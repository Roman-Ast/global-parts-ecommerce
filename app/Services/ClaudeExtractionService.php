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
    // Модель РАЗНАЯ для двух разных по характеру задач (разделено 2026-10-03,
    // после живого теста извлечения на haiku — "56 вызовов, без единого сбоя"):
    //
    // - EXTRACTION_MODEL (parseRequest/parseAttachment) — haiku. Открытый
    //   текст (VIN/марка/модель/год/список запчастей), нет закрытого списка
    //   значений, который можно нарушить — ровно тот типа задачи, где
    //   дешёвая модель не подводила ни разу за весь день тестов.
    // - ANALYSIS_MODEL (analyzeOutcome) — sonnet. Тут был реальный провал
    //   haiku на живом 50-лидовом прогоне в тот же день: на пограничных
    //   случаях (деталь, которую клиент не запрашивал явно; неоднозначный
    //   контекст) примерно в 1 из 5 заявок вместо строго одного из
    //   bought/declined/silent/pending выдумывала "unknown" — значения,
    //   которого нет в закрытом списке, даже при том что промпт прямым
    //   текстом его требует (защита от падения есть в VALID_OUTCOMES в
    //   AnalyzeDemandSignalsCommand, но подмена на дефолт 'pending' — это
    //   потеря данных, не настоящий вердикт). Сам этот метод сейчас не
    //   вызывается в бою (анализ исхода перешёл на ручной разбор раз в
    //   месяц, см. ImportAnalyzedDemandCommand) — константа оставлена
    //   корректной на случай, если когда-нибудь вернём его через API.
    private const EXTRACTION_MODEL = 'claude-haiku-4-5-20251001';
    private const ANALYSIS_MODEL = 'claude-sonnet-5';

    private Client $client;

    // Счётчик токенов за текущий прогон (просьба Романа 2026-10-03 — "заодно
    // прикинь сколько денег это обходится" при пробном бэкфилле). Статик, не
    // per-instance — сервис создаётся по новой на каждый вызов через DI,
    // инстанс-поле обнулялось бы между сообщениями. Сбрасывается явно вызовом
    // resetUsageTotals() в начале прогона (см. BackfillDemandDataCommand).
    private static int $totalInputTokens = 0;
    private static int $totalOutputTokens = 0;
    private static int $totalCalls = 0;

    // Причина последнего провала callAndParseJson() (просьба Романа
    // 2026-10-03 — "Claude не вернул вердикт" в консоли без деталей
    // заставляло лезть в лог на проде через Plesk за каждой причиной).
    // Вызывающая сторона (AnalyzeDemandSignalsCommand) читает это сразу
    // после null-результата и печатает прямо в свой вывод.
    private static ?string $lastError = null;

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.api_key'));
    }

    public static function resetUsageTotals(): void
    {
        self::$totalInputTokens = 0;
        self::$totalOutputTokens = 0;
        self::$totalCalls = 0;
    }

    /** @return array{calls: int, input_tokens: int, output_tokens: int} */
    public static function getUsageTotals(): array
    {
        return [
            'calls' => self::$totalCalls,
            'input_tokens' => self::$totalInputTokens,
            'output_tokens' => self::$totalOutputTokens,
        ];
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
        ], maxTokens: 512, model: self::EXTRACTION_MODEL);

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

        // Тип определяем по СОДЕРЖИМОМУ файла, не по расширению. Раньше всё,
        // что не .pdf, слалось как image/jpeg — на проде (лог 2026-10-03..07)
        // это ~1000 отказов 400: голосовые .oga/видео .mp4/.xlsx ("Could not
        // process image") и webp под видом jpg ("appears to be a image/webp").
        // Неподдерживаемые типы просто пропускаем без платного вызова.
        $body = $fileResponse->body();
        $mediaType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        $supported = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($mediaType, $supported, true)) {
            return null;
        }
        // Лимит API — 10 МБ на base64 (живой случай: фото 16.9 МБ → 400).
        if (strlen($body) * 4 / 3 > 10 * 1024 * 1024) {
            Log::warning('ClaudeExtractionService: вложение слишком большое, пропущено', ['url' => $fileUrl, 'bytes' => strlen($body)]);
            return null;
        }
        $blockType = $mediaType === 'application/pdf' ? 'document' : 'image';
        $base64 = base64_encode($body);

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
        ], maxTokens: 256, model: self::EXTRACTION_MODEL);

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

        // НАЙДЕНА настоящая причина серии провалов 2026-10-03 (не длина
        // транскрипта, как предполагалось сначала) — диагностика состава
        // блоков показала "типы: [thinking:?]", весь maxTokens уходил в
        // extended thinking, ни одного символа JSON. thinking теперь явно
        // отключён в callAndParseJson() — 4096 снова с запасом (живой тест
        // на 15 деталях укладывался в 2233 токена).
        return $this->callAndParseJson([
            ['role' => 'user', 'content' => $prompt],
        ], maxTokens: 4096, model: self::ANALYSIS_MODEL);
    }

    private function callAndParseJson(array $messages, int $maxTokens, string $model): ?array
    {
        try {
            $message = $this->client->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                messages: $messages,
                // Живой случай 2026-10-03: без явного отключения sonnet-5
                // иногда уходит в extended thinking и сжирает ВЕСЬ maxTokens
                // на размышление, не оставляя ничего на сам JSON-ответ
                // (подтверждено диагностикой — "блоков в ответе: 1, типы:
                // [thinking:?]", output_tokens=8192 и ни одного символа
                // текста). Нам тут нужен быстрый структурированный JSON, не
                // рассуждения — отключаем совсем, а не просто поднимаем лимит.
                thinking: ['type' => 'disabled'],
            );

            self::$totalCalls++;
            self::$totalInputTokens += $message->usage->inputTokens ?? 0;
            self::$totalOutputTokens += $message->usage->outputTokens ?? 0;

            $jsonText = null;
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $jsonText = $block->text;
                    break;
                }
            }

            if (!$jsonText) {
                $blockTypes = array_map(fn ($b) => $b->type . ':' . (isset($b->text) ? mb_strlen($b->text) . 'симв' : '?'), $message->content);
                self::$lastError = 'Пустой ответ от модели (ни одного text-блока), stop_reason=' . ($message->stopReason ?? '?')
                    . ', блоков в ответе: ' . count($message->content)
                    . ', типы: [' . implode(', ', $blockTypes) . ']'
                    . ', output_tokens=' . ($message->usage->outputTokens ?? '?');
                return null;
            }

            // На случай если модель всё равно обернёт в ```json ... ```
            $jsonText = preg_replace('/```json|```/i', '', $jsonText);

            $parsed = json_decode(trim($jsonText), true);

            // Живой случай 2026-10-03 (stop_reason=end_turn, т.е. модель
            // честно закончила, но json_decode всё равно падает с "Syntax
            // error"/"Control character error") — модель иногда копирует
            // перенос строки или другой управляющий символ из исходной
            // переписки клиента ПРЯМО в значение поля (напр. notes),
            // забывая его экранировать как \n — строгий json_decode такое
            // не прощает. Сырые control-символы ВНУТРИ JSON-строки всегда
            // невалидны по спеке (уже экранированные \n — это два обычных
            // символа, их не затронет), так что просто вычищаем и пробуем
            // распарсить повторно, прежде чем сдаваться.
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonText);
                $retryParsed = json_decode(trim($cleaned), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $retryParsed;
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                // stop_reason=max_tokens — явный признак того, что ответ
                // обрезан на середине JSON (у длинных заявок со многими
                // деталями список легко не влезает в лимит) — отдельно
                // помечаем, это решается увеличением maxTokens, а не чем-то
                // в самих данных.
                $stopReason = $message->stopReason ?? '?';
                $hint = $stopReason === 'max_tokens' ? ' (похоже, ответ обрезан — упёрлись в maxTokens)' : '';
                // Было mb_substr(-300) — хвост почти всегда выглядит валидным,
                // реальная синтаксическая ошибка может быть где угодно внутри
                // длинного ответа (живой случай 2026-10-03: хвост чист, а
                // json_decode всё равно падает). Показываем куда больше —
                // проще один раз увидеть весь ответ и найти место глазами,
                // чем гадать по обрезку.
                self::$lastError = "Невалидный JSON от модели ({$stopReason}{$hint}): " . json_last_error_msg()
                    . ' — длина ответа: ' . mb_strlen($jsonText) . ' символов'
                    . ' — ПОЛНЫЙ ОТВЕТ:' . PHP_EOL . $jsonText;
                Log::warning('ClaudeExtractionService: невалидный JSON от модели', ['raw' => $jsonText, 'stop_reason' => $stopReason]);
                return null;
            }

            return $parsed;
        } catch (\Throwable $e) {
            self::$lastError = get_class($e) . ': ' . $e->getMessage();
            Log::error('ClaudeExtractionService: ошибка запроса к Claude API — ' . $e->getMessage());
            return null;
        }
    }
}
