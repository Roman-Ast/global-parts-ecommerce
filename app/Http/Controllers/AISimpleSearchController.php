<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AISimpleSearchController extends Controller
{
    public function searchArticlesByGPT(Request $request)
    {
        $query = $request->data;

        $apiKey = env('OPENAI_API_KEY');
        $url = 'https://api.openai.com/v1/chat/completions';

        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Ты — эксперт по подбору автозапчастей. 
                        Пользователь вводит марку, модель, год выпуска и деталь. 
                        Верни список оригинальных номеров (OEM) и популярных аналогов с брендами. 
                        Не проси VIN, не упоминай про VIN. 
                        Структурируй ответ красиво: сначала название авто, потом список OEM номеров, потом список популярных аналогов с брендами. 
                        В конце ответа всегда добавляй примечание: 
                        'Для уточнения совместимости и подбора вы можете обратиться к специалисту нашего магазина или менеджеру отдела продаж.'"
                ],
                [
                    'role' => 'user',
                    'content' => $query
                ]
            ],
            'temperature' => 0.2
        ];


        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    "Authorization: Bearer $apiKey"
                ],
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return json_encode($result);
    }

    public function searchArticlesByGPTWithVin(Request $request)
{
    $apiKey = env('OPENAI_API_KEY');
    $url = 'https://api.openai.com/v1/chat/completions';

    $vin  = $request->input('data.VIN');
    $part = $request->input('data.part');

    if (!$vin || !$part) {
        return response()->json(['error' => 'VIN и запчасть обязательны'], 422);
    }

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => "Ты — эксперт по подбору автозапчастей.
                По VIN-коду {$vin} подбери оригинальные номера и качественные аналоги для запчасти: {$part}.
                Структура ответа:
                1. Название авто (модель, год, двигатель если доступно).
                2. OEM номера (оригинальные).
                3. Популярные аналоги (бренд + номер).
                В конце всегда добавляй примечание:
                'Для уточнения совместимости и подбора вы можете обратиться к специалисту нашего магазина.'"
            ],
            [
                'role' => 'user',
                'content' => "VIN: {$vin}, деталь: {$part}"
            ]
        ],
        'temperature' => 0.2,
        'max_tokens' => 800
    ];

    try {
        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$apiKey}"
                ],
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 25
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            \Log::error("Ошибка API OpenAI VIN: {$vin}, part: {$part}");
            return response()->json(['error' => 'Ошибка при запросе к OpenAI'], 500);
        }

        $response = json_decode($result, true);

        if (!isset($response['choices'][0]['message']['content'])) {
            \Log::warning("Пустой ответ от OpenAI VIN: {$vin}, part: {$part}");
            return response()->json(['answer' => 'Нет данных. Попробуйте снова или уточните запрос.']);
        }

        return response()->json([
            'answer' => $response['choices'][0]['message']['content']
        ]);

    } catch (\Exception $e) {
        \Log::error("Исключение OpenAI API: {$e->getMessage()} VIN: {$vin}, part: {$part}");
        return response()->json(['error' => 'Внутренняя ошибка сервера'], 500);
    }
}

}
