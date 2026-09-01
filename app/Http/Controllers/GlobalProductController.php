<?php

namespace App\Http\Controllers;

use App\Console\Commands\SeedOwnPartsCatalogCommand;
use App\Helpers\SlugHelper;
use App\Models\PartsCatalog;
use App\Services\SupplierOfferPricer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GlobalProductController extends Controller
{
    /**
     * /product/{brand}/{article} — теперь смотрит в parts_catalog (контент
     * из скрейпа Kaspi-карточек), а не в устаревший global_catalog (цены там
     * зависли на 2026-05-20, см. CLAUDE.md). Три исхода:
     *
     *  1. Карточка есть и заскрейплена (scrape_status=done) — полный
     *     контент (фото/описание/характеристики), индексируем, 301 на
     *     канонический URL при несовпадении регистра/слага.
     *  2. Карточки нет, но товар реально продаётся (живой supplier_offers) —
     *     облегчённая версия страницы (как раньше выглядел весь сайт), но с
     *     noindex — впустую тратить краулинговый бюджет Google на тонкий
     *     контент мы и пытаемся прекратить.
     *  3. Ни того, ни другого — 410 Gone. Явный сигнал "не возвращайся",
     *     быстрее выпадает из индекса, чем мягкий 404.
     *
     *  Оба поддерживаемых случая (1 и 2) требуют, чтобы бренд хоть раз
     *  был привязан к карточке Kaspi (parts_catalog.brand_slug) — иначе
     *  восстановить исходное написание бренда из URL-слага нечем, а
     *  гадать по эвристике (дефис↔пробел) ненадёжно.
     */
    public function show($brand = '', $article = '')
    {
        // Роут '/product/{brand}/{article}' объявлен с ->where('brand', '.*')
        // — это намеренно (бренды со слэшем, напр. "Citroen/Peugeot"), но
        // тем же разрешает пустой сегмент бренда. На "/product//АРТИКУЛ"
        // (двойной слэш — старые URL с NULL/пустым брендом в прежней
        // global_catalog, реально всплыли в GSC как 500 на ГАЗ/ВАЗ-позициях
        // 2026-08-24) Laravel резолвит только ОДИН параметр из двух, и без
        // значений по умолчанию здесь падал ArgumentCountError — фатально,
        // ещё до проверки $brandSlug === '' ниже, которая для этого и
        // написана.
        $rawBrand = urldecode($brand);
        $rawArticle = urldecode($article);

        // Лечим раскладку: копипаста артикула при русской раскладке
        // клавиатуры подменяет визуально неотличимые латинские буквы на
        // кириллические — без этого такие артикулы никогда не находились.
        $cyrillic = ['С', 'А', 'Е', 'О', 'Р', 'К', 'Х', 'В', 'а', 'е', 'о', 'р', 'к', 'х', 'с'];
        $latin    = ['C', 'A', 'E', 'O', 'P', 'K', 'X', 'B', 'a', 'e', 'o', 'p', 'k', 'x', 'c'];
        $decodedArticle = str_replace($cyrillic, $latin, $rawArticle);

        $brandSlug = SlugHelper::brandToSlug($rawBrand);
        $articleNormalized = SeedOwnPartsCatalogCommand::normalizeArticle($decodedArticle);

        if ($brandSlug === '' || $articleNormalized === '') {
            return $this->renderGone();
        }

        $card = PartsCatalog::query()
            ->where('brand_slug', $brandSlug)
            ->where('article_normalized', $articleNormalized)
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->first();

        if ($card) {
            return $this->showRichCard($card);
        }

        return $this->showFallback($brandSlug, $articleNormalized, $decodedArticle);
    }

    private function showRichCard(PartsCatalog $card)
    {
        $correctPath = 'product/' . $card->brand_slug . '/' . strtolower(trim($card->article));
        $currentPath = urldecode(request()->path());

        if ($currentPath !== $correctPath) {
            return redirect()->to(url($correctPath), 301);
        }

        (new SupplierOfferPricer())->attach(collect([$card]));
        $card->retail_price = $card->offer['retail_price'] ?? 0;

        $recommended = PartsCatalog::query()
            ->where('brand_slug', $card->brand_slug)
            ->where('id', '!=', $card->id)
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        (new SupplierOfferPricer())->attach($recommended);

        return response()->view('global_product', [
            'product'      => $card,
            'recommended'  => $recommended,
            'canonicalUrl' => url($correctPath),
            'indexable'    => true,
        ], 200);
    }

    /**
     * Товар реально в продаже (живой supplier_offers), но своей карточки
     * в parts_catalog ещё нет — либо очередь скрейпа не дошла, либо бренд
     * никогда не матчился с Kaspi вовсе. Показываем облегчённую версию,
     * но помечаем noindex, чтобы не плодить тонкий контент в индексе, пока
     * пока нет реального наполнения.
     */
    private function showFallback(string $brandSlug, string $articleNormalized, string $rawArticle)
    {
        $brandNormalized = PartsCatalog::query()
            ->where('brand_slug', $brandSlug)
            ->value('brand_normalized');

        if (!$brandNormalized) {
            return $this->renderGone();
        }

        $offer = DB::table('supplier_offers')
            ->where('sku_normalized', $articleNormalized)
            ->where('brand_normalized', $brandNormalized)
            ->orderByDesc('stock')
            ->orderBy('purchase_price')
            ->first();

        if (!$offer) {
            return $this->renderGone();
        }

        $pricer = new SparePartController();

        $product = new \stdClass();
        $product->id = 0;
        $product->name = $offer->title ?: ('Запчасть ' . $rawArticle);
        $product->brand = $offer->brand;
        $product->article = $rawArticle;
        $product->offer = [
            'purchase_price' => (float) $offer->purchase_price,
            'retail_price'   => (int) ceil($pricer->setPrice((float) $offer->purchase_price)),
            'stock'          => (int) $offer->stock,
            'supplier_name'  => $offer->supplier_name,
            'preorder_days'  => (int) $offer->preorder_days,
        ];
        $product->retail_price = $product->offer['retail_price'];
        $product->is_virtual = false;

        return response()->view('global_product', [
            'product'      => $product,
            'recommended'  => collect(),
            'canonicalUrl' => url()->current(),
            'indexable'    => false,
        ], 200)->header('X-Robots-Tag', 'noindex, follow');
    }

    private function renderGone()
    {
        return response()->view('global_product_gone', [], 410);
    }

    public function fetchGoogleImages(Request $request)
    {
        $query = $request->query('q');

        // Делаем запрос к Google API
        $response = Http::get("https://www.googleapis.com/customsearch/v1", [
            'key' => env('GOOGLE_SEARCH_API_KEY'),
            'cx'  => env('GOOGLE_SEARCH_CX'),
            'q'   => $query . ' auto part', // Добавляем контекст запчастей
            'searchType' => 'image',
            'num' => 5 // Берем 5 картинок
        ]);

        if ($response->successful()) {
            return response()->json($response->json()['items'] ?? []);
        }

        return response()->json([
            'error' => 'Ошибка API',
            'status' => $response->status(),
            'body'   => $response->json(), // ← вот тут увидишь что именно Google говорит
        ], 500);
    }

    public function getApiPrices(Request $request)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        try {
            $article = $request->query('article');
            $brand = $request->query('brand');

            // Создаем подзапрос, принудительно включая поиск по всем складам (включая Автопитер)
            $subRequest = new Request([
                'partnumber' => $article,
                'brand'      => $brand,
                'rossko_need_to_search' => false, // Росску мы грузим отдельно асинхронно
                'only_on_stock' => false         // Включаем внешние склады (Автопитер и др.)
            ]);

            $sparePartCtrl = new \App\Http\Controllers\SparePartController();
            $sparePartCtrl->getSearchedPartAndCrosses($subRequest);

            // Достаем данные из защищенного свойства finalArr через Reflection
            $reflection = new \ReflectionClass($sparePartCtrl);
            $property = $reflection->getProperty('finalArr');
            $property->setAccessible(true);
            $finalData = $property->getValue($sparePartCtrl);

            // Собираем все результаты (искомые и кроссы) в один массив
            $all = array_merge(
                $finalData['searchedNumber'] ?? [],
                $finalData['crosses_on_stock'] ?? [],
                $finalData['crosses_to_order'] ?? []
            );

            // 1. Очистка и нормализация данных
            $cleanOffers = [];
            foreach ($all as $item) {
                $cleanOffers[] = [
                    'brand'   => strtoupper((string)($item['brand'] ?? '')),
                    'article' => (string)($item['article'] ?? ''),
                    'name'    => mb_convert_encoding((string)($item['name'] ?? ''), 'UTF-8', 'UTF-8'),
                    'qty'     => (int)($item['qty'] ?? 0),
                    'price'   => $item['price'] ?? 0,
                    'priceWithMargine' => (int)($item['priceWithMargine'] ?? 0),
                    'delivery_time'    => (string)($item['delivery_time'] ?? ($item['deliveryStart'] ?? '1-2 дня')),
                    'supplier_city'    => (string)($item['supplier_city'] ?? 'Склад')
                ];
            }

            // 2. Умная группировка и фильтрация "мусора"
            $processed = collect($cleanOffers)
                ->groupBy(function($item) {
                    // Группируем по Бренду и чистому Артикулу (без тире и пробелов)
                    $cleanArt = preg_replace('/[^A-Z0-9]/', '', strtoupper($item['article']));
                    return $item['brand'] . '|' . $cleanArt;
                })
                ->flatMap(function($group) {
                    // Внутри каждой группы (например, все LYNX CO-7301):

                    // Сначала ищем наличие в Астане
                    $inAstana = $group->filter(function($item) {
                        return $item['supplier_city'] === 'ast' ||
                            str_contains(mb_strtolower($item['delivery_time']), 'часа');
                    })->sortBy('priceWithMargine');

                    // Если предложений в группе очень много (Автопитер "заспамил")
                    if ($group->count() > 3) {
                        // Берем Астану (если есть) + 2 самых дешевых варианта из остальных
                        // Метод unique гарантирует, что мы не продублируем Астану, если она и так самая дешевая
                        return $inAstana->concat($group->sortBy('priceWithMargine')->take(2))->unique();
                    }

                    return $group;
                })
                ->sortBy('priceWithMargine') // Глобальная сортировка всей таблицы по цене
                ->values();

            // 3. Возвращаем чистый, красивый JSON
            return response()->json(
                ['offers' => $processed],
                200,
                [],
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getRosskoApi(Request $request)
    {
        // Создаем экземпляр контроллера, где лежит метод
        $sparePartCtrl = new \App\Http\Controllers\SparePartController();

        // Вызываем метод оттуда
        $offers = $sparePartCtrl->getRosskoPricesOnly($request->brand, $request->article);

        return response()->json(['offers' => $offers]);
    }

    public function addToCartApi(Request $request)
    {
        // 1. Пытаемся достать корзину из сессии
        $cart = session()->get('cart');

        // 2. ПРОВЕРКА: Если там пусто или (вдруг) затесался массив от прошлых тестов —
        // создаем НОВЫЙ объект твоего класса. Это защитит от ошибок.
        if (!$cart instanceof \App\Cart) {
            $cart = new \App\Cart();
        }

        // 3. Добавляем товар, используя РОДНОЙ метод твоего класса
        // Твой метод add() принимает 9 параметров, передаем их строго по порядку:
        $cart->add(
            (string)$request->article,         // $article
            (string)$request->brand,           // $brand
            (string)$request->name,            // $name
            (string)$request->article,         // $originNumber (обычно совпадает с артикулом)
            (string)$request->delivery,        // $deliveryTime
            (string)$request->price,           // $price (закуп)
            (int)$request->quantity,           // $qty
            (string)$request->supplier,        // $stockFrom
            (int)$request->retail_price        // $priceWithMargine (продажа)
        );

        // Клиенту в поиске видна только обезличенная метка склада
        // (stockFrom, напр. "ast"/"москва") — реального поставщика его
        // браузер никогда не получает (защита от Network-инспекции
        // конкурентами). Поэтому здесь, на сервере, best-effort находим
        // реального поставщика заново по article+brand+цена закупа, чтобы
        // админ видел его в заказе, а не ту же обезличенную метку, что и
        // клиент.
        $lastIndex = count($cart->items) - 1;
        if ($lastIndex >= 0) {
            $cart->items[$lastIndex]['adminSupplierName'] = $this->resolveAdminSupplierName(
                (string) $request->article,
                (string) $request->brand,
                (float) $request->price
            );
        }

        // 4. Сохраняем ОБЪЕКТ обратно.
        // Теперь и старый поиск, и новый метод видят одну и ту же структуру.
        session()->put('cart', $cart);

        return response()->json([
            'success' => true,
            'cart_count' => $cart->count(),
            'message' => 'Товар добавлен в корзину'
        ]);
    }

    /**
     * Best-effort поиск реального поставщика для позиции, добавленной в
     * корзину — только для отображения админу в заказе, см. миграцию
     * 2026_09_01_000006 за подробным разбором зачем. Сначала пробуем
     * точное совпадение цены закупа (самый надёжный сигнал), если не
     * нашлось — берём ближайшую по цене среди совпадений по артикулу и
     * бренду.
     */
    private function resolveAdminSupplierName(string $article, string $brand, float $purchasePrice): ?string
    {
        $articleNorm = SeedOwnPartsCatalogCommand::normalizeArticle($article);
        $brandNorm = SeedOwnPartsCatalogCommand::normalizeBrand($brand);

        if ($articleNorm === '' || $brandNorm === '') {
            return null;
        }

        $candidates = DB::table('supplier_offers')
            ->where('sku_normalized', $articleNorm)
            ->where('brand_normalized', $brandNorm)
            ->select('supplier_name', 'purchase_price')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $exact = $candidates->first(fn ($row) => abs((float) $row->purchase_price - $purchasePrice) < 0.01);
        if ($exact) {
            return $exact->supplier_name;
        }

        return $candidates->sortBy(fn ($row) => abs((float) $row->purchase_price - $purchasePrice))->first()->supplier_name;
    }

}
