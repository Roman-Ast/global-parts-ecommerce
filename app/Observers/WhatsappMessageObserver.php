<?php

namespace App\Observers;

use App\Models\WhatsappMessage;
use App\Models\LeadRequest;
use App\Services\GeminiService;

class WhatsappMessageObserver
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function created(WhatsappMessage $message)
    {
        if (!$message->is_incoming) return;

        \Log::info("=== Начало обработки сообщения {$message->id} ===");

        try {
            $vin      = null;
            $carModel = null;
            $parts    = [];

            // 1. Парсим ФАЙЛ (если есть)
            if (!empty($message->file_url)) {
                \Log::info("Observer: Обработка файла...");
                $vin = $this->gemini->parseFileForVin($message->file_url);
                \Log::info("Observer: Извлечен VIN: " . ($vin ?? 'null'));
            }

            // 2. Парсим ТЕКСТ (если есть)
            if (!empty($message->message_text)) {
                \Log::info("Observer: Обработка текста...");
                $parsed = $this->gemini->parseRequest($message->message_text);
                \Log::info("Observer: Ответ Gemini", ['parsed' => $parsed]);

                if ($parsed) {
                    // VIN из файла приоритетнее чем из текста
                    $vin      = $vin ?? ($parsed['vin'] !== 'null' ? ($parsed['vin'] ?? null) : null);
                    $carModel = $parsed['car_model'] !== 'null' ? ($parsed['car_model'] ?? null) : null;
                    $parts    = $parsed['parts'] ?? [];
                    \Log::info("Observer: Текст распаршен", [
                        'vin'      => $vin,
                        'model'    => $carModel,
                        'parts'    => count($parts),
                    ]);
                }
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
                if (empty($leadRequest->vin) && $vin) {
                    $updates['vin'] = $vin;
                }
                if (empty($leadRequest->car_model) && $carModel) {
                    $updates['car_model'] = $carModel;
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

            } elseif ($vin || $carModel || !empty($parts)) {
                // --- СОЗДАЁМ новый запрос ---
                \Log::info("Observer: Создаю НОВЫЙ запрос.");
                \App\Models\LeadRequest::create([
                    'whatsapp_lead_id' => $message->whatsapp_lead_id,
                    'vin'              => $vin,
                    'car_model'        => $carModel,
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