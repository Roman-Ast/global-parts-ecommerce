<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GlobalCatalog;
use Illuminate\Support\Facades\DB;

class GlobalProductController extends Controller
{
    /**
     * Отображение карточки товара для SEO и НЧ-запросов
     */
    public function show($brand, $article)
	{
		$rawArticle = $article; // сохраняем для логов или виртуалки
		$searchArticle = preg_replace('/[^A-Za-z0-9]/', '', urldecode($article));
		$cleanBrand = trim(urldecode($brand));

		// 2. Ищем в базе
		$product = GlobalCatalog::where(DB::raw('UPPER(TRIM(brand))'), strtoupper($cleanBrand))
			->where('clean_article', $searchArticle)
			->first();
	   // Если нашли реальный товар в базе
        if ($product && isset($product->clean_article)) {
            
            // Сравниваем то, что пришло в URL, с тем, как должно быть (clean_article)
            // Мы декодируем входящий артикул, чтобы убрать %2F и прочее для сравнения
            $currentArticle = urldecode($article);
            
            if ($currentArticle !== $product->clean_article) {
                // Делаем редирект 301 на чистую ссылку
                return redirect()->route('product.show', [
                    'brand' => $product->brand,
                    'article' => $product->clean_article
                ], 301);
            }
        }

		// 3. Резервный поиск (если по clean_article не нашли)
		if (!$product) {
			$product = \App\Models\GlobalCatalog::where(DB::raw('UPPER(TRIM(brand))'), strtoupper($cleanBrand))
				->where(function($query) use ($searchArticle) {
					// Очищаем колонку article от пробелов, тире и слэшей прямо в базе
					$query->where(DB::raw("REPLACE(REPLACE(REPLACE(article, ' ', ''), '-', ''), '/', '')"), $searchArticle)
						  // И на всякий случай ищем по началу артикула
						  ->orWhere('article', 'LIKE', $searchArticle . '%');
				})
				->first();
		}

		// --- РЕДИРЕКТ: Если нашли товар, но ссылка "грязная" ---
		if ($product && $article !== $product->clean_article) {
			return redirect()->route('product.show', [
				'brand' => $product->brand,
				'article' => $product->clean_article
			], 301);
		}

	                   // --- РЕДИРЕКТ 301 ДЛЯ ГУГЛА ---
        // Если товар найден и это не "заглушка"
        if ($product && !isset($product->is_virtual)) {
            // Получаем текущий путь из браузера (декодируем, чтобы видеть / вместо %2F)
            $currentPath = urldecode(request()->path()); 
            // Формируем правильный путь, который должен быть
            $correctPath = "product/" . $product->brand . "/" . $product->clean_article;

            // Если пути не совпадают — гоним Гугл на правильный адрес
            if ($currentPath !== $correctPath) {
                return redirect()->to($correctPath, 301);
            }
        }
	   
		// 4. Если всё равно не нашли — создаем виртуальный объект
		if (!$product) {
			$product = new \stdClass();
			$product->brand = $cleanBrand;
			$product->article = $rawArticle;
			$product->name = "Запчасть " . $cleanBrand . " " . $rawArticle;
			$product->price = 0;
			$product->qty = 0;
			$product->is_virtual = true;
			$product->placeholder_url = "https://shop.globalparts.kz/images/placeholders/default_gear.jpeg";
		} else {
			$product->is_virtual = false;
		}

		// --- ЦЕНЫ ---
		$sparePartCtrl = new \App\Http\Controllers\SparePartController();
		$basePrice = $product->price;
		$retailPrice = $sparePartCtrl->setPrice($basePrice);

		if ($retailPrice == $basePrice && $basePrice > 0) {
			$retailPrice = $basePrice * 1.25; 
		}
		$product->retail_price = $retailPrice;

		// Рекомендации
		$recommended = GlobalCatalog::where('brand', $cleanBrand)
			->where('clean_article', '!=', $searchArticle)
			->inRandomOrder()                  
			->take(10)                         
			->get();

        $canonicalUrl = route('product.show', [
            'brand' => $product->brand,
            'article' => $product->clean_article ?? $product->article
        ]);

		return view('global_product', compact('product', 'recommended', 'canonicalUrl'));
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
