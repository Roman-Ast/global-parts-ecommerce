<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Опрашивает /v1/product/import/info по всем карточкам со status=submitted
 * в ozon_created_cards и обновляет реальный итог — аналог
 * halyk:check-card-status. Проверено вживую 2026-09-03: обработка
 * асинхронная, статус "imported" устаканивается не мгновенно (у первой
 * тестовой карточки — примерно за 10 секунд после отправки).
 *
 * С 2026-09-14 — прямо здесь же, как только карточка устаканилась в
 * "imported", сразу передаём остаток (`OzonClient::updateStock()`), а не
 * ждём отдельного ручного `ozon:push-stock` — без остатка карточка виснет
 * в "Готов к продаже" и никогда не становится "Продаётся" (см. разбор
 * склада Ozon в CLAUDE.md, найдено и починено в этот же день).
 */
class OzonCheckCardStatusCommand extends Command
{
    protected $signature = 'ozon:check-card-status {--limit=100}';

    protected $description = 'Проверяет реальный статус отправленных на Ozon карточек (ozon_created_cards, status=submitted)';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $warehouseId = (int) env('OZON_WAREHOUSE_ID');

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

        $justImported = [];

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

            if ($newStatus === 'imported') {
                $justImported[] = $row;
            }
        }

        if (!empty($justImported) && $warehouseId) {
            $this->pushStockForJustImported($client, $justImported, $warehouseId);
        } elseif (!empty($justImported)) {
            $this->warn('OZON_WAREHOUSE_ID не задан — остаток для новых карточек не передан, прогони ozon:push-stock отдельно.');
        }

        return 0;
    }

    /** Сразу передаёт остаток для карточек, которые только что устаканились в imported — без отдельного ручного шага. */
    private function pushStockForJustImported(OzonClient $client, array $rows, int $warehouseId): void
    {
        $articles = array_column($rows, 'article');

        $cards = PartsCatalog::query()->whereIn('article', $articles)->get();
        $cards = (new SupplierOfferPricer())->attach($cards);
        $byArticleBrand = $cards->keyBy(fn ($c) => $c->article . '|' . $c->brand);

        $this->info('Передаём остаток для только что импортированных карточек...');

        foreach ($rows as $row) {
            $card = $byArticleBrand->get($row->article . '|' . $row->brand);
            $stock = $card->offer['stock'] ?? 0;

            if ($stock <= 0) {
                $this->line("  {$row->brand}-{$row->article}: нет остатка у поставщика сейчас — пропуск");
                continue;
            }

            $offerId = mb_substr("{$row->brand}-{$row->article}", 0, 50);

            try {
                $result = $client->updateStock($offerId, $stock, $warehouseId);
                $this->line($result['updated']
                    ? "  {$offerId}: остаток {$stock} передан"
                    : "  ⨯ {$offerId}: не обновилось (" . ($result['errors'][0]['code'] ?? 'unknown') . ')');
            } catch (\Throwable $e) {
                $this->error("  ⨯ {$offerId}: {$e->getMessage()}");
            }
        }
    }
}
