<?php

namespace App\Console\Commands;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Пилот на 100 позициях (см. CLAUDE.md, раздел "Halyk Market"): берём
 * артикулы из kaspi_initial_products (неизменяемый источник истины по
 * нашим Kaspi-карточкам — brand+sku там уже реальный производственный
 * артикул, не наш внутренний ID, проверено на выборке) и ищем их через
 * Halyk-овский GET /skus/search. Результат — сырые кандидаты в
 * halyk_sku_candidates, сопоставление и привязка — отдельными командами
 * (halyk:match / halyk:bind), тот же принцип разделения, что и в
 * kaspi:parse-sku → kaspi:match.
 */
class HalykSearchSkuCommand extends Command
{
    protected $signature = 'halyk:search-sku {--limit=100}';

    protected $description = 'Пилот: ищет артикулы из kaspi_initial_products на Halyk Market через их API поиска';

    public function handle(HalykMarketClient $client): int
    {
        $limit = (int) $this->option('limit');

        $alreadySearched = DB::table('halyk_sku_candidates')->pluck('request_article')->all();

        $products = DB::table('kaspi_initial_products')
            ->where('stock', '>', 0)
            ->whereNotIn('sku', $alreadySearched)
            ->orderBy('id')
            ->limit($limit)
            ->get(['sku', 'brand', 'title']);

        if ($products->isEmpty()) {
            $this->info('Нечего искать — либо лимит --limit исчерпан, либо все уже проверены.');
            return 0;
        }

        $this->info("Ищем {$products->count()} артикулов на Halyk Market...");

        foreach ($products as $product) {
            $query = trim("{$product->brand} {$product->sku}");
            $this->line("→ {$query}");

            try {
                $results = $client->searchSku($query, 1, 5);
            } catch (\Throwable $e) {
                $this->error('Halyk API недоступен: ' . $e->getMessage());
                return 1;
            }

            $now = now();

            if (empty($results)) {
                DB::table('halyk_sku_candidates')->insert([
                    'request_article' => $product->sku,
                    'request_brand'   => $product->brand,
                    'searched_at'     => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                $this->line('  ⨯ не найдено');
                usleep(300000);
                continue;
            }

            foreach ($results as $result) {
                DB::table('halyk_sku_candidates')->insert([
                    'request_article'     => $product->sku,
                    'request_brand'       => $product->brand,
                    'halyk_sku_id'        => $result['skuId'] ?? null,
                    'halyk_name'          => $result['name'] ?? null,
                    'halyk_category_id'   => $result['category']['id'] ?? null,
                    'halyk_category_name' => $result['category']['name'] ?? null,
                    'halyk_market_url'    => $result['marketUrl'] ?? null,
                    'searched_at'         => $now,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);
                $this->line("  · skuId={$result['skuId']} — {$result['name']}");
            }

            // Вежливая пауза между запросами к реальному партнёрскому API —
            // не скрейпинг, но всё равно не стоит долбить без пауз.
            usleep(300000);
        }

        return 0;
    }
}
