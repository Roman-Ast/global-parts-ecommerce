<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\LeadRequestExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Асинхронная обёртка над LeadRequestExtractor::extract() для ЖИВОГО
 * пути (WhatsappMessageObserver) — просьба Романа 2026-10-05, когда
 * включаем WHATSAPP_LLM_EXTRACTION_ENABLED на проде. Раньше observer
 * вызывал extract() СИНХРОННО прямо внутри обработки вебхука — каждое
 * новое сообщение ждало бы ответа Claude (а вложение — отдельный vision-
 * запрос, может занять не одну секунду) прямо во время HTTP-ответа на
 * вебхук Green API. На этом хостинге (nginx ~60 сек таймаут, та же
 * причина 504 на обычном поиске и на демонстрационном анализе спроса)
 * это риск: не только падает ответ на сам вебхук, но и, если Green API
 * ждёт быстрый 200 OK, он может начать ретраить/дублировать доставку.
 *
 * Теперь observer кладёт задачу в БД-очередь (QUEUE_CONNECTION=database,
 * без Redis — таблица jobs уже есть) и мгновенно возвращает управление;
 * реальный разбор Claude происходит отдельно, когда очередь обработает
 * задачу. На проде НЕТ постоянного воркера (нет SSH, нельзя держать
 * `queue:work` демоном) — обработку запускает Plesk "Планировщик задач"
 * раз в несколько минут командой `php artisan queue:work --stop-when-
 * empty --max-time=50` (укладывается в тот же безопасный запас по
 * времени, что и везде на этом хостинге).
 *
 * `--tries=1` на самой задаче (см. $tries ниже) — НЕ ретраим упавшую
 * задачу автоматически (ошибка Claude на одном сообщении не должна
 * пытаться бесконечно тратить API-вызовы) — упавшие уходят в
 * failed_jobs, разобрать вручную при необходимости.
 */
class ExtractLeadRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // tries=2 + maxExceptions=1 (2026-10-10): если сама задача бросила
    // исключение (ошибка Claude) — в failed_jobs сразу, без повтора, как и
    // раньше. Но если процесс УБИЛИ посреди работы (таймаут HTTP-крона), это
    // не исключение — задача остаётся «занятой» и после retry_after (90 сек)
    // подхватывается заново; с tries=1 такой подхват сразу давал
    // MaxAttemptsExceededException вместо второй попытки.
    public int $tries = 2;

    public int $maxExceptions = 1;

    public function __construct(public int $messageId)
    {
    }

    public function handle(LeadRequestExtractor $extractor): void
    {
        $message = WhatsappMessage::find($this->messageId);

        if (!$message) {
            return;
        }

        $extractor->extract($message);
    }
}
