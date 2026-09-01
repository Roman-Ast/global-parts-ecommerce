<?php

namespace App\Http\Controllers;

use App\Cart;
use App\Console\Commands\SeedOwnPartsCatalogCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (session()->has('cart')) {
            $cart = session()->get('cart');
            $cartContent = $cart->content();
            foreach ($cartContent as $cartItem) {
                $processedArr[] = [
                    'qty' => $cartItem['qty']
                ];
            } 
        } else {
            $cart = new Cart;
        }
        
        return view('cart', [
            'cartContent' => $processedArr ?? [],
            'cartTotal' => $cart->total() ?? []
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart');
        } else {
            $cart = new Cart();
        }

        //$duplicates = $cart->search($request->data['article']);
        
        /*Wif ($duplicates == 'bingo') {
            return json_encode([
                'items' => $cart->content(),
                'total' => $cart->totalWithMargine(),
                'count' => $cart->count(),
                'duplicates' => true
            ]);
        } else {
            $cart->add(
                $request->data['article'], $request->data['brand'], $request->data['name'], $request->data['originNumber'],
                $request->data['deliveryTime'],  $request->data['price'],  $request->data['qty'],  $request->data['stockFrom'], $request->data['priceWithMargine']
            );
        }*/
        $cart->add(
            $request->data['article'], $request->data['brand'], $request->data['name'], $request->data['searchedNumber'],
            $request->data['deliveryTime'],  $request->data['price'],  $request->data['qty'],  $request->data['stockFrom'], $request->data['priceWithMargine']
        );

        // Клиенту виден только обезличенный stockFrom — реальный
        // поставщик находится best-effort на сервере, для отображения
        // админу (см. GlobalProductController::resolveAdminSupplierName
        // за подробным разбором, та же логика).
        $lastIndex = count($cart->items) - 1;
        if ($lastIndex >= 0) {
            $cart->items[$lastIndex]['adminSupplierName'] = $this->resolveAdminSupplierName(
                (string) $request->data['article'],
                (string) $request->data['brand'],
                (float) $request->data['price']
            );
        }

        $request->session()->put('cart', $cart);
        
        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->totalWithMargine(),
            'count' => $cart->count(),
            'duplicates' => false
        ]);
    }

    /**
     * Display the specified resource.
     */
    
    public function updatePrice(Request $request)
    {
        $cart = $request->session()->get('cart');
        //return json_encode($cart->content());
        $article = $request->data['article'];
        $priceWithMargine = $request->data['priceWithMargine'];

        $cart->update($article, ['priceWithMargine' => $priceWithMargine]);

        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->totalWithMargine(),
            'count' => $cart->count()
        ]);
    }
/**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $cart = $request->session()->get('cart');
        $article = $request->data['article'];
        $qty = $request->data['qty'];

        $cart->update($article, ['qty' => $qty]);

        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->totalWithMargine(),
            'count' => $cart->count()
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function deleteItem(Request $request)
    {
        $cart = $request->session()->get('cart');

        $ost = $cart->remove($request->data['article']);

        return json_encode([
            'ost' => $ost,
            'items' => $cart->content(),
            'total' => $cart->totalWithMargine(),
            'count' => $cart->count()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function clear(Request $request)
    {
        $cart = $request->session()->get('cart');

        $cart->clear();

        return redirect('/');
    }

    /**
     * Best-effort поиск реального поставщика — та же логика, что и
     * GlobalProductController::resolveAdminSupplierName().
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
