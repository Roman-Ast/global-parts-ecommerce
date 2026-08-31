<?php

namespace App\Console\Commands\Halyk;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Опрашивает статус модерации отправленных карточек (halyk:create-card,
 * status=submitted). MODERATION — в очереди, REJECT — ошибки в
 * характеристиках, DELETED — дубль/неверная категория, SUCCESS — на
 * витрине. Обновляет status/comment в halyk_created_cards.
 */
class HalykCheckCardStatusCommand extends Command
{
    protected $signature = 'halyk:check-card-status';

    protected $description = 'Опрашивает статус модерации отправленных карточек halyk:create-card';

    public function handle(HalykMarketClient $client): int
    {
        $rows = DB::table('halyk_created_cards')
            ->whereIn('status', ['submitted', 'moderation'])
            ->whereNotNull('halyk_product_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Нечего проверять — нет отправленных карточек в очереди.');
            return 0;
        }

        foreach ($rows as $row) {
            // Живой случай 2026-08-31: на ~2800 строк опроса сетевой сбой
            // на ОДНОЙ позиции (ConnectionException — таймаут/обрыв, не
            // просто плохой HTTP-статус, retry(throw:false) на это не
            // распространяется) ронял всю команду целиком, ни одна строка
            // после сбойной не успевала обновиться. Ловим тут же, на
            // конкретной позиции — остальные обрабатываются как ни в чём
            // не бывало.
            try {
                $result = $client->getDraftStatus($row->halyk_product_id);
            } catch (\Throwable $e) {
                $this->error("  ⨯ {$row->article} (id={$row->halyk_product_id}) — исключение: {$e->getMessage()}");
                continue;
            }

            if (!$result['ok']) {
                $this->error("  ⨯ {$row->article} (id={$row->halyk_product_id}) — HTTP {$result['status']}");
                continue;
            }

            // Статус лежит вложенным в productDraftResponse, comment — на
            // верхнем уровне ответа (проверено вживую 2026-08-22).
            $status = $result['body']['productDraftResponse']['status'] ?? 'unknown';
            $comment = $result['body']['comment'] ?? null;

            DB::table('halyk_created_cards')->where('id', $row->id)->update([
                'status'     => mb_strtolower($status),
                'comment'    => $comment,
                'updated_at' => now(),
            ]);

            $icon = match ($status) {
                'SUCCESS' => '✓',
                'REJECT', 'DELETED' => '⨯',
                default => '·',
            };
            $this->line("  {$icon} {$row->article} (id={$row->halyk_product_id}) — {$status}" . ($comment ? " — {$comment}" : ''));
        }

        return 0;
    }
}
