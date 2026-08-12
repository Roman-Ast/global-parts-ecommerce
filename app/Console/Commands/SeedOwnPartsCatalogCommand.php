<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedOwnPartsCatalogCommand extends Command
{
    protected $signature = 'parts:seed-own {--fresh : удалить старые own-записи перед посевом} {--limit= : ограничить количество обрабатываемых kaspi_feed_items (для тестового прогона)}';
    protected $description = 'Наполняет parts_catalog собственными позициями из kaspi_feed_items';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $deleted = DB::table('parts_catalog')->where('source', 'own')->delete();
            $this->warn("Удалено старых own-записей: {$deleted}");
        }

        $now = now();
        $total = 0;
        $skippedEmpty = 0;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $baseQuery = DB::table('kaspi_feed_items')
            ->where('is_active', 1)
            ->where('bound', 1)
            ->whereNotNull('our_article')
            ->orderBy('kaspi_sku')
            ->groupBy('kaspi_sku', 'our_article', 'brand', 'kaspi_name')
            ->select('kaspi_sku', 'our_article', 'brand', 'kaspi_name');

        $processBatch = function ($items) use (&$total, &$skippedEmpty, $now) {
            $batch = [];

            foreach ($items as $item) {
                $articleNorm = self::normalizeArticle($item->our_article);
                $brandNorm = self::normalizeBrand($item->brand);

                if ($articleNorm === '' || $brandNorm === '') {
                    $skippedEmpty++;
                    continue;
                }

                $batch[] = [
                    'article' => $item->our_article,
                    'article_normalized' => $articleNorm,
                    'brand' => $item->brand,
                    'brand_normalized' => $brandNorm,
                    'name' => $item->kaspi_name,
                    'source' => 'own',
                    'source_kaspi_sku' => $item->kaspi_sku,
                    'scrape_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($batch)) {
                $total += DB::table('parts_catalog')->insertOrIgnore($batch);
            }
        };

        if ($limit) {
            // Небольшой тестовый прогон — просто get() с лимитом, без чанкинга
            // (limit() и chunk() в Laravel несовместимы: chunk сам управляет пагинацией).
            $items = $baseQuery->limit($limit)->get();
            $processBatch($items);
        } else {
            $baseQuery->chunk(1000, $processBatch);
        }

        $this->info("Добавлено новых own-карточек: {$total}");
        if ($skippedEmpty > 0) {
            $this->warn("Пропущено (пустой article/brand после нормализации): {$skippedEmpty}");
        }

        return self::SUCCESS;
    }

    public static function normalizeArticle(string $article): string
    {
        return mb_strtoupper(preg_replace('/[^a-zA-Zа-яА-Я0-9]/u', '', $article));
    }

    public static function normalizeBrand(string $brand): string
    {
        return mb_strtoupper(trim($brand));
    }
}