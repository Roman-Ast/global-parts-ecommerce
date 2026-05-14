<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlobalCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GlobalProductController extends Controller
{
    /**
     * Отображение карточки товара для SEO и НЧ-запросов
     */
    public function show($brand, $rest)
    {
        // 1. СБОРКА ПОЛНОГО ПУТИ И ДЕКОДИРОВАНИЕ
        // $rest содержит всё после /product/{brand}/ включая возможные слеши
        // Примеры что может прийти:
        //   brand=Hyundai, rest=Kia/4955121000  (бренд со слешем)
        //   brand=FILTRON, rest=AP139/2          (артикул со слешем)
        //   brand=XYG,     rest=5370AGNCMVZ2B LFW/X (артикул со слешем и пробелом)

        $decodedBrand = trim(urldecode($brand));
        $decodedRest  = trim(urldecode($rest));

        // 2. ОПРЕДЕЛЕНИЕ ТИПА URL — бренд со слешем или артикул со слешем?
        // Стратегия: пробуем все варианты разбивки brand/article из $rest
        $product = null;
        $matchedBrand = null;
        $matchedArticle = null;

        // Сначала пробуем: весь $rest — это артикул (brand = $decodedBrand)
        $candidates = $this->buildCandidates($decodedBrand, $decodedRest);

        foreach ($candidates as [$tryBrand, $tryArticle]) {
            $cleanArticle = preg_replace('/[^A-Za-z0-9]/', '', $tryArticle);
            if (!$cleanArticle) continue;

            $found = $this->findProduct($tryBrand, $cleanArticle, $tryArticle);
            if ($found) {
                $product = $found;
                $matchedBrand = $tryBrand;
                $matchedArticle = $tryArticle;
                break;
            }
        }

        // 3. SEO-РЕДИРЕКТ на канонический URL
        if ($product) {
            $canonicalBrand   = urlencode(str_replace(' ', '-', $product->brand));
            // убираем двойные дефисы после замены / и пробелов
            $canonicalBrand   = preg_replace('/-+/', '-', str_replace(['/', ' '], '-', $product->brand));
            $canonicalArticle = $product->clean_article;
            $correctPath = "product/{$canonicalBrand}/{$canonicalArticle}";

            $currentPath = request()->path();
            if (urldecode($currentPath) !== urldecode($correctPath)) {
                return redirect($correctPath, 301);
            }
        }

        // 4. ПОДГОТОВКА ДАННЫХ
        $cleanBrand = $product ? $product->brand : $decodedBrand;
        $canonicalUrl = $product
            ? route('product.show', [
                'brand' => str_replace(['/', ' '], '-', $product->brand),
                'rest'  => $product->clean_article,
            ])
            : url()->current();

        if ($product && !isset($product->is_virtual)) {
            $sparePartCtrl = new \App\Http\Controllers\SparePartController();
            $basePrice     = $product->price;
            $retailPrice   = $sparePartCtrl->setPrice($basePrice);
            if ($retailPrice == $basePrice && $basePrice > 0) {
                $retailPrice = $basePrice * 1.25;
            }
            $product->retail_price = $retailPrice;
        }

        // 5. РЕКОМЕНДАЦИИ
        $recommended = GlobalCatalog::where('brand', $cleanBrand)
            ->when($product, fn($q) => $q->where('clean_article', '!=', $product->clean_article))
            ->inRandomOrder()
            ->take(10)
            ->get();

            \Log::info('SHOW DEBUG', [
    'brand' => $decodedBrand,
    'rest'  => $decodedRest,
    'candidates' => $this->buildCandidates($decodedBrand, $decodedRest),
    'found' => $product ? $product->id : null,
]);
        // 6. 404 ЛОВУШКА
        if (!$product || (isset($product->is_virtual) && $product->is_virtual)) {
            if (!$product) {
                $product = new \stdClass();
                $product->name          = "Запчасть " . $decodedRest;
                $product->brand         = $decodedBrand;
                $product->article       = $decodedRest;
                $product->clean_article = preg_replace('/[^A-Za-z0-9]/', '', $decodedRest);
                $product->price         = 0;
                $product->retail_price  = 0;
                $product->supplier_name = null;
                $product->image         = null;
                $product->description   = null;
                $product->category      = null;
                $product->weight        = null;
                $product->is_virtual    = true;
            }
            return response()->view('global_product', compact('product', 'recommended', 'canonicalUrl'), 404);
        }

        return view('global_product', compact('product', 'recommended', 'canonicalUrl'));
    }

    /**
     * Строим список кандидатов (brand, article) для перебора.
     * Разбиваем $rest по слешу слева направо — пробуем присоединять сегменты к бренду.
     */
    private function buildCandidates(string $brand, string $rest): array
    {
        $candidates = [];
        $segments = explode('/', $rest);

        // Вариант 1: brand=$brand, article=весь $rest (слеши внутри артикула)
        $candidates[] = [$brand, $rest];

        // Вариант 2+: brand=$brand + первые N сегментов из $rest, article = остаток
        // Например: brand=Hyundai, rest=Kia/4955121000 → brand=Hyundai/Kia, article=4955121000
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $extBrand  = $brand . '/' . implode('/', array_slice($segments, 0, $i + 1));
            $article   = implode('/', array_slice($segments, $i + 1));
            $candidates[] = [$extBrand, $article];
        }

        // Вариант с дефисами → слеш в бренде (Hyundai-Kia → Hyundai/Kia)
        $brandWithSlash = str_replace('-', '/', $brand);
        if ($brandWithSlash !== $brand) {
            $candidates[] = [$brandWithSlash, $rest];
            for ($i = 0; $i < count($segments) - 1; $i++) {
                $extBrand = $brandWithSlash . '/' . implode('/', array_slice($segments, 0, $i + 1));
                $article  = implode('/', array_slice($segments, $i + 1));
                $candidates[] = [$extBrand, $article];
            }
        }

        return $candidates;
    }

    private function findProduct(string $brand, string $cleanArticle, string $rawArticle): ?GlobalCatalog
    {
        $brandUpper      = strtoupper($brand);
        $brandSlashUpper = strtoupper(str_replace('-', '/', $brand));

        $base = GlobalCatalog::where(function($q) use ($brandUpper, $brandSlashUpper) {
            $q->where(DB::raw('UPPER(TRIM(brand))'), $brandUpper)
            ->orWhere(DB::raw('UPPER(TRIM(brand))'), $brandSlashUpper);
        });

        // Точный поиск по clean_article
        $found = (clone $base)->where('clean_article', $cleanArticle)->first();
        if ($found) return $found;

        // Резервный — по маске
        return (clone $base)->where(function($q) use ($cleanArticle, $rawArticle) {
            $q->where(DB::raw("REPLACE(REPLACE(REPLACE(article, ' ', ''), '-', ''), '/', '')"), $cleanArticle)
            ->orWhere('article', 'LIKE', $cleanArticle . '%');
        })->first();
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

        // 4. Сохраняем ОБЪЕКТ обратно. 
        // Теперь и старый поиск, и новый метод видят одну и ту же структуру.
        session()->put('cart', $cart);

        return response()->json([
            'success' => true,
            'cart_count' => $cart->count(),
            'message' => 'Товар добавлен в корзину'
        ]);
    }

}
