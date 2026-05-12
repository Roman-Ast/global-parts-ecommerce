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
    public function show($brand, $article)
    {
        // 1. Очистка входных данных
        // Убираем всё кроме букв и цифр для поиска и формирования правильного URL
        $cleanBrand = trim(urldecode($brand));
        $searchArticle = preg_replace('/[^A-Za-z0-9]/', '', urldecode($article));

        // 2. Поиск товара в базе
        // Сначала ищем по заранее подготовленному полю clean_article (если оно есть в БД)
        $product = GlobalCatalog::where(DB::raw('UPPER(TRIM(brand))'), strtoupper($cleanBrand))
            ->where('clean_article', $searchArticle)
            ->first();

        // Резервный поиск, если по чистому артикулу ничего не найдено
        if (!$product) {
            $product = \App\Models\GlobalCatalog::where(DB::raw('UPPER(TRIM(brand))'), strtoupper($cleanBrand))
                ->where(function($query) use ($searchArticle) {
                    $query->where(DB::raw("REPLACE(REPLACE(REPLACE(article, ' ', ''), '-', ''), '/', '')"), $searchArticle)
                        ->orWhere('article', 'LIKE', $searchArticle . '%');
                })
                ->first();
        }

        // 3. ПРОВЕРКА URL И РЕДИРЕКТ (Борьба с дублями)
        if ($product) {
            // Формируем "идеальный" путь: бренд как в базе / артикул без знаков
            // Используем rawurlencode для бренда на случай знаков '/' или пробелов
            $correctPath = "product/" . rawurlencode($product->brand) . "/" . $product->clean_article;
            $currentPath = request()->path();

            // Сравниваем текущий путь в браузере с идеальным. 
            // Если они разные (например, зашли по ссылке с дефисом) — перенаправляем 301 редиректом.
            if (urldecode($currentPath) !== urldecode($correctPath)) {
                return redirect()->to(url($correctPath), 301);
            }
        }

        // 4. ОБРАБОТКА (только если товар найден)
        $canonicalUrl = url()->current(); // Значение по умолчанию

        if ($product) {
            // Расчет цены
            if (!isset($product->is_virtual)) {
                $sparePartCtrl = new \App\Http\Controllers\SparePartController();
                $basePrice = $product->price;
                $retailPrice = $sparePartCtrl->setPrice($basePrice);

                if ($retailPrice == $basePrice && $basePrice > 0) {
                    $retailPrice = $basePrice * 1.25; 
                }
                $product->retail_price = $retailPrice;
            }

            // Формируем правильный каноникл
            $canonicalUrl = route('product.show', [
                'brand' => $product->brand,
                'article' => $product->clean_article ?? $product->article
            ]);
        }

        // 5. Рекомендации (пусть ищутся по бренду из URL, даже если товар не найден)
        $recommended = \App\Models\GlobalCatalog::where('brand', $cleanBrand)
            ->when($product, function($q) use ($product) {
                return $q->where('clean_article', '!=', $product->clean_article);
            })
            ->inRandomOrder()                  
            ->take(10)                         
            ->get();

        // 6. ФИНАЛЬНЫЙ ВЫВОД
        if (!$product || (isset($product->is_virtual) && $product->is_virtual)) {
            // Передаем null в компакт, чтобы вьюха знала, что показывать 404-стаб
            return response()->view('global_product', compact('product', 'recommended', 'canonicalUrl'), 404);
        }

        return view('global_product', compact('product', 'recommended', 'canonicalUrl'));
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
