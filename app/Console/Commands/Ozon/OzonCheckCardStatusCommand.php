<?php

namespace App\Console\Commands\Ozon;

use App\Services\OzonClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Опрашивает /v1/product/import/info по всем карточкам со status=submitted
 * в ozon_created_cards и обновляет реальный итог — аналог
 * halyk:check-card-status. Проверено вживую 2026-09-03: обработка
 * асинхронная, статус "imported" устаканивается не мгновенно (у первой
 * тестовой карточки — примерно за 10 секунд после отправки).
 */
class OzonCheckCardStatusCommand extends Command
{
    protected $signature = 'ozon:check-card-status {--limit=100}';

    protected $description = 'Проверяет реальный статус отправленных на Ozon карточек (ozon_created_cards, status=submitted)';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');

        $rows = DB::table('ozon_created_cards')
            ->where('status', 'submitted')
            ->whereNotNull('ozon_task_id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Нечего проверять — нет карточек в статусе submitted.');
            return 0;
        }

        $this->info("Проверяем {$rows->count()} карточек...");

        foreach ($rows as $row) {
            try {
                $result = $client->importStatus((int) $row->ozon_task_id);
            } catch (\Throwable $e) {
                $this->error("  ⨯ {$row->article}: {$e->getMessage()}");
                continue;
            }

            $status = $result['status'] ?? 'unknown';
            $productId = $result['product_id'] ?? null;
            $errors = $result['errors'] ?? [];

            $newStatus = match (true) {
                $status === 'imported' => 'imported',
                !empty($errors) => 'failed',
                default => $status,
            };

            DB::table('ozon_created_cards')->where('id', $row->id)->update([
                'status' => $newStatus,
                'ozon_product_id' => $productId,
                'comment' => !empty($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => now(),
            ]);

            $this->line("  {$row->article}: {$newStatus}" . ($productId ? " (product_id={$productId})" : ''));
        }

        return 0;
    }
}
