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

        $subRequest = new Request([
            'partnumber' => $article,
            'brand'      => $brand,
            'rossko_need_to_search' => false,
            'only_on_stock' => false
        ]);

        $sparePartCtrl = new \App\Http\Controllers\SparePartController();
        $sparePartCtrl->getSearchedPartAndCrosses($subRequest);

        $reflection = new \ReflectionClass($sparePartCtrl);
        $property = $reflection->getProperty('finalArr');
        $property->setAccessible(true);
        $finalData = $property->getValue($sparePartCtrl);

        // Собираем всё в один массив
        $all = array_merge(
            $finalData['searchedNumber'] ?? [],
            $finalData['crosses_on_stock'] ?? [],
            $finalData['crosses_to_order'] ?? []
        );

        // 1. Сначала делаем "плоский" массив, как у тебя и было
        $cleanOffers = [];
        foreach ($all as $item) {
            $cleanOffers[] = [
                'brand'   => strtoupper((string)($item['brand'] ?? '')), // В верхний регистр для точности
                'article' => (string)($item['article'] ?? ''),
                'name'    => mb_convert_encoding((string)($item['name'] ?? ''), 'UTF-8', 'UTF-8'),
                'qty'     => (int)($item['qty'] ?? 0),
                'price'   => $item['price'] ?? 0, // Не забудь про себестоимость для ERP
                'priceWithMargine' => (int)($item['priceWithMargine'] ?? 0),
                'delivery_time'    => (string)($item['delivery_time'] ?? ($item['deliveryStart'] ?? '1-2 дня')),
                'supplier_city'    => (string)($item['supplier_city'] ?? 'Склад')
            ];
        }

        // 2. А теперь "умная" группировка через коллекции
        $processed = collect($cleanOffers)
            ->groupBy(function($item) {
                // Убираем лишние символы из артикула для точного сравнения (ST-1040 == ST1040)
                $cleanArt = preg_replace('/[^A-Za-z0-9]/', '', $item['article']);
                return $item['brand'] . '|' . $cleanArt;
            })
            ->map(function($group) {
                // Для каждой группы (например, все Sufix ST1040) 
                // сначала ищем, есть ли что-то в наличии в Астане
                $inAstana = $group->first(function($item) {
                    return $item['supplier_city'] === 'ast' || $item['delivery_time'] === '1.5-2 часа';
                });

                // Если есть в Астане - берем его, если нет - берем самый дешевый вариант из РФ/ОАЭ
                return $inAstana ?: $group->sortBy('priceWithMargine')->first();
            })
            ->sortBy('priceWithMargine') // Сортируем весь итоговый список от дешевых к дорогим
            ->values();

        // 3. Возвращаем чистый JSON
        return response()->json(
            ['offers' => $processed], 
            200, 
            [], 
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

    } catch (\Throwable $e) {
        // Если упало, мы ХОТИМ видеть текст ошибки
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
}
}
