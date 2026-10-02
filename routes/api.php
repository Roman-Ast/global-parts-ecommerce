<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

// Наш главный роут для приема сообщений
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

/**
 * Cron через HTTP (просьба Романа 2026-10-02) — на его тарифе Plesk нет SSH,
 * из-за чего недоступны оба штатных способа: ни встроенный Laravel-шедулер
 * (Laravel Toolkit прямо пишет "требуется доступ к серверу по SSH"), ни
 * обычный "Планировщик задач" с типом "Выполнить команду" (падает что с
 * "php artisan ...", что без — похоже на урезанную/chroot-оболочку без
 * доступа к PHP-бинарю проекта). Единственный тип задачи, который там же
 * реально работает на таком тарифе — "Получить URL-адрес" (обычный HTTP GET
 * по расписанию) — отсюда и этот роут: вызывается ИЗ Plesk-cron'а дважды в
 * день, сам внутри себя вызывает whatsapp:recover-history (обход бага
 * receiveNotification Green API, см. докблок команды) на оба инстанса.
 *
 * Защищено токеном в query (не Laravel auth — у Plesk cron нет сессии/логина),
 * сравнение hash_equals() против timing-атак. Токен — WHATSAPP_CRON_TOKEN в
 * .env, не в коде.
 */
Route::get('/cron/whatsapp-recover', function () {
    $token = request()->query('token', '');
    $expected = config('services.whatsapp_cron_token');

    if (!$expected || !hash_equals($expected, $token)) {
        abort(403);
    }

    $results = [];
    foreach (['site', '2gis'] as $source) {
        Artisan::call('whatsapp:recover-history', [
            '--since' => '24 hours ago',
            '--source' => $source,
        ]);
        $results[$source] = trim(Artisan::output());
    }

    return response()->json(['ok' => true, 'ran_at' => now()->toDateTimeString(), 'results' => $results]);
});
