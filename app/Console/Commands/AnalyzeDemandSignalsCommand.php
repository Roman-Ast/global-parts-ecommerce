<?php

namespace App\Console\Commands;

use App\Models\DemandSignal;
use App\Models\LeadRequest;
use App\Services\ClaudeExtractionService;
use App\Support\LeadStatuses;
use Illuminate\Console\Command;

/**
 * Ночной джоб (задуман на 23:00, см. переписку с Романом — планирование
 * архитектуры CRM 2026-09-12/13): смотрит на ВСЮ переписку по каждой открытой
 * заявке (lead_requests), а не на одно сообщение, и выносит по каждой
 * запрошенной детали вердикт — что мы ответили по наличию и чем кончилось
 * (купил/отказался/замолчал/ещё пендинг). Результат — demand_signals,
 * финальный нормализованный слой под анализ спроса на склад.
 *
 * Не поставлена в Schedule (routes/console.php) намеренно — там стоит пометка
 * "Расписание — не трогаем пока", плюс сначала нужен реальный ANTHROPIC_API_KEY
 * и накопленная переписка, чтобы было что анализировать.
 */
class AnalyzeDemandSignalsCommand extends Command
{
    protected $signature = 'whatsapp:analyze-demand {--all : Пересчитать даже уже закрытые заявки (по умолчанию только status=pending)} {--limit=100}';

    protected $description = 'Анализирует переписку по заявкам (lead_requests) и заполняет demand_signals исходом по каждой детали';

    public function handle(ClaudeExtractionService $claude): int
    {
        $limit = (int) $this->option('limit');

        // "Рабочие" (просьба Романа 2026-09-21) — свои/коллеги никогда не
        // должны попадать в анализ спроса. WhatsappMessageObserver уже не
        // создаёт им lead_requests вовсе, это доп. страховка на случай,
        // если такая запись всё же появится другим путём.
        $query = LeadRequest::query()
            ->whereNotNull('parts_json')
            ->where('parts_json', '!=', '[]')
            ->whereHas('lead', fn ($q) => $q->where('status', '!=', LeadStatuses::STAFF_STATUS));

        if (!$this->option('all')) {
            $query->where('status', 'pending');
        }

        $leadRequests = $query->orderBy('created_at')->limit($limit)->get();

        if ($leadRequests->isEmpty()) {
            $this->info('Нет заявок для анализа.');
            return 0;
        }

        $this->info("Анализируем {$leadRequests->count()} заявок...");

        foreach ($leadRequests as $leadRequest) {
            $this->line("→ Заявка #{$leadRequest->id} (лид {$leadRequest->whatsapp_lead_id}, {$leadRequest->brand} {$leadRequest->car_model})");

            $lead = $leadRequest->lead;
            if (!$lead) {
                $this->warn('  ⨯ лид не найден — пропуск');
                continue;
            }

            $messages = $lead->messages()->orderBy('created_at')->get();
            if ($messages->isEmpty()) {
                $this->warn('  ⨯ нет сообщений у лида — пропуск');
                continue;
            }

            $transcript = $messages->map(function ($m) {
                $who = $m->is_incoming ? 'Клиент' : 'Мы';
                $when = $m->created_at->format('Y-m-d H:i');
                $text = $m->message_text ?: ($m->file_url ? '[вложение]' : '');
                return "[{$when}] {$who}: {$text}";
            })->implode("\n");

            $result = $claude->analyzeOutcome($leadRequest->parts_json, $transcript, now()->toIso8601String());

            if (!$result || empty($result['parts'])) {
                $this->warn('  ⨯ Claude не вернул вердикт — пропуск (см. лог на причину)');
                continue;
            }

            $allResolved = true;

            foreach ($result['parts'] as $part) {
                $outcome = $part['outcome'] ?? 'pending';
                if ($outcome === 'pending') {
                    $allResolved = false;
                }

                DemandSignal::updateOrCreate(
                    [
                        'whatsapp_lead_id' => $lead->id,
                        'lead_request_id' => $leadRequest->id,
                        'part_name' => $part['name'] ?? 'unknown',
                        'part_side' => $part['side'] ?? null,
                        'part_position' => $part['position'] ?? null,
                    ],
                    [
                        'source' => $leadRequest->source,
                        'phone' => $lead->phone,
                        'vin' => $leadRequest->vin,
                        'brand' => $leadRequest->brand,
                        'car_model' => $leadRequest->car_model,
                        'car_year' => $leadRequest->car_year,
                        'availability_answer' => $part['availability_answer'] ?? 'unknown',
                        'lead_time_days' => $part['lead_time_days'] ?? null,
                        'quoted_price' => $part['quoted_price'] ?? null,
                        'outcome' => $outcome,
                        'decline_reason' => $part['decline_reason'] ?? null,
                        'outcome_amount' => $part['outcome_amount'] ?? null,
                        'notes' => $part['notes'] ?? null,
                        'raw_llm_response' => $result,
                        'analyzed_at' => now(),
                    ]
                );
            }

            if ($allResolved) {
                $leadRequest->update(['status' => 'closed']);
                $this->line('  · заявка закрыта (все позиции с исходом)');
            } else {
                $this->line('  · заявка ещё открыта (есть pending-позиции)');
            }
        }

        $this->info('Готово.');
        return 0;
    }
}
