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
 *
 * ИЗМЕНЕНО 2026-10-03 — живьём поймано 504 Gateway Time-out (nginx) дважды
 * подряд: один HTTP-запрос гонял recover-history на ОБА инстанса сразу
 * (site + 2gis), а у 2gis трафик заметно больше (10549 чатов против 8790
 * у site, живьём видно по консоли Green API) — суммарно превышало таймаут
 * nginx/PHP-FPM на этом тарифе. Теперь `source` — обязательный query-
 * параметр, один вызов = один инстанс; под это нужны ДВЕ отдельные задачи в
 * Plesk-планировщике (разные URL), не одна.
 */
Route::get('/cron/whatsapp-recover', function () {
    $token = request()->query('token', '');
    $expected = config('services.whatsapp_cron_token');

    if (!$expected || !hash_equals($expected, $token)) {
        abort(403);
    }

    $source = request()->query('source');
    if (!in_array($source, ['site', '2gis'], true)) {
        return response()->json(['ok' => false, 'error' => 'missing or invalid source= (ожидается site или 2gis)'], 400);
    }

    Artisan::call('whatsapp:recover-history', [
        '--since' => '24 hours ago',
        '--source' => $source,
    ]);

    return response()->json([
        'ok' => true,
        'ran_at' => now()->toDateTimeString(),
        'source' => $source,
        'result' => trim(Artisan::output()),
    ]);
});
