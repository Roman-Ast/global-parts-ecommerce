<?php

namespace App\Http\Controllers;

use App\Models\DemandSignal;
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

        return view('admin.demand-analysis', compact(
            'totalSignals', 'byPart', 'byBrand', 'byCarModel', 'byOutcome', 'byDeclineReason', 'byAvailability', 'lostToStockOrPrice'
        ));
    }
}
