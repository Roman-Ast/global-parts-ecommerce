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

        $vin  = $request->data['VIN'];
        $part = $request->data['part'];

        //return [$vin, $part];

        if (!$vin || !$part) {
            return response()->json(['error' => 'VIN и запчасть обязательны'], 422);
        }

           $data = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Ты — эксперт по подбору автозапчастей. 
                        По VIN-коду {$vin} подбери оригинальные номера и аналоги для запчасти: {$part}. 
                        Структурируй ответ красиво: сначала название авто, потом список OEM номеров, потом список популярных аналогов с брендами. 
                        В конце ответа всегда добавляй примечание: 
                        'Для уточнения совместимости и подбора вы можете обратиться к специалисту нашего магазина или менеджеру отдела продаж.'"
                    ],
                    [
                        'role' => 'user',
                        'content' => "VIN: {$vin}, деталь: {$part}"
                    ]
                ],
                'temperature' => 0.2
            ];

        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$apiKey}"
                ],
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 20
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            return response()->json(['error' => 'Ошибка при запросе к OpenAI'], 500);
        }

        $response = json_decode($result, true);

        return response()->json([
            'answer' => $response['choices'][0]['message']['content'] ?? 'Нет ответа от ИИ'
        ]);
    }
}
