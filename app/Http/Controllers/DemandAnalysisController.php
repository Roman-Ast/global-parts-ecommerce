<?php

namespace App\Http\Controllers;

use App\Models\DemandSignal;
use App\Models\LeadRequest;
use Illuminate\Support\Facades\DB;

/**
 * Вьюха для demand_signals — просьба Романа 2026-10-03, "пока простенький
 * анализ пока по марке модели авто... потом посмотрим может сделать более
 * сложный анализ по категориям запчастей или бренду авто". Таблица
 * наполняется ЕЖЕНЕДЕЛЬНО вручную через whatsapp:import-analyzed-demand
 * (ручной разбор Claude в чате, не через Anthropic API — см. докблок той
 * команды за историей решения отказаться от автоматики на этом хостинге).
 *
 * ABC-анализ здесь в классическом инвентарном смысле: сортируем позиции по
 * частоте запроса, считаем накопленный % от общего числа запросов, A — верх
 * до 80% совокупного спроса, B — следующие до 95%, C — остаток. Это НЕ про
 * выручку (мы не знаем цену для каждого реального запроса надёжно), а про
 * частоту — "что чаще всего спрашивают", что и есть сигнал для закупки.
 */
class DemandAnalysisController extends Controller
{
    public function index()
    {
        $totalSignals = DemandSignal::count();

        $byPart = DemandSignal::select('part_name', DB::raw('count(*) as cnt'))
            ->groupBy('part_name')
            ->orderByDesc('cnt')
            ->get();

        $running = 0;
        $byPart = $byPart->map(function ($row) use (&$running, $totalSignals) {
            $running += $row->cnt;
            $cumPct = $totalSignals > 0 ? round($running / $totalSignals * 100, 1) : 0;
            $row->pct = $totalSignals > 0 ? round($row->cnt / $totalSignals * 100, 1) : 0;
            $row->cum_pct = $cumPct;
            $row->tier = $cumPct <= 80 ? 'A' : ($cumPct <= 95 ? 'B' : 'C');
            return $row;
        });

        $byBrand = DemandSignal::select('brand', DB::raw('count(*) as cnt'))
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->groupBy('brand')
            ->orderByDesc('cnt')
            ->get();

        $byCarModel = DemandSignal::select('brand', 'car_model', DB::raw('count(*) as cnt'))
            ->whereNotNull('car_model')
            ->where('car_model', '!=', '')
            ->groupBy('brand', 'car_model')
            ->orderByDesc('cnt')
            ->get();

        $byOutcome = DemandSignal::select('outcome', DB::raw('count(*) as cnt'))
            ->groupBy('outcome')
            ->orderByDesc('cnt')
            ->get();

        // Исход СОГЛАСНО МЕНЕДЖЕРУ (реальный статус лида в CRM на момент
        // анализа, manager_status_label) — рядом с $byOutcome (исход
        // СОГЛАСНО ЛЛМ), просьба Романа 2026-10-05, чтобы сверять одно с
        // другим напрямую. Не LLM-вывод — просто снэпшот реального статуса
        // из whatsapp_leads.status на момент whatsapp:analyze-demand (см.
        // CLAUDE.md, добавлено 2026-09-?? в AnalyzeDemandSignalsCommand).
        $byManagerStatus = DemandSignal::select('manager_status_label', DB::raw('count(*) as cnt'))
            ->whereNotNull('manager_status_label')
            ->groupBy('manager_status_label')
            ->orderByDesc('cnt')
            ->get();

        $byDeclineReason = DemandSignal::select('decline_reason', DB::raw('count(*) as cnt'))
            ->whereNotNull('decline_reason')
            ->groupBy('decline_reason')
            ->orderByDesc('cnt')
            ->get();

        $byAvailability = DemandSignal::select('availability_answer', DB::raw('count(*) as cnt'))
            ->groupBy('availability_answer')
            ->orderByDesc('cnt')
            ->get();

        // "Горящий" сигнал для склада: спросили, деталь была в наличии, но
        // дорого — или не было в наличии и не готовы были ждать (см.
        // decline_reason=no_stock_wont_wait в докблоке миграции demand_signals).
        $lostToStockOrPrice = DemandSignal::whereIn('decline_reason', ['no_stock_wont_wait', 'in_stock_too_expensive'])
            ->select('part_name', 'decline_reason', DB::raw('count(*) as cnt'))
            ->groupBy('part_name', 'decline_reason')
            ->orderByDesc('cnt')
            ->get();

        $quoteFunnel = $this->buildQuoteFunnel();

        return view('admin.demand-analysis', compact(
            'totalSignals', 'byPart', 'byBrand', 'byCarModel', 'byOutcome', 'byManagerStatus', 'byDeclineReason', 'byAvailability', 'lostToStockOrPrice', 'quoteFunnel'
        ));
    }

    /**
     * Воронка "сколько КП отправлено / сколько купили" — просьба Романа
     * 2026-10-05 ("чтоб понимать процент закрытий и в целом сколько заявок
     * обрабатываем"). Считаем на уровне lead_requests (одна заявка =
     * один клиентский запрос, не одна деталь внутри него, как у
     * demand_signals), join на whatsapp_leads.status — это РЕАЛЬНЫЙ
     * текущий статус CRM, а не вывод ЛЛМ, поэтому не требует предварительно
     * прогнанного whatsapp:analyze-demand — работает сразу после обычного
     * whatsapp:backfill-demand (дешёвый Haiku-шаг).
     *
     * "КП отправлено" — это НЕ только статус `offer` буквально: если лид
     * продвинулся дальше по воронке (работа с возражениями, оплата,
     * купил/ждёт, продано) или отвалился с явно пост-КП причиной
     * ("дорого", "передумал") — КП физически должно было уже уйти, иначе
     * эти стадии были бы невозможны. Статусы ДО КП (искали деталь, не
     * нашли, не ответил, молчит до всякого предложения) в счёт не идут.
     * Граница спорная (особенно 'no_response' — не отвечает мог и после
     * КП) — отмечено ниже константой на случай, если Роман скажет
     * поправить классификацию.
     */
    private function buildQuoteFunnel(): array
    {
        // Статусы, при которых КП объективно уже должно было уйти клиенту.
        $kpSentOrBeyondStatuses = [
            'offer', 'thinking', 'silent', 'expensive', 'wait', 'pending',
            'payment', 'bought_waiting', 'deal_closed',
            'in_stock_too_expensive', 'changed_mind',
        ];
        $boughtStatuses = ['payment', 'bought_waiting', 'deal_closed'];

        $totalRequests = LeadRequest::count();

        $statusCounts = LeadRequest::join('whatsapp_leads', 'whatsapp_leads.id', '=', 'lead_requests.whatsapp_lead_id')
            ->select('whatsapp_leads.status', DB::raw('count(*) as cnt'))
            ->groupBy('whatsapp_leads.status')
            ->pluck('cnt', 'status');

        $kpSent = 0;
        $bought = 0;
        foreach ($statusCounts as $status => $cnt) {
            if (in_array($status, $kpSentOrBeyondStatuses, true)) {
                $kpSent += $cnt;
            }
            if (in_array($status, $boughtStatuses, true)) {
                $bought += $cnt;
            }
        }

        return [
            'totalRequests' => $totalRequests,
            'kpSent' => $kpSent,
            'bought' => $bought,
            'conversionPct' => $kpSent > 0 ? round($bought / $kpSent * 100, 1) : null,
        ];
    }
}
