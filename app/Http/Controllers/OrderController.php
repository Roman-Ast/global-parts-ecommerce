<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Setlement;
use App\Models\SupplierSettlement;
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
        $orders = Order::where('user_id', auth()->user()->id)->latest()->get();
        
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
        
        $order = Order::create([
            'user_id' => $request->user_id,
            'date' => date('d.m.y'),
            'time' => date('H:i:s'),
            'sum' => $cart->total(),
            'sum_with_margine' => $cart->totalWithMargine(),
            'status' => 'заказано',
            'customer_phone' => $request->customer_phone
        ]);
        
        foreach ($cart->content() as $cartItem) {
            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'article' => $cartItem['article'],
                'brand' => $cartItem['brand'],
                'name' => $cartItem['name'],
                'price' => $cartItem['price'],
                'priceWithMargine' => $cartItem['priceWithMargine'],
                'qty' => $cartItem['qty'],
                'item_sum' => $cartItem['price'] * $cartItem['qty'],
                'itemSumWithMargine' => $cartItem['priceWithMargine'] * $cartItem['qty'],
                'searched_number' => $cartItem['originNumber'],
                'fromStock' => $cartItem['stockFrom'],
                'deliveryTime' => $cartItem['deliveryTime'],
                'status' => 'payment_waiting'
            ]);
            $supplier_settlement = SupplierSettlement::create([
                'order_id' => $order->id,
                'product_id' => $orderProduct->id,
                'supplier' => $cartItem['stockFrom'],
                'sum' => -($cartItem['price'] * $cartItem['qty']),
                'date' => date('d.m.y'),
                'operation' => 'realization'
            ]);
        }

        $settlement = Setlement::create([
            'order_id' => $order->id,
            'user_id' => $request->user_id,
            'operation' => 'realization',
            'date' => date('d.m.y'),
            'sum' => -$cart->total(),
            'sumWithMargine' => -$cart->totalWithMargine(),
            'released' => true,
            'paid' => false
        ]);

        $order->setlement_id = $settlement->id;
        $cart->clear();

        return redirect('orders')
            ->with('message', 'Ваш заказ успешно создан!')
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
