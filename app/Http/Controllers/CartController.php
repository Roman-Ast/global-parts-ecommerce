<?php

namespace App\Http\Controllers;

use App\Cart;
use Illuminate\Http\Request;

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

        $duplicates = $cart->search($request->data['article']);
        //return json_encode($duplicates);
        if ($duplicates == 'bingo') {
            return json_encode([
                'items' => $cart->content(),
                'total' => $cart->total(),
                'count' => $cart->count(),
                'duplicates' => true
            ]);
        } else {
            $cart->add(
                $request->data['article'], $request->data['brand'], $request->data['name'],
                $request->data['deliveryTime'],  $request->data['price'],  $request->data['qty'],  $request->data['stockFrom']
            );
        }
        
        $request->session()->put('cart', $cart);
        
        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->total(),
            'count' => $cart->count(),
            'duplicates' => false
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cart $cart)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function deleteItem(Request $request)
    {
        $cart = $request->session()->get('cart');

        $ost = $cart->remove($request->data['article']);

        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->total(),
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

        $cart->update($article, $qty);

        return json_encode([
            'items' => $cart->content(),
            'total' => $cart->total(),
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

        return view('index');
    }
}
