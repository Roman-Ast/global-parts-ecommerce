<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ParseKaspiSkuCommand extends Command
{
    protected $signature = 'kaspi:parse-sku {--limit=111000} {--supplier=}';

    protected $description = 'Парсит Каспи: SKU + конкуренты + количество в комплекте';

    private string $cityId = '710000000';

    private string $cookies = 'mc-session=1783326645.435.114065.686134|825e5f3659dba1ed7b5d7b2cbf5f1012; mc-sid=cfca2f70-71f1-4dc6-9ca1-82c921ac43e8';

    /**
     * Минимальная длина нормализованного артикула, до которой можно
     * укорачивать поисковый запрос в fallback-цикле.
     */
    const MIN_SEARCH_QUERY_LEN = 4;

    const BUNDLE_PRICED_KEYWORDS = [
        'колодки',
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
            $this->line("→ Артикул: {$product->sku} (бренд: {$product->brand})");

            $results = $this->searchKaspi($product->brand, $product->sku);

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
                $anyBound = false;

                foreach ($results as $result) {
                    $signals = $this->fetchSpecificationSignals($result['sku']);

                    // Карточка с явным цветом (напр. крашеный бампер под конкретный
                    // цвет кузова) — тот же артикул, но другой товар и другая цена.
                    // Роман поймал случай "номер совпал, а карточка дороже" именно
                    // из-за этого — не привязываемся к таким карточкам вообще.
                    if ($signals['has_color']) {
                        $this->line("  ⨯ {$result['sku']} — {$result['name']} | пропущено: карточка с цветом");
                        usleep(random_int(800000, 1500000));
                        continue;
                    }

                    $anyBound = true;

                    $competitorData = $this->fetchOffers(
                        $result['sku'],
                        $result['brand'],
                        $result['categoryCodes'],
                        $product->sku
                    );

                    $kaspiQty = $signals['qty'];

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

                // Все найденные кандидаты оказались цветными карточками — явно
                // помечаем причину (не просто "ничего не сохранили молча"),
                // чтобы при разборе было видно, что это осознанный пропуск,
                // а не баг поиска.
                if (!$anyBound) {
                    DB::table('kaspi_sku_test')->insert([
                        'request_article' => $product->sku,
                        'sku'             => 'SKIPPED_COLOR',
                        'name'            => '',
                    ]);
                }
            }

            DB::table('kaspi_initial_products')
                ->where('sku', $product->sku)
                ->update(['kaspi_parsed' => 1]);

            usleep(random_int(1500000, 2500000));
        }

        $this->info("Готово. Сохранено результатов: {$totalSaved}");

        // ВРЕМЕННО: после основного парсинга сразу запускаем полный пересбор
        // карточек (включая уже готовые, --rescrape-done) — чтобы заодно
        // дособрать category_path/category_leaf для подготовки выгрузки на
        // Ozon, без отдельного ручного ночного запуска. Затея разовая —
        // когда категории соберутся по всей базе, этот блок стоит вернуть
        // обратно к обычному вызову без --rescrape-done (или вовсе убрать
        // отсюда и звать parts:scrape-cards отдельным шагом в pipeline),
        // иначе КАЖДЫЙ прогон kaspi:parse-sku будет заново пересобирать
        // ВСЕ ~49к карточек (часы времени) вместо только новых.
        /*$this->info('Запускаем полный пересбор карточек Kaspi (включая готовые, для категорий)...');
        \Illuminate\Support\Facades\Artisan::call('parts:scrape-cards', [
            '--source'         => 'own',
            '--limit'          => 0,
            '--rescrape-done'  => true,
        ], $this->getOutput());
        $this->info('Скрапинг карточек завершён.');*/

        return 0;
    }

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

    /**
     * Нормализует артикул: убирает ТОЛЬКО дефисы, приводит к верхнему
     * регистру. Пробелы НЕ убираем — они нужны как настоящие границы
     * слов в названии карточки при проверке токена.
     */
    private function normalizeArticle(string $s): string
    {
        $s = mb_strtoupper($s);
        return str_replace('-', '', $s);
    }

    /**
     * Ищет карточки Kaspi по артикулу с fallback на укороченные запросы.
     *
     * Поисковый движок Kaspi, похоже, токенизирует названия карточек ПО
     * ДЕФИСАМ ("BW0010-07-2" -> токены "BW0010","07","2"). Слитный запрос
     * без дефисов длиннее одного индексного токена ("BW0010072") движок
     * не находит вообще, даже если карточка реально существует.
     *
     * Если полный запрос пуст — пробуем короче (обрезая хвост), получаем
     * более широкий список кандидатов от Kaspi, но фильтруем СТРОГО по
     * полному (не обрезанному) артикулу с проверкой границы токена —
     * обрезка касается только текста запроса, не критерия совпадения.
     */
    private function searchKaspi(string $brand, string $article): ?array
    {
        $articleNormalized = $this->normalizeArticle($article);
        $fullLen = mb_strlen($articleNormalized);

        for ($len = $fullLen; $len >= min($fullLen, self::MIN_SEARCH_QUERY_LEN); $len--) {
            $queryArticle = mb_substr($articleNormalized, 0, $len);
            $searchQuery  = mb_strtoupper($brand) . ' ' . $queryArticle;

            if ($len < $fullLen) {
                $this->line("  ↳ полный запрос не дал совпадений, пробуем короче: {$searchQuery}");
                usleep(random_int(500000, 900000));
            }

            $rawResults = $this->searchKaspiRaw($searchQuery);

            if ($rawResults === null) {
                return null; // сессия истекла
            }

            $filtered = $this->filterByExactArticle($rawResults, $article);

            if (!empty($filtered)) {
                return $filtered;
            }
        }

        return [];
    }

    /**
     * Сырой поиск по Kaspi API без фильтрации по артикулу (только
     * базовая проверка id/title/категории). Точный отбор — отдельно
     * в filterByExactArticle().
     */
    private function searchKaspiRaw(string $query): ?array
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

            $data    = $response->json();
            $results = [];

            foreach ($data['products'] ?? [] as $item) {
                if (empty($item['id']) || empty($item['title'])) {
                    continue;
                }

                $categoryCodes = $item['categoryCodes'] ?? [];
                if (!in_array('Replacement parts', $categoryCodes)) {
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

    /**
     * Строгая фильтрация кандидатов по ПОЛНОМУ артикулу, с проверкой
     * границы токена — тот же принцип, что защищал от бага BW0010.
     */
    private function filterByExactArticle(array $rawResults, string $article): array
    {
        $articleNormalized = $this->normalizeArticle($article);
        $pattern = '/(?<![A-Z0-9])' . preg_quote($articleNormalized, '/') . '(?![A-Z0-9])/u';

        $filtered = [];
        foreach ($rawResults as $r) {
            $nameNormalized = $this->normalizeArticle($r['name']);
            if (preg_match($pattern, $nameNormalized)) {
                $filtered[] = $r;
            }
        }

        return $filtered;
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

    /**
     * Достаёт из specifications карточки Kaspi два сигнала за один запрос:
     * - qty: сколько единиц реально в комплекте (влияет на себестоимость в
     *   RepriceKaspiCommand/SyncKaspiFeedCommand — cost = purchase * qty).
     *   Основной источник — фича с именем "количество". Но иногда (напр.
     *   заводские свечи JAC 2.0 TURBO) число комплекта не вынесено в
     *   отдельную фичу, а спрятано текстом внутри значения "Дополнительная
     *   информация" ("...комплект из 4 штук...") — на этот случай fallback-
     *   регэксп по значениям ВСЕХ фич, не только по имени "количество".
     * - has_color: есть ли у карточки явная фича "цвет" — крашеные детали
     *   под конкретный цвет кузова (напр. бампер) имеют тот же артикул, что
     *   и обычная деталь, но другую цену — привязываться к таким карточкам
     *   нельзя (см. handle()).
     */
    private function fetchSpecificationSignals(string $kaspiSku): array
    {
        $default = ['qty' => 1, 'has_color' => false];

        try {
            $output = shell_exec(
                "curl -s 'https://kaspi.kz/shop/p/-{$kaspiSku}/' " .
                "-H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' " .
                "| grep -o '\"specifications\":\\[.*\\]' | head -c 50000"
            );

            if (empty($output)) return $default;

            $json = substr($output, strpos($output, '['));
            $json = substr($json, 0, strrpos($json, ']') + 1);

            $data = json_decode('{"specifications":' . $json . '}', true);
            if (!$data) return $default;

            $qty      = null;
            $hasColor = false;

            foreach ($data['specifications'] ?? [] as $section) {
                foreach ($section['features'] ?? [] as $feature) {
                    $nameLower = mb_strtolower($feature['name'] ?? '');

                    if ($qty === null && str_contains($nameLower, 'количество')) {
                        $value = $feature['featureValues'][0]['value'] ?? '1';
                        $qty = max(1, (int) $value);
                    }

                    if (str_contains($nameLower, 'цвет')) {
                        $hasColor = true;
                    }

                    // Fallback для количества: "комплект из N шт(ук)" внутри
                    // произвольного текстового значения (напр. "Дополнительная
                    // информация"), не в отдельной именованной фиче.
                    if ($qty === null) {
                        foreach ($feature['featureValues'] ?? [] as $featureValue) {
                            $text = mb_strtolower((string) ($featureValue['value'] ?? ''));
                            if (preg_match('/комплект[^0-9]*(\d+)\s*шт/u', $text, $m)) {
                                $qty = max(1, (int) $m[1]);
                                break;
                            }
                        }
                    }
                }
            }

            return [
                'qty'       => $qty ?? 1,
                'has_color' => $hasColor,
            ];

        } catch (\Exception $e) {
            $this->warn("  Ошибка спецификаций SKU {$kaspiSku}: " . $e->getMessage());
            return $default;
        }
    }
}