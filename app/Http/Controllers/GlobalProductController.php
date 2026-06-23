<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlobalCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Helpers\SlugHelper;

class GlobalProductController extends Controller
{
    /**
     * Твой бронебойный метод SHOW
     */
    public function show($brand, $article)
    {
        // 1. ДЕЗИНФЕКЦИЯ
        $rawBrand = urldecode($brand);
        $rawArticle = urldecode($article);

        // Лечим раскладку
        $cyrillic = ['С', 'А', 'Е', 'О', 'Р', 'К', 'Х', 'В', 'а', 'е', 'о', 'р', 'к', 'х', 'с'];
        $latin    = ['C', 'A', 'E', 'O', 'P', 'K', 'X', 'B', 'a', 'e', 'o', 'p', 'k', 'x', 'c'];
        $decodedArticle = str_replace($cyrillic, $latin, $rawArticle);

        // Убираем всё, кроме дефисов и подчеркиваний
        $badSymbols = ['/', '+', '*', '(', ')', '"', "'", '&', '!', '#', ',', '?', '%2F', '%2B', '%26', '%3F'];
        $cleanArticle = str_replace($badSymbols, '', $decodedArticle);
        
        // Для бренда: заменяем мусор на дефис, но НЕ трогаем существующие дефисы слишком жестко
        $cleanBrand = str_replace($badSymbols, '-', $rawBrand);
        $cleanBrand = trim($cleanBrand, '- ');

        $cleanArticle = trim($cleanArticle);

        if (empty($cleanArticle) || str_contains(strtoupper($cleanArticle), 'E+')) {
            return $this->renderProduct($this->createVirtual($cleanBrand, $cleanArticle), 404);
        }

        // Артикул для поиска: буквы, цифры и подчеркивание (G4KE_1)
        $searchArticle = preg_replace('/[^A-Za-z0-9_]/', '', $cleanArticle);

        // 2. ПОИСК В БАЗЕ (Улучшенный)
        // Мы ищем, игнорируя регистр и лишние пробелы. 
        // Если в базе 'Chery-Exeed', а в URL 'Chery-Exeed' — теперь точно найдет.
        $product = GlobalCatalog::where(function($query) use ($cleanBrand) {
            $query->where(DB::raw('UPPER(REPLACE(brand, " ", "-"))'), strtoupper($cleanBrand))
                  ->orWhere(DB::raw('UPPER(brand)'), strtoupper($cleanBrand));
        })
        ->where('clean_article', $searchArticle)
        ->first();

        // 3. РЕДИРЕКТ И ЦЕНА
        if ($product) {
            $canonicalBrand = SlugHelper::brandToSlug($product->brand);
            
            $correctPath = 'product/' . $canonicalBrand . '/' . $product->clean_article;
            $currentPath = urldecode(request()->path());

            if (!empty($canonicalBrand) && $currentPath !== $correctPath) {
                return redirect()->to(url($correctPath), 301);
            }

            $product->retail_price = $this->setPrice($product->price);
            
            return $this->renderProduct($product, 200, url($correctPath));
        }

        // Продукт не найден — виртуальный
        $product = $this->createVirtual($cleanBrand, $cleanArticle);
        return $this->renderProduct($product, 404, url()->current());
    }

    /**
     * Твой метод наценки (он в этом же классе!)
     */
    public function setPrice($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.2; 
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.2;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 1.9;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.55;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.42;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.39;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.35;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.31;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.295;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.265;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.24;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.22;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.21;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.216;
        }

        return $priceWithMargin;
    }

    /**
     * Создание виртуального объекта
     */
    private function createVirtual($brand, $article)
    {
        $virtual = new \stdClass();
        $virtual->id = 0;
        $virtual->name = "Запчасть " . $article;
        $virtual->brand = strtoupper($brand);
        $virtual->article = $article;
        $virtual->clean_article = $article;
        $virtual->price = 0;
        $virtual->retail_price = 0; // ← теперь после new \stdClass()
        $virtual->is_virtual = true;
        return $virtual;
    }

    /**
     * Метод рендеринга (чтобы не дублировать код)
     */
    private function renderProduct($product, $status)
    {
        $recommended = GlobalCatalog::where('brand', 'LIKE', $product->brand . '%')
            ->where('clean_article', '!=', $product->clean_article)
            ->limit(10) // Просто берем первые 10
            ->get();

        // Прогоняем цены рекомендаций через наценку
        foreach ($recommended as $item) {
            $item->retail_price = $this->setPrice($item->price);
        }

        if ($product && isset($product->brand)) {
            $product->brand = \Illuminate\Support\Str::upper($product->brand);
        }
        return response()->view('global_product', [
            'product' => $product,
            'recommended' => $recommended,
            'canonicalUrl' => url()->current()
        ], $status);
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