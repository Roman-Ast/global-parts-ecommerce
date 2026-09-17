<?php

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Замена GeminiService (см. переписку с Романом 2026-09-13) — тот же контракт
 * (parseRequest/parseFileForVin), плюс parseAttachment() теперь вытаскивает из
 * фото/PDF техпаспорта не только VIN, а сразу марку/модель/год — раньше это
 * работало только из текста, не из документа.
 *
 * Модель — claude-opus-5 (дефолт). Метод вызывается синхронно на КАЖДОЕ входящее
 * сообщение (WhatsappMessageObserver) — если счёт по API станет ощутимым при
 * реальном объёме переписки, можно переключить на более дешёвую модель здесь же
 * (это решение по деньгам, не техническое — оставлено на усмотрение Романа).
 */
class ClaudeExtractionService
{
    // По решению Романа (2026-09-13) — sonnet вместо opus, дешевле ($2/$10 за
    // млн токенов против $5/$25), для извлечения VIN/марки/списка запчастей из
    // JSON разница в качестве не критична.
    private const MODEL = 'claude-sonnet-5';

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.api_key'));
    }

    /**
     * Разбор ТЕКСТА сообщения клиента — VIN/марка/модель/год (если упомянуты
     * текстом) + список запрошенных запчастей.
     *
     * @return array{vin: ?string, brand: ?string, car_model: ?string, car_year: ?string, parts: array}|null
     */
    public function parseRequest(string $text): ?array
    {
        $prompt = <<<PROMPT
Ты — эксперт по автозапчастям. Извлеки данные из сообщения клиента автомагазина.

Верни ТОЛЬКО валидный JSON без markdown-разметки, без пояснений, без текста вне скобок:
{
    "vin": "17-значный VIN или frame-номер (японские авто) если есть в тексте, иначе null",
    "brand": "марка автомобиля если есть, иначе null",
    "car_model": "модель автомобиля если есть, иначе null",
    "car_year": "год выпуска если есть (можно диапазон типа \"2015-2018\"), иначе null",
    "parts": [
        {
            "name": "название запчасти",
            "side": "left / right / null",
            "position": "front / rear / upper / lower / null"
        }
    ]
}

Сообщение клиента: {$text}
PROMPT;

        $parsed = $this->callAndParseJson([
            ['role' => 'user', 'content' => $prompt],
        ], maxTokens: 512);

        if ($parsed === null) {
            return null;
        }

        return [
            'vin' => $parsed['vin'] ?? null,
            'brand' => $parsed['brand'] ?? null,
            'car_model' => $parsed['car_model'] ?? null,
            'car_year' => $parsed['car_year'] ?? null,
            'parts' => $parsed['parts'] ?? [],
        ];
    }

    /**
     * Разбор ВЛОЖЕНИЯ (фото или PDF техпаспорта/СРТС) — VIN + марка/модель/год
     * одним запросом (у Gemini-версии это вытаскивало только VIN).
     *
     * @return array{vin: ?string, brand: ?string, car_model: ?string, car_year: ?string}|null
     */
    public function parseAttachment(string $fileUrl): ?array
    {
        $fileResponse = Http::timeout(15)->get($fileUrl);
        if (!$fileResponse->successful()) {
            Log::warning('ClaudeExtractionService: не удалось скачать файл', ['url' => $fileUrl]);
            return null;
        }

        $extension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $isPdf = $extension === 'pdf';
        $mediaType = $isPdf ? 'application/pdf' : 'image/jpeg';
        $blockType = $isPdf ? 'document' : 'image';
        $base64 = base64_encode($fileResponse->body());

        $prompt = <<<PROMPT
Ты — ассистент по распознаванию автомобильных документов (техпаспорт РК, СРТС РФ
и аналоги). Найди в документе:
- VIN-код (ровно 17 символов, латиница+цифры, без I/O/Q) ИЛИ frame-номер
  (японские авто, 7-10 символов, часто с дефисом, напр. "EF7-1234567")
- марку автомобиля
- модель автомобиля
- год выпуска

Верни ТОЛЬКО валидный JSON без markdown-разметки, без пояснений:
{
    "vin": "VIN или frame-номер КАК ЕСТЬ, иначе null",
    "brand": "марка, иначе null",
    "car_model": "модель, иначе null",
    "car_year": "год выпуска, иначе null"
}
PROMPT;

        $parsed = $this->callAndParseJson([
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => $blockType,
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mediaType,
                            'data' => $base64,
                        ],
                    ],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ],
        ], maxTokens: 256);

        if ($parsed === null) {
            return null;
        }

        return [
            'vin' => $parsed['vin'] ?? null,
            'brand' => $parsed['brand'] ?? null,
            'car_model' => $parsed['car_model'] ?? null,
            'car_year' => $parsed['car_year'] ?? null,
        ];
    }

    /**
     * Совместимость со старым контрактом GeminiService::parseFileForVin() —
     * пока наблюдатель/другой код не переведён на parseAttachment() целиком.
     */
    public function parseFileForVin(string $fileUrl): ?string
    {
        return $this->parseAttachment($fileUrl)['vin'] ?? null;
    }

    /**
     * Анализирует ВЕСЬ диалог по заявке (не одно сообщение) и по каждой
     * запрошенной детали выносит вердикт: что мы ответили по наличию, и чем
     * дело кончилось (купил/отказался/замолчал/ещё не ясно). Это и есть
     * содержимое таблицы demand_signals — см. AnalyzeDemandSignalsCommand.
     *
     * @param array $parts исходный parts_json из lead_requests — список того,
     *                     что клиент просил, модель должна вынести вердикт
     *                     ПО КАЖДОЙ позиции из этого списка
     * @param string $transcript вся переписка по лиду в формате
     *                           "[дата время] Клиент: текст" / "[..] Мы: текст"
     * @param string $nowIso текущее время ISO — чтобы модель могла посчитать,
     *                       сколько часов прошло с последнего сообщения клиента
     *                       (критично для вердикта "молчание" vs "ещё думает")
     *
     * @return array{parts: array}|null
     */
    public function analyzeOutcome(array $parts, string $transcript, string $nowIso): ?array
    {
        $partsForPrompt = json_encode($parts, JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
Ты анализируешь переписку автомагазина с клиентом в WhatsApp, чтобы понять, чем
закончился запрос по КАЖДОЙ из запрошенных деталей ниже.

Запрошенные детали (ответ должен содержать ровно эти позиции, в том же порядке,
с теми же name/side/position):
{$partsForPrompt}

Текущее время: {$nowIso}

Переписка (в хронологическом порядке, "Клиент:" — сообщения от клиента, "Мы:" —
наши ответы):
---
{$transcript}
---

По КАЖДОЙ детали из списка выше определи:
- availability_answer: "in_stock" (сказали что есть в наличии), "on_order"
  (только под заказ), "not_found" (не нашли/нет такой), "unknown" (мы вообще
  не ответили по этой позиции в переписке)
- lead_time_days: срок в днях, если называли "под заказ N дней" (число N),
  иначе null
- quoted_price: цена, которую назвали клиенту за эту деталь (число, без
  пробелов/валюты), иначе null
- outcome: "bought" (клиент подтвердил покупку/оплатил именно эту деталь),
  "declined" (явно отказался), "silent" (клиент не ответил на наше
  предложение дольше 24 часов считая от текущего времени выше, и явного
  отказа не было), "pending" (диалог ещё живой, дискуссия продолжается менее
  24 часов назад)
- decline_reason: заполняется ТОЛЬКО если outcome="declined", иначе null.
  Строго одно из:
  - "no_stock_wont_wait" — детали не было в наличии (под заказ/нет сейчас),
    и клиент явно не готов был ждать (сказал "долго", "надо сейчас" и т.п.)
    — то есть купил бы, будь она в наличии прямо сейчас
  - "in_stock_too_expensive" — деталь БЫЛА в наличии, но клиент отказался
    именно из-за цены (сказал "дорого", "есть дешевле", ушёл сравнивать цену)
  - "part_not_found" — мы вообще не смогли найти/предложить такую деталь
  - "changed_mind" — клиент передумал чинить/деталь больше не нужна по
    причине, НЕ связанной ни с ценой, ни с наличием (продал машину, нашёл
    проблему в другом, отложил ремонт вообще, и т.п.)
  Если "declined", но ни одна причина явно не читается из переписки — всё
  равно выбери наиболее вероятную по контексту, не оставляй null.
- outcome_amount: фактическая сумма оплаты, если "bought" и сумма ясна из
  переписки (иначе null — не надо путать с quoted_price)
- notes: одна короткая фраза своими словами, что именно произошло (например:
  "клиент сказал что заберёт", "написал 'долго ждать'", "не отвечает третьи
  сутки", "сказал что уже отремонтировал в сервисе")

Верни ТОЛЬКО валидный JSON без markdown-разметки, без пояснений:
{
    "parts": [
        {
            "name": "...", "side": "...", "position": "...",
            "availability_answer": "...", "lead_time_days": null, "quoted_price": null,
            "outcome": "...", "decline_reason": null, "outcome_amount": null, "notes": "..."
        }
    ]
}
PROMPT;

        return $this->callAndParseJson([
            ['role' => 'user', 'content' => $prompt],
        ], maxTokens: 2048);
    }

    private function callAndParseJson(array $messages, int $maxTokens): ?array
    {
        try {
            $message = $this->client->messages->create(
                model: self::MODEL,
                maxTokens: $maxTokens,
                messages: $messages,
            );

            $jsonText = null;
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $jsonText = $block->text;
                    break;
                }
            }

            if (!$jsonText) {
                return null;
            }

            // На случай если модель всё равно обернёт в ```json ... ```
            $jsonText = preg_replace('/```json|```/i', '', $jsonText);

            $parsed = json_decode(trim($jsonText), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('ClaudeExtractionService: невалидный JSON от модели', ['raw' => $jsonText]);
                return null;
            }

            return $parsed;
        } catch (\Throwable $e) {
            Log::error('ClaudeExtractionService: ошибка запроса к Claude API — ' . $e->getMessage());
            return null;
        }
    }
}
