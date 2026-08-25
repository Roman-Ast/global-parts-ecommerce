<?php

namespace App\Console\Commands\Halyk;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;

/**
 * Опрашивает статус обработки фида, загруженного через halyk:upload-feed.
 * С --wait ждёт, пока статус не станет терминальным (COMPLETED/FAILED/
 * SKIPPED), опрашивая раз в 10 сек — обработка 90к+ позиций явно небыстрая,
 * не рассчитываем на мгновенный ответ.
 */
class HalykCheckFeedStatusCommand extends Command
{
    protected $signature = 'halyk:check-feed-status {id : id загрузки из halyk:upload-feed} {--wait : ждать терминального статуса}';

    protected $description = 'Опрашивает статус обработки загруженного фида Halyk';

    public function handle(HalykMarketClient $client): int
    {
        $uploadId = (int) $this->argument('id');
        $terminal = ['COMPLETED', 'FAILED', 'SKIPPED'];

        while (true) {
            $result = $client->getFeedUploadStatus($uploadId);

            if (!$result['ok']) {
                $this->error('HTTP ' . $result['status'] . ' — ' . json_encode($result['body'], JSON_UNESCAPED_UNICODE));
                return 1;
            }

            $body = $result['body'];
            $status = $body['status'] ?? 'unknown';

            $this->info("status={$status} total=" . ($body['totalCount'] ?? '?')
                . ' success=' . ($body['successCount'] ?? '?')
                . ' notMapped=' . ($body['notMappedCount'] ?? '?')
                . ' fail=' . ($body['failCount'] ?? '?')
                . ' message=' . ($body['message'] ?? ''));

            if (!$this->option('wait') || in_array($status, $terminal, true)) {
                if (in_array($status, $terminal, true)) {
                    $this->line('Полный ответ: ' . json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                return 0;
            }

            sleep(10);
        }
    }
}
