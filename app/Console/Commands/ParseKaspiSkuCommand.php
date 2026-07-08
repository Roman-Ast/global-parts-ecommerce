<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ParseKaspiSkuCommand extends Command
{
    protected $signature = 'kaspi:parse-sku {--limit=11000} {--supplier=}';

    protected $description = 'Парсит Каспи: SKU + конкуренты + количество в комплекте';

    private string $cityId = '710000000';

    private string $cookies = 'mc-session=1783326645.435.114065.686134|825e5f3659dba1ed7b5d7b2cbf5f1012; mc-sid=0a99e808-b347-4d4a-9b04-da7b56e9d6f9';

    /**
     * Категории товаров, где у ПОСТАВЩИКА цена уже указана за комплект,
     * а не за 1 штуку. Для них kaspi_qty используется только для
     * информации (сравнение с карточкой Kaspi), но НЕ умножает
     * purchase_price при расчёте себестоимости.
     *
     * Сюда добавлять новые ключевые слова по мере обнаружения новых кейсов.
     * Сравнение регистронезависимое, ищется подстрока в названии товара.
     */
    const BUNDLE_PRICED_KEYWORDS = [
        'колодки', // тормозные колодки — прайс поставщика уже за комплект (обычно 4 шт)
    ];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $query = DB::table('kaspi_initial_products')
            ->where('kaspi_parsed', 0)
            ->where('stock', '>=', 2);

        if ($supplier = $this->option('supplier')) {
            $query->where('supplier_name', $supplier);
        }

        $products = $query->limit($limit)->get(['sku', 'brand', 'title']);

        if ($products->isEmpty()) {
            $this->info('Нет новых артикулов для парсинга.');
            return 0;
        }

        $this->info("Обрабатываем {$products->count()} артикулов...");

        $totalSaved = 0;

        foreach ($products as $product) {
            $searchQuery = mb_strtoupper($product->brand) . ' ' . $product->sku;
            $this->line("→ Ищем: {$searchQuery}");

            $results = $this->searchKaspi($searchQuery, $product->sku);

            if ($results === null) {
                $this->error('Сессия истекла! Обнови куки и запусти снова.');
                return 1;
            }

            if (empty($results)) {
                DB::table('kaspi_sku_test')->insert([
                    'request_article' => $product->sku,
                    'sku'             => 'NOT_FOUND',
                    'name'            => '',
                ]);
            } else {
                foreach ($results as $result) {
                    // Шаг 2: конкуренты
                    $competitorData = $this->fetchOffers(
                        $result['sku'],
                        $result['brand'],
                        $result['categoryCodes'],
                        $product->sku
                    );

                    // Шаг 3: количество в комплекте из характеристик
                    $kaspiQty = $this->fetchSpecifications($result['sku']);

                    // Если поставщик продаёт товар комплектом (например колодки),
                    // помечаем это отдельным флагом — пригодится для расчёта маржи
                    $isBundlePriced = $this->isBundlePriced($product->title ?? $result['name']);

                    DB::table('kaspi_sku_test')->insert([
                        'request_article'            => $product->sku,
                        'sku'                        => $result['sku'],
                        'name'                       => $result['name'],
                        'competitors_min_price'      => $competitorData['min_price'],
                        'competitors_tomorrow_count' => $competitorData['tomorrow_count'],
                        'competitors_total'          => $competitorData['total'],
                        'kaspi_qty'                  => $kaspiQty,
                        'qty_suspicious'             => $isBundlePriced ? 1 : 0,
                        'competitors_parsed_at'      => now(),
                    ]);

                    $totalSaved++;
                    $bundleNote = $isBundlePriced ? ' [BUNDLE]' : '';
                    $this->line("  ✓ {$result['sku']} — {$result['name']} | кол-во: {$kaspiQty}{$bundleNote} | мин.цена: {$competitorData['min_price']} | конкурентов завтра: {$competitorData['tomorrow_count']}/{$competitorData['total']}");

                    usleep(random_int(800000, 1500000));
                }
            }

            DB::table('kaspi_initial_products')
                ->where('sku', $product->sku)
                ->update(['kaspi_parsed' => 1]);

            usleep(random_int(1500000, 2500000));
        }

        $this->info("Готово. Сохранено результатов: {$totalSaved}");
        return 0;
    }

    /**
     * Определяет, продаёт ли поставщик данный товар комплектом
     * (цена в прайсе уже за весь комплект, не за 1 штуку).
     */
    private function isBundlePriced(string $title): bool
    {
        $titleLower = mb_strtolower($title);

        foreach (self::BUNDLE_PRICED_KEYWORDS as $keyword) {
            if (str_contains($titleLower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function searchKaspi(string $query, string $article): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'ru,ru-RU;q=0.9,en-US;q=0.8,en;q=0.7',
                'Connection'      => 'keep-alive',
                'Referer'         => 'https://kaspi.kz/mc/',
                'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
                'X-Auth-Version'  => '3',
                'x-merchant'      => '30360429',
                'Cookie'          => $this->cookies,
            ])->timeout(15)->get('https://kaspi.kz/yml/product-view/mc/products', [
                'text' => $query,
            ]);

            if ($response->status() === 401) {
                return null;
            }

            if (!$response->ok()) {
                $this->warn("  HTTP {$response->status()}");
                return [];
            }

            $data         = $response->json();
            $results      = [];
            $articleUpper = strtoupper(trim($article));

            foreach ($data['products'] ?? [] as $item) {
                if (empty($item['id']) || empty($item['title'])) {
                    continue;
                }

                $categoryCodes = $item['categoryCodes'] ?? [];
                if (!in_array('Replacement parts', $categoryCodes)) {
                    continue;
                }

                $nameUpper = strtoupper($item['title']);
                if (!str_contains($nameUpper, $articleUpper)) {
                    continue;
                }

                $results[] = [
                    'sku'           => $item['id'],
                    'name'          => mb_substr($item['title'], 0, 250),
                    'brand'         => $item['brand'] ?? '',
                    'categoryCodes' => $categoryCodes,
                ];
            }

            return $results;

        } catch (\Exception $e) {
            $this->warn("  Ошибка поиска: " . $e->getMessage());
            return [];
        }
    }

    private function fetchOffers(string $sku, string $brand, array $categoryCodes, string $requestArticle): array
    {
        $default = ['min_price' => null, 'tomorrow_count' => 0, 'total' => 0];

        try {
            $response = Http::withHeaders([
                'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Content-Type' => 'application/json',
                'Referer'      => "https://kaspi.kz/shop/p/{$sku}/",
                'Accept'       => 'application/json',
            ])->timeout(15)->post("https://kaspi.kz/yml/offer-view/offers/{$sku}", [
                'cityId'              => $this->cityId,
                'id'                  => $sku,
                'merchantUID'         => [],
                'limit'               => 20,
                'page'                => 0,
                'product'             => [
                    'brand'            => $brand,
                    'baseProductCodes' => [],
                    'categoryCodes'    => $categoryCodes,
                    'groups'           => null,
                    'productSeries'    => [],
                ],
                'searchText'          => null,
                'sortOption'          => 'PRICE',
                'zoneId'              => ['Magnum_ZONE1'],
                'highRating'          => null,
                'isExcellentMerchant' => false,
                'installationId'      => '-1',
            ]);

            if (!$response->ok()) {
                $this->warn("  Офферы HTTP {$response->status()} для SKU {$sku}");
                return $default;
            }

            $data   = $response->json();
            $offers = $data['offers'] ?? [];

            $minPrice = null;
            $tomorrowCount = 0;

            foreach ($offers as $offer) {
                $merchantId = $offer['merchantId'] ?? null;

                if ($merchantId === '30360429') {
                    continue;
                }

                $deliveryDuration = $offer['deliveryDuration'] ?? null;
                $preorderDays     = (int) ($offer['preorder'] ?? 0);

                $offerPrice = isset($offer['price']) ? (int) $offer['price'] : null;
                if ($offerPrice && ($minPrice === null || $offerPrice < $minPrice)) {
                    $minPrice = $offerPrice;
                }

                if ($deliveryDuration === 'TOMORROW' && $preorderDays === 0) {
                    $tomorrowCount++;
                }

                DB::table('kaspi_competitors')->insert([
                    'kaspi_sku'         => $sku,
                    'request_article'   => $requestArticle,
                    'merchant_id'       => $merchantId,
                    'merchant_name'     => $offer['merchantName'] ?? null,
                    'merchant_rating'   => $offer['merchantRating'] ?? null,
                    'merchant_reviews'  => $offer['merchantReviewsQuantity'] ?? 0,
                    'price'             => $offer['price'] ?? null,
                    'delivery_duration' => $deliveryDuration,
                    'preorder_days'     => $preorderDays,
                    'parsed_at'         => now(),
                ]);
            }

            return [
                'min_price'      => $minPrice,
                'tomorrow_count' => $tomorrowCount,
                'total'          => (int) ($data['offersCount'] ?? count($offers)),
            ];

        } catch (\Exception $e) {
            $this->warn("  Ошибка офферов: " . $e->getMessage());
            return $default;
        }
    }

    private function fetchSpecifications(string $kaspiSku): int
    {
        try {
            $output = shell_exec(
                "curl -s 'https://kaspi.kz/shop/p/-{$kaspiSku}/' " .
                "-H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' " .
                "| grep -o '\"specifications\":\\[.*\\]' | head -c 50000"
            );

            if (empty($output)) return 1;

            $json = substr($output, strpos($output, '['));
            $json = substr($json, 0, strrpos($json, ']') + 1);

            $data = json_decode('{"specifications":' . $json . '}', true);
            if (!$data) return 1;

            foreach ($data['specifications'] ?? [] as $section) {
                foreach ($section['features'] ?? [] as $feature) {
                    if (str_contains(mb_strtolower($feature['name'] ?? ''), 'количество')) {
                        $value = $feature['featureValues'][0]['value'] ?? '1';
                        return max(1, (int) $value);
                    }
                }
            }

            return 1;

        } catch (\Exception $e) {
            $this->warn("  Ошибка спецификаций SKU {$kaspiSku}: " . $e->getMessage());
            return 1;
        }
    }
}