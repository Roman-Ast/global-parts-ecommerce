<?php

namespace App\Console\Commands;

use App\Models\DemandSignal;
use App\Models\LeadRequest;
use App\Models\WhatsappLead;
use Illuminate\Console\Command;

/**
 * Импортирует РАЗБОР, сделанный Claude вручную в чате (не через API) — см.
 * переписку с Романом 2026-10-03: после серии живых 504/max_tokens/thinking
 * сбоев на shared-хостинге без SSH решили не бороться с автоматикой каждую
 * неделю, а гонять этот пайплайн "руками" раз в неделю — Роман выгружает
 * CSV с прода (lead_requests + переписка), кидает в чат, Claude читает и
 * выносит те же вердикты, что раньше делал whatsapp:analyze-demand сам по
 * себе, только бесплатно и без таймаутов. Результат — JSON с тем же набором
 * полей, что demand_signals ожидает (см. $schema ниже), эта команда грузит
 * его в БД — upsert'ит whatsapp_leads/lead_requests по ходу, если их ещё
 * нет локально.
 *
 * Формат JSON — массив лидов:
 * [{ source_lead_request_id, phone, source, vin, brand, car_model, car_year,
 *    manager_status, parts: [{ name, side, position, availability_answer,
 *    lead_time_days, quoted_price, outcome, decline_reason, outcome_amount,
 *    notes }] }]
 */
class ImportAnalyzedDemandCommand extends Command
{
    protected $signature = 'whatsapp:import-analyzed-demand {path : Путь до JSON-файла с разбором}';

    protected $description = 'Загружает в demand_signals/lead_requests ручной разбор переписки (сделанный Claude в чате, не через API)';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("Файл не найден: {$path}");
            return 1;
        }

        $leads = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Невалидный JSON: ' . json_last_error_msg());
            return 1;
        }

        $leadsCount = 0;
        $signalsCount = 0;

        foreach ($leads as $leadData) {
            $lead = WhatsappLead::firstOrCreate(
                ['phone' => $leadData['phone']],
                [
                    'source' => $leadData['source'] ?? null,
                    'status' => $leadData['manager_status'] ?? 'new',
                    'last_seen_at' => now(),
                ]
            );

            // Живая находка 2026-10-05: искать по ['whatsapp_lead_id','status'
            // =>'pending'] ломалось при повторном/частичном прогоне — как
            // только обработка лида завершалась (status='closed'), ПОВТОРНЫЙ
            // запуск того же файла (напр. после сбоя посреди батча) не
            // находил существующую заявку и создавал дубль с осиротевшими
            // demand_signals. Теперь сначала ищем ТОЧНО по
            // source_lead_request_id (сам ID заявки с прода, есть в каждом
            // элементе разбора) — это однозначно; без него — любую заявку
            // этого лида НЕЗАВИСИМО от статуса; создаём новую только если
            // вообще ничего не нашлось.
            $leadRequest = null;
            if (!empty($leadData['source_lead_request_id'])) {
                $leadRequest = LeadRequest::find($leadData['source_lead_request_id']);
            }
            if (!$leadRequest) {
                $leadRequest = LeadRequest::where('whatsapp_lead_id', $lead->id)->orderByDesc('id')->first();
            }
            if (!$leadRequest) {
                $leadRequest = LeadRequest::create([
                    'whatsapp_lead_id' => $lead->id,
                    'source' => $leadData['source'] ?? null,
                    'vin' => $leadData['vin'] ?? null,
                    'brand' => $leadData['brand'] ?? null,
                    'car_model' => $leadData['car_model'] ?? null,
                    'car_year' => $leadData['car_year'] ?? null,
                    'status' => 'pending',
                    'raw_request' => 'Импортировано из ручного разбора (прод lead_request_id=' . ($leadData['source_lead_request_id'] ?? '?') . ')',
                    'parts_json' => array_map(fn ($p) => ['name' => $p['name'], 'side' => $p['side'] ?? null, 'position' => $p['position'] ?? null], $leadData['parts']),
                ]);
            }

            $allResolved = true;

            foreach ($leadData['parts'] as $part) {
                if (($part['outcome'] ?? 'pending') === 'pending') {
                    $allResolved = false;
                }

                DemandSignal::updateOrCreate(
                    [
                        'whatsapp_lead_id' => $lead->id,
                        'lead_request_id' => $leadRequest->id,
                        'part_name' => $part['name'],
                        'part_side' => $part['side'] ?? null,
                        'part_position' => $part['position'] ?? null,
                    ],
                    [
                        'source' => $leadData['source'] ?? null,
                        'phone' => $lead->phone,
                        'vin' => $leadData['vin'] ?? null,
                        'brand' => $leadData['brand'] ?? null,
                        'car_model' => $leadData['car_model'] ?? null,
                        'car_year' => $leadData['car_year'] ?? null,
                        'availability_answer' => $part['availability_answer'] ?? 'unknown',
                        'lead_time_days' => $part['lead_time_days'] ?? null,
                        'quoted_price' => $part['quoted_price'] ?? null,
                        'outcome' => $part['outcome'] ?? 'pending',
                        'decline_reason' => $part['decline_reason'] ?? null,
                        'outcome_amount' => $part['outcome_amount'] ?? null,
                        'manager_status' => $leadData['manager_status'] ?? $lead->status,
                        'manager_status_label' => \App\Support\LeadStatuses::labelFor($leadData['manager_status'] ?? $lead->status),
                        'notes' => $part['notes'] ?? null,
                        'raw_llm_response' => null, // не от API — разобрано вручную Claude в чате
                        'analyzed_at' => now(),
                    ]
                );
                $signalsCount++;
            }

            if ($allResolved) {
                $leadRequest->update(['status' => 'closed']);
            }

            $leadsCount++;
            $this->line("→ {$leadData['brand']} {$leadData['car_model']} ({$leadData['phone']}) — " . count($leadData['parts']) . ' позиций');
        }

        $this->info("Готово: {$leadsCount} лидов, {$signalsCount} demand_signals записано/обновлено.");

        return 0;
    }
}
