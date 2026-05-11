<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    protected $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
    }

    
    public function parseRequest($text)
    {
        $prompt = "Ты — эксперт по автозапчастям. Извлеки данные из сообщения клиента.

    Верни ТОЛЬКО валидный JSON без markdown-разметки, без пояснений, без текста вне скобок:
    {
        \"vin\": \"17-значный VIN или frame-номер если есть, иначе null\",
        \"car_model\": \"марка и модель автомобиля если есть, иначе null\",
        \"parts\": [
            {
                \"name\": \"название запчасти\",
                \"side\": \"left / right / null\",
                \"position\": \"front / rear / upper / lower / null\"
            }
        ]
    }

    Сообщение клиента: " . $text;

        try {
            $response = Http::timeout(20)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [['text' => $prompt]]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0,
                    'maxOutputTokens' => 512,
                ]
            ]);

            if ($response->successful()) {
                $data     = $response->json();
                $jsonText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if (!$jsonText) return null;

                // Gemini иногда всё равно оборачивает в ```json ... ```
                $jsonText = preg_replace('/```json|```/i', '', $jsonText);

                $parsed = json_decode(trim($jsonText), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Gemini parseRequest: невалидный JSON', ['raw' => $jsonText]);
                    return null;
                }

                return $parsed;
            }

            Log::error('Gemini parseRequest HTTP error', ['status' => $response->status()]);

        } catch (\Exception $e) {
            Log::error('Gemini API Error: ' . $e->getMessage());
        }

        return null;
    }

    public function parseFileForVin($fileUrl)
    {
        try {
            $fileResponse = Http::timeout(15)->get($fileUrl);
            if (!$fileResponse->successful()) {
                Log::warning('Gemini: не удалось скачать файл', ['url' => $fileUrl]);
                return null;
            }

            $fileData = base64_encode($fileResponse->body());
            $extension = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mimeType = ($extension === 'pdf') ? 'application/pdf' : 'image/jpeg';

            $prompt = "Ты — ассистент по распознаванию автодокументов.

    Найди в документе идентификационный номер транспортного средства. Возможны два формата:

    1. VIN-код — ровно 17 символов (латиница A-Z и цифры 0-9, буквы I, O, Q не используются).
    Где искать: поле «VIN», «Идентификационный номер», пункт №5 в казахстанских техпаспортах,
    «Идентификационный номер» / «Шасси №» в российских СРТС.

    2. Frame-номер (японские автомобили) — от 7 до 10 символов (латиница и цифры), 
    часто содержит дефис, например: «EF7-1234567» или «GD1-1234567».
    Где искать: поле «Frame No.», «Номер шасси», «Шасси №».

    Правила ответа:
    - Если нашёл VIN (17 символов) — верни ТОЛЬКО его, без пробелов и дефисов.
    - Если нашёл Frame-номер — верни его КАК ЕСТЬ, сохраняя дефис если он есть.
    - Если не нашёл ничего — верни строго слово: null
    - Никаких пояснений, никаких лишних символов.";

            $response = Http::timeout(30)->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data'      => $fileData,
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0,   // детерминированный ответ
                    'maxOutputTokens' => 32,  // нам нужно максимум ~20 символов
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Gemini: HTTP ошибка', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $res  = $response->json();
            $text = trim($res['candidates'][0]['content']['parts'][0]['text'] ?? '');

            Log::info('Gemini raw response', ['text' => $text]);

            if (empty($text) || strtolower($text) === 'null') {
                return null;
            }

            // VIN: строго 17 символов, без I/O/Q
            if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $text, $m)) {
                return strtoupper($m[1]);
            }

            // Frame-номер: буквы+цифры с необязательным дефисом, 7–12 символов
            if (preg_match('/\b([A-Z0-9]{2,6}-[A-Z0-9]{4,8})\b/i', $text, $m)) {
                return strtoupper($m[1]);
            }

            // Frame без дефиса: 7–10 символов
            if (preg_match('/\b([A-Z0-9]{7,10})\b/i', $text, $m)) {
                return strtoupper($m[1]);
            }

            Log::warning('Gemini: ответ получен, но не распознан', ['text' => $text]);
            return null;

        } catch (\Exception $e) {
            Log::error('Gemini Error: ' . $e->getMessage());
            return null;
        }
    }
}