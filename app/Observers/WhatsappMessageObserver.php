<?php

namespace App\Observers;

use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use App\Services\ClaudeExtractionService;
use App\Support\LeadStatuses;

class WhatsappMessageObserver
{
    protected ClaudeExtractionService $claude;

    public function __construct(ClaudeExtractionService $claude)
    {
        $this->claude = $claude;
    }

    public function created(WhatsappMessage $message)
    {
        if (!$message->is_incoming) return;

        // Рубильник по решению Романа 2026-09-14 — сырые сообщения копим всегда
        // (это просто INSERT из вебхука, бесплатно), а платный LLM-разбор стоит
        // выключенным, пока таксономия причин отказа не выведена вручную из
        // реальных чатов за первую неделю. См. .env WHATSAPP_LLM_EXTRACTION_ENABLED.
        if (!config('services.anthropic.whatsapp_extraction_enabled')) {
            return;
        }

        // "Рабочие" (просьба Романа 2026-09-21, исправляет более раннее
        // противоречивое решение того же дня — см. CLAUDE.md/WhatsAppWebhookController):
        // свои/коллеги/рабочая группа, перенесённые в этот статус, ДОЛЖНЫ
        // сохраняться в whatsapp_messages как обычно (иначе колонку "Рабочие"
        // просто нечем наполнить), но НИКОГДА не должны попадать в платный
        // LLM-разбор спроса и в lead_requests/demand_signals — это не
        // клиентские запросы, анализировать их как спрос бессмысленно и
        // просто тратит деньги на Claude API впустую.
        $leadStatus = WhatsappLead::where('id', $message->whatsapp_lead_id)->value('status');
        if ($leadStatus === LeadStatuses::STAFF_STATUS) {
            return;
        }

        \Log::info("=== Начало обработки сообщения {$message->id} ===");

        try {
            $vin      = null;
            $brand    = null;
            $carModel = null;
            $carYear  = null;
            $parts    = [];
            $rawResponses = [];

            // 1. Парсим ВЛОЖЕНИЕ (фото/PDF техпаспорта), если есть — VIN+марка+модель+год одним запросом
            if (!empty($message->file_url)) {
                \Log::info("Observer: Обработка вложения...");
                $fileParsed = $this->claude->parseAttachment($message->file_url);
                \Log::info("Observer: Ответ Claude по вложению", ['parsed' => $fileParsed]);

                if ($fileParsed) {
                    $vin      = $this->nullIfLiteralNull($fileParsed['vin'] ?? null);
                    $brand    = $this->nullIfLiteralNull($fileParsed['brand'] ?? null);
                    $carModel = $this->nullIfLiteralNull($fileParsed['car_model'] ?? null);
                    $carYear  = $this->nullIfLiteralNull($fileParsed['car_year'] ?? null);
                    $rawResponses['attachment'] = $fileParsed;
                }
            }

            // 2. Парсим ТЕКСТ, если есть — запчасти + VIN/марка/модель/год, если упомянуты текстом
            if (!empty($message->message_text)) {
                \Log::info("Observer: Обработка текста...");
                $textParsed = $this->claude->parseRequest($message->message_text);
                \Log::info("Observer: Ответ Claude по тексту", ['parsed' => $textParsed]);

                if ($textParsed) {
                    // Данные из вложения (техпаспорт) приоритетнее — надёжнее текста клиента
                    $vin      = $vin ?? $this->nullIfLiteralNull($textParsed['vin'] ?? null);
                    $brand    = $brand ?? $this->nullIfLiteralNull($textParsed['brand'] ?? null);
                    $carModel = $carModel ?? $this->nullIfLiteralNull($textParsed['car_model'] ?? null);
                    $carYear  = $carYear ?? $this->nullIfLiteralNull($textParsed['car_year'] ?? null);
                    $parts    = $textParsed['parts'] ?? [];
                    $rawResponses['text'] = $textParsed;
                }
            }

            // Сохраняем сырой результат разбора ЭТОГО сообщения — независимо от того,
            // смёрджится ли он дальше в lead_requests (аудит/отладка, см. миграцию
            // 2026_09_13_000003).
            if (!empty($rawResponses)) {
                $message->forceFill([
                    'extracted_vin' => $vin,
                    'extracted_brand' => $brand,
                    'extracted_car_model' => $carModel,
                    'extracted_car_year' => $carYear,
                    'extracted_parts_json' => $parts,
                    'llm_raw_response' => $rawResponses,
                    'llm_processed_at' => now(),
                ])->save();
            }

            // 3. Ищем активный pending-запрос этого лида за последние 24 часа
            $leadRequest = \App\Models\LeadRequest::where('whatsapp_lead_id', $message->whatsapp_lead_id)
                ->where('status', 'pending')
                ->where('created_at', '>', now()->subDay())
                ->latest()
                ->first();

            if ($leadRequest) {
                // --- ОБНОВЛЯЕМ существующий запрос ---
                \Log::info("Observer: Найден запрос ID: {$leadRequest->id}. Обновляю...");

                $updates = [];

                // Поля заполняем только если они ещё пустые
                foreach (['vin' => $vin, 'brand' => $brand, 'car_model' => $carModel, 'car_year' => $carYear] as $field => $value) {
                    if (empty($leadRequest->{$field}) && $value) {
                        $updates[$field] = $value;
                    }
                }

                // Дописываем текст сообщения в raw_request
                if (!empty($message->message_text)) {
                    $updates['raw_request'] = trim(($leadRequest->raw_request ?? '') . "\n" . $message->message_text);
                }

                // Мерджим запчасти без дублей (сравниваем по name+side+position)
                if (!empty($parts)) {
                    $existing = is_array($leadRequest->parts_json) ? $leadRequest->parts_json : [];
                    $updates['parts_json'] = $this->mergePartsUnique($existing, $parts);
                }

                if (!empty($updates)) {
                    $leadRequest->update($updates);
                    \Log::info("Observer: Запрос обновлён", ['updates' => array_keys($updates)]);
                } else {
                    \Log::info("Observer: Нечего обновлять.");
                }

            } elseif ($vin || $brand || $carModel || !empty($parts)) {
                // --- СОЗДАЁМ новый запрос ---
                \Log::info("Observer: Создаю НОВЫЙ запрос.");
                \App\Models\LeadRequest::create([
                    'whatsapp_lead_id' => $message->whatsapp_lead_id,
                    // Не через WhatsappMessage::lead() — та связь завязана на
                    // несуществующую колонку chat_id (см. CLAUDE.md/разбор WhatsApp
                    // 2026-09-13), тянем source напрямую по FK.
                    'source'           => \App\Models\WhatsappLead::where('id', $message->whatsapp_lead_id)->value('source'),
                    'vin'              => $vin,
                    'brand'            => $brand,
                    'car_model'        => $carModel,
                    'car_year'         => $carYear,
                    'raw_request'      => $message->message_text ?? 'Запрос по медиа',
                    'parts_json'       => $parts,
                    'status'           => 'pending',
                ]);
            } else {
                \Log::info("Observer: Полезных данных не найдено, запись не создана.");
            }

        } catch (\Exception $e) {
            \Log::error("ОШИБКА В OBSERVER: " . $e->getMessage(), [
                'message_id' => $message->id,
                'trace'      => $e->getTraceAsString(),
            ]);
        }

        \Log::info("=== Конец обработки сообщения {$message->id} ===");
    }

    /**
     * Claude иногда буквально пишет строку "null" вместо JSON null — приводим
     * оба варианта отсутствия значения к одному PHP null.
     */
    private function nullIfLiteralNull(?string $value): ?string
    {
        return ($value === null || $value === 'null') ? null : $value;
    }

    /**
     * Мёрдж запчастей без дублей по комбинации name+side+position
     */
    private function mergePartsUnique(array $existing, array $incoming): array
    {
        $key = fn($p) => strtolower(trim($p['name'] ?? ''))
            . '|' . ($p['side'] ?? 'null')
            . '|' . ($p['position'] ?? 'null');

        $index = [];
        foreach ($existing as $part) {
            $index[$key($part)] = true;
        }

        foreach ($incoming as $part) {
            if (!isset($index[$key($part)])) {
                $existing[] = $part;
            }
        }

        return $existing;
    }

}
