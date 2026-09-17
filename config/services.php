<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'green_api' => [
        // Оставлены для обратной совместимости — раньше это были единственные
        // креды. Новый код должен брать пару instance_id/token через
        // instances.{source}, не отсюда напрямую.
        'instance_id' => env('GREEN_API_INSTANCE_ID'),
        'token' => env('GREEN_API_TOKEN'),

        // source-метка => креды конкретного инстанса. Для ИСХОДЯЩИХ сообщений
        // (WhatsappMessenger::sendMessage) — иначе ответ клиенту с номера 2ГИС
        // ушёл бы с номера сайта или вообще не тем инстансом.
        'instances' => [
            'site' => [
                'instance_id' => env('GREEN_API_INSTANCE_ID'),
                'token' => env('GREEN_API_TOKEN'),
            ],
            '2gis' => [
                'instance_id' => env('GREEN_API_INSTANCE_ID_2GIS'),
                'token' => env('GREEN_API_TOKEN_2GIS'),
            ],
        ],

        // instanceId => source-метка для whatsapp_leads.source. Раньше это было
        // захардкожено прямо в WhatsAppWebhookController как тернарник "если этот
        // конкретный ID — site, иначе — 2gis"; вынесено сюда, чтобы подключение
        // второго номера (2ГИС) было просто новой строкой здесь, без правки кода.
        'instance_sources' => [
            env('GREEN_API_INSTANCE_ID') => 'site',
            env('GREEN_API_INSTANCE_ID_2GIS') => '2gis',
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Явный рубильник (не просто "ключ пустой") — см. WhatsappMessageObserver
        // и .env. По умолчанию выключено: копим сырые сообщения бесплатно,
        // пока Роман вручную не выведет таксономию причин отказа из реальных чатов.
        'whatsapp_extraction_enabled' => (bool) env('WHATSAPP_LLM_EXTRACTION_ENABLED', false),
    ],

];
