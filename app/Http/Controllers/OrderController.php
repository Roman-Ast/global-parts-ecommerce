<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Setlement;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Laravel\Ui\Presets\React;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $user = auth()->user();
        
        $orders = $user->orders;
        
        return view('orders', [
            'orders' => $orders
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function products(Request $request)
    {
        $order = Order::find($request->data['order_id']);
        $orderSum = $order->sum;
        $products = $order->products;

        return json_encode([
            'products' => $products,
            'orderSum' => $orderSum,
            'orderId' => $order->id
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $cart = $request->session()->get('cart');

        //dd($cart);
        $order = Order::create([
            'user_id' => $request->user_id,
            'date' => date('d.m.y'),
            'time' => date('H:i:s'),
            'sum' => $cart->total(),
            'status' => 'заказано'
        ]);
        
        foreach ($cart->content() as $cartItem) {
            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'article' => $cartItem['article'],
                'brand' => $cartItem['brand'],
                'name' => $cartItem['name'],
                'price' => $cartItem['price'],
                'qty' => $cartItem['qty'],
                'item_sum' => $cartItem['price'] * $cartItem['qty'],
                'searched_number' => $cartItem['originNumber'],
                'fromStock' => $cartItem['stockFrom'],
                'deliveryTime' => $cartItem['deliveryTime'],
            ]);
        }

        $settlement = Setlement::create([
            'order_id' => $order->id,
            'user_id' => $request->user_id,
            'operation' => 'realization',
            'date' => date('d.m.y'),
            'sum' => $cart->total(),
            'released' => true,
            'paid' => false
        ]);
        
        $order->setlement_id = $settlement->id;
        
        $cart->clear();

        return view('index')
            ->with('success_message', '!')
            ->with('class', 'alert-success');
        
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
       //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
