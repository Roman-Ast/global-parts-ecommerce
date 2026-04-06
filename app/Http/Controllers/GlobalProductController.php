<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlobalCatalog;

class GlobalProductController extends Controller
{
    /**
     * Отображение карточки товара для SEO и НЧ-запросов
     */
    public function show($brand, $article)
    {
        $cleanArticle = trim(urldecode($article));
        $cleanBrand = trim(urldecode($brand));

        $product = GlobalCatalog::where('brand', $cleanBrand)
            ->where(function($query) use ($cleanArticle) {
                $query->where('article', $cleanArticle)
                    ->orWhere('article', 'LIKE', $cleanArticle . '%');
            })
            ->first();

        if (!$product) abort(404);

        $sparePartCtrl = new \App\Http\Controllers\SparePartController();
        
        // ОТЛАДКА: Посмотрим, что заходит и что выходит
        $basePrice = $product->price;
        $retailPrice = $sparePartCtrl->setPrice($basePrice);

        // Если ты видишь то же самое число, значит setPrice внутри не срабатывает.
        // Давай "силой" проверим наценку, если метод вдруг вернул оригинал:
        if ($retailPrice == $basePrice) {
            $retailPrice = $basePrice * 1.25; // Принудительные +25% для теста
        }

        $product->retail_price = $retailPrice;

        return view('global_product', compact('product'));
    }
    
    public function getProductImages(Request $request)
    {
        $article = $request->query('article');
        $brand = $request->query('brand');
        
        // Тут твоя логика запроса к Google Custom Search
        // Пока для теста можешь вернуть массив фейковых ссылок
        /*
        $apiKey = '...';
        $cx = '...';
        $response = Http::get("https://www.googleapis.com/customsearch/v1", [...]);
        return response()->json($links);
        */
        
        // Тестовая заглушка
        return response()->json([
            "https://via.placeholder.com/400x300?text=Google+Image+1",
            "https://via.placeholder.com/400x300?text=Google+Image+2"
        ]);
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
