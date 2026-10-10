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

/**
 * Cron через HTTP для дренажа БД-очереди `ExtractLeadRequestJob` (просьба
 * Романа 2026-10-06) — та же причина, что и у `/cron/whatsapp-recover` выше:
 * на этом тарифе Plesk нет SSH, поэтому нельзя держать `php artisan
 * queue:work` постоянным демоном (так задача и задумывалась с самого начала,
 * см. докблок `ExtractLeadRequestJob` — там это было только описано, сам
 * HTTP-триггер не был реализован). Без этого роута `WHATSAPP_LLM_EXTRACTION_
 * ENABLED=true` на проде просто копил бы задачи в таблице `jobs` без единого
 * обработчика — очередь росла бы бесконечно, ничего не разбиралось бы.
 *
 * `--stop-when-empty` — не висит, если очередь уже пуста; `--max-time=50` —
 * тот же безопасный запас под 60-секундный таймаут nginx/PHP-FPM на этом
 * хостинге, что и везде в этом файле. Токен — тот же `WHATSAPP_CRON_TOKEN`,
 * что и у `/cron/whatsapp-recover` (общий внутренний cron-секрет, не отдельный
 * под каждый роут).
 */
Route::get('/cron/queue-work', function () {
    $token = request()->query('token', '');
    $expected = config('services.whatsapp_cron_token');

    if (!$expected || !hash_equals($expected, $token)) {
        abort(403);
    }

    // --max-time проверяется только МЕЖДУ задачами: задача, начатая на 49-й
    // секунде, спокойно работала дальше и уходила за 60-сек лимит хостинга
    // (500 + MaxAttemptsExceededException, 2026-10-10). Худший случай одной
    // задачи — скачивание вложения (15 сек) + Claude (25 сек) ≈ 40 сек,
    // поэтому новые задачи берём только первые 15 сек запуска.
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 15,
    ]);

    return response()->json([
        'ok' => true,
        'ran_at' => now()->toDateTimeString(),
        'result' => trim(Artisan::output()),
    ]);
});
