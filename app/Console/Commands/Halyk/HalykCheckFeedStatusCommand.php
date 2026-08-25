<?php

namespace App\Console\Commands\Halyk;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;

/**
 * Опрашивает статус обработки фида, загруженного через halyk:upload-feed.
 * С --wait ждёт терминального состояния, опрашивая раз в 10 сек — обработка
 * 90к+ позиций явно небыстрая, не рассчитываем на мгновенный ответ.
 *
 * ИСПРАВЛЕНО 2026-08-25 (живой прогон): терминальность определяется по
 * MESSAGE (PROCESSING → COMPLETED/FAILED), не по STATUS. Их дока называет
 * `status` терминальными значениями COMPLETED/FAILED/SKIPPED, но реальный
 * ответ на полностью обработанный фид содержит `status:
 * "UPLOADED_WITH_ERRORS"` (конкретный исход) + `message: "COMPLETED"`
 * (стадия обработки) — старая версия ждала COMPLETED именно в status,
 * никогда не находила, зациклилась на ~78 одинаковых опросах и в итоге
 * упала по случайному сетевому таймауту вместо честного выхода.
 */
class HalykCheckFeedStatusCommand extends Command
{
    protected $signature = 'halyk:check-feed-status {id : id загрузки из halyk:upload-feed} {--wait : ждать терминального статуса}';

    protected $description = 'Опрашивает статус обработки загруженного фида Halyk';

    public function handle(HalykMarketClient $client): int
    {
        $uploadId = (int) $this->argument('id');
        $terminalMessages = ['COMPLETED', 'FAILED', 'SKIPPED'];

        while (true) {
            $result = $client->getFeedUploadStatus($uploadId);

            if (!$result['ok']) {
                $this->error('HTTP ' . $result['status'] . ' — ' . json_encode($result['body'], JSON_UNESCAPED_UNICODE));
                return 1;
            }

            $body = $result['body'];
            $status = $body['status'] ?? 'unknown';
            $message = $body['message'] ?? 'unknown';

            $this->info("status={$status} message={$message} total=" . ($body['totalCount'] ?? '?')
                . ' success=' . ($body['successCount'] ?? '?')
                . ' notMapped=' . ($body['notMappedCount'] ?? '?')
                . ' fail=' . ($body['failCount'] ?? '?'));

            $isTerminal = in_array($message, $terminalMessages, true);

            if (!$this->option('wait') || $isTerminal) {
                if ($isTerminal) {
                    $this->line('Полный ответ: ' . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                return 0;
            }

            sleep(10);
        }
    }
}
