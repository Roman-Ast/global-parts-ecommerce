<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Бэкфилл остатков для карточек, у которых status=imported в
 * ozon_created_cards, но остаток так и не передавался — это все карточки,
 * созданные ДО 2026-09-15 (когда склад Ozon "Global_Parts_PP1" ещё был
 * недоступен, WAREHOUSE_WRONG_STATUS, см. CLAUDE.md) плюс до того, как
 * ozon:check-card-status стал пушить остаток автоматически при переходе
 * в imported (2026-09-14). С этого момента новые карточки остаток уже
 * получают сами — эта команда закрывает только исторический бэклог.
 */
class OzonPushStockCommand extends Command
{
    protected $signature = 'ozon:push-stock {--limit=1000}';

    protected $description = 'Бэкфилл остатков на Ozon для уже созданных карточек (ozon_created_cards, status=imported)';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $warehouseId = (int) env('OZON_WAREHOUSE_ID');

        if (!$warehouseId) {
            $this->error('OZON_WAREHOUSE_ID не задан в .env');
            return 1;
        }

        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Нечего пушить — нет карточек в статусе imported.');
            return 0;
        }

        $articles = $rows->pluck('article')->all();
        $cards = PartsCatalog::query()->whereIn('article', $articles)->get();
        $cards = (new SupplierOfferPricer())->attach($cards);
        $byArticleBrand = $cards->keyBy(fn ($c) => $c->article . '|' . $c->brand);

        $this->info("Пушим остаток для {$rows->count()} карточек...");

        $ok = 0;
        $noStock = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $card = $byArticleBrand->get($row->article . '|' . $row->brand);
            $stock = $card->offer['stock'] ?? 0;

            if ($stock <= 0) {
                $noStock++;
                continue;
            }

            $offerId = mb_substr("{$row->brand}-{$row->article}", 0, 50);

            try {
                $result = $client->updateStock($offerId, $stock, $warehouseId);
                if ($result['updated']) {
                    $ok++;
                    $this->line("  ✓ {$offerId}: остаток {$stock}");
                } else {
                    $failed++;
                    $this->line("  ⨯ {$offerId}: " . ($result['errors'][0]['code'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ⨯ {$offerId}: {$e->getMessage()}");
            }
        }

        $this->info("Готово: ок={$ok}, нет остатка у поставщика={$noStock}, ошибка={$failed}");

        return 0;
    }
}
