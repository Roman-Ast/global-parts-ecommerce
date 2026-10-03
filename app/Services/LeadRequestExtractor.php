<?php

namespace App\Services;

use App\Models\LeadRequest;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;

/**
 * Разбор ОДНОГО входящего сообщения через Claude (VIN/марка/модель/год +
 * список запчастей) и мёрдж в lead_requests — вынесено из
 * WhatsappMessageObserver::created() 2026-10-03, чтобы ту же логику можно
 * было прогнать батчем по уже накопленной истории (whatsapp:backfill-demand),
 * не только на лету по новым сообщениям. Наблюдатель и бэкфилл-команда
 * вызывают ровно этот метод — поведение идентично для обоих путей, разницы
 * в данных быть не должно.
 *
 * Решение, КОГДА вызывать этот метод (рубильник
 * WHATSAPP_LLM_EXTRACTION_ENABLED, статус "Рабочие" и т.п.) остаётся на
 * вызывающей стороне — сам класс ничего не решает, просто разбирает.
 */
class LeadRequestExtractor
{
    public function __construct(private ClaudeExtractionService $claude)
    {
    }

    public function extract(WhatsappMessage $message): void
    {
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
                \Log::info("LeadRequestExtractor: Обработка вложения...");
                $fileParsed = $this->claude->parseAttachment($message->file_url);
                \Log::info("LeadRequestExtractor: Ответ Claude по вложению", ['parsed' => $fileParsed]);

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
                \Log::info("LeadRequestExtractor: Обработка текста...");
                $textParsed = $this->claude->parseRequest($message->message_text);
                \Log::info("LeadRequestExtractor: Ответ Claude по тексту", ['parsed' => $textParsed]);

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
            $leadRequest = LeadRequest::where('whatsapp_lead_id', $message->whatsapp_lead_id)
                ->where('status', 'pending')
                ->where('created_at', '>', now()->subDay())
                ->latest()
                ->first();

            if ($leadRequest) {
                // --- ОБНОВЛЯЕМ существующий запрос ---
                \Log::info("LeadRequestExtractor: Найден запрос ID: {$leadRequest->id}. Обновляю...");

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
                    \Log::info("LeadRequestExtractor: Запрос обновлён", ['updates' => array_keys($updates)]);
                } else {
                    \Log::info("LeadRequestExtractor: Нечего обновлять.");
                }

            } elseif ($vin || $brand || $carModel || !empty($parts)) {
                // --- СОЗДАЁМ новый запрос ---
                \Log::info("LeadRequestExtractor: Создаю НОВЫЙ запрос.");
                LeadRequest::create([
                    'whatsapp_lead_id' => $message->whatsapp_lead_id,
                    // Не через WhatsappMessage::lead() — та связь завязана на
                    // несуществующую колонку chat_id (см. CLAUDE.md/разбор WhatsApp
                    // 2026-09-13), тянем source напрямую по FK.
                    'source'           => WhatsappLead::where('id', $message->whatsapp_lead_id)->value('source'),
                    'vin'              => $vin,
                    'brand'            => $brand,
                    'car_model'        => $carModel,
                    'car_year'         => $carYear,
                    'raw_request'      => $message->message_text ?? 'Запрос по медиа',
                    'parts_json'       => $parts,
                    'status'           => 'pending',
                ]);
            } else {
                \Log::info("LeadRequestExtractor: Полезных данных не найдено, запись не создана.");
            }

        } catch (\Exception $e) {
            \Log::error("ОШИБКА В LeadRequestExtractor: " . $e->getMessage(), [
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
