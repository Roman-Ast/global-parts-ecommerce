<?php

namespace App\Console\Commands;

use App\Helpers\SlugHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Недостающее звено между kaspi:parse-sku-raw (широкий, "не для продажи"
 * поиск kaspi_sku по supplier_offers_raw — см. докстринг того файла) и
 * реальным наполнением parts_catalog/GSC.
 *
 * Раньше это звено отсутствовало: SeedOwnPartsCatalogCommand
 * (parts:seed-own) читает только kaspi_feed_items (bound=1) — это
 * ПРОДАЖНАЯ ветка (сначала kaspi:match/kaspi:sync-feed/kaspi:bind-products),
 * до которой артикулы из supplier_offers_raw в принципе никогда не
 * дойдут — ParseKaspiSkuRawCommand намеренно исключает всё, что уже есть
 * в kaspi_initial_products (whereNotExists), а kaspi:match join'ится
 * ИМЕННО по kaspi_initial_products.sku, не по kaspi_sku_test напрямую.
 * Проверено 2026-09-01: раньше в докстринге ParseKaspiSkuRawCommand было
 * написано, что kaspi:match "подхватит независимо от источника" — это
 * не так, эта команда и есть настоящее продолжение цепочки.
 */
class SeedRawPartsCatalogCommand extends Command
{
    protected $signature = 'parts:seed-raw {--fresh : удалить старые raw-записи перед посевом} {--limit= : ограничить количество (для тестового прогона)}';
    protected $description = 'Наполняет parts_catalog находками из kaspi:parse-sku-raw — контент для GSC, не для продажи';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $deleted = DB::table('parts_catalog')->where('source', 'raw')->delete();
            $this->warn("Удалено старых raw-записей: {$deleted}");
        }

        $now = now();
        $total = 0;
        $skippedEmpty = 0;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        // request_article в kaspi_sku_test — это голый sku без привязки к
        // бренду (см. ParseKaspiSkuRawCommand), поэтому та же
        // страховка-проверка "бренд встречается в найденном названии
        // карточки", что и в MatchKaspiSkuCommand — иначе один и тот же
        // артикул от двух разных брендов (обычная история для
        // generic-подшипников/сайлентблоков) склеится в одну карточку.
        $baseQuery = DB::table('kaspi_sku_test as kst')
            ->join('supplier_offers_raw as sor', 'sor.sku', '=', 'kst.request_article')
            ->whereNotIn('kst.sku', ['NOT_FOUND', 'SKIPPED_COLOR'])
            ->whereRaw('LOWER(kst.name) LIKE CONCAT("%", LOWER(sor.brand), "%")')
            ->orderBy('kst.sku')
            ->groupBy('kst.sku', 'sor.sku', 'sor.brand', 'kst.name')
            ->select('kst.sku as kaspi_sku', 'sor.sku as our_article', 'sor.brand', 'kst.name as kaspi_name');

        $processBatch = function ($items) use (&$total, &$skippedEmpty, $now) {
            $batch = [];

            foreach ($items as $item) {
                $articleNorm = SeedOwnPartsCatalogCommand::normalizeArticle($item->our_article);
                $brandNorm = SeedOwnPartsCatalogCommand::normalizeBrand($item->brand);

                if ($articleNorm === '' || $brandNorm === '') {
                    $skippedEmpty++;
                    continue;
                }

                $batch[] = [
                    'article' => $item->our_article,
                    'article_normalized' => $articleNorm,
                    'brand' => $item->brand,
                    'brand_normalized' => $brandNorm,
                    'brand_slug' => SlugHelper::brandToSlug($item->brand),
                    'name' => $item->kaspi_name,
                    'source' => 'raw',
                    'source_kaspi_sku' => $item->kaspi_sku,
                    'scrape_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($batch)) {
                // insertOrIgnore — на общем уникальном ключе
                // (article_normalized, brand_normalized) в parts_catalog,
                // так что если такая пара уже есть под source='own' (или
                // раньше залетела как 'raw') — просто пропустится, не
                // задвоит.
                $total += DB::table('parts_catalog')->insertOrIgnore($batch);
            }
        };

        if ($limit) {
            $items = $baseQuery->limit($limit)->get();
            $processBatch($items);
        } else {
            $baseQuery->chunk(1000, $processBatch);
        }

        $this->info("Добавлено новых raw-карточек: {$total}");
        if ($skippedEmpty > 0) {
            $this->warn("Пропущено (пустой article/brand после нормализации): {$skippedEmpty}");
        }

        return self::SUCCESS;
    }
}
