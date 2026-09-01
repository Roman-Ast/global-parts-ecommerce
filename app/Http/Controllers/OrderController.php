<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Setlement;
use App\Models\SupplierSettlement;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlaced;
use Laravel\Ui\Presets\React;
use App\Traits\HasCustomerLogic;

class OrderController extends Controller
{
    use HasCustomerLogic;
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
    // Единственный склад/пункт выдачи — тот же адрес, что и в Halyk
    // (HALYK_POINT_CODE=Global_Parts_pp1), см. CLAUDE.md.
    const PICKUP_ADDRESS = 'мкр. Целинный 5/1, ТД Акку, офис 6';

    public function store(Request $request)
    {
        $request->validate([
            'customer_phone' => [
                'required',
                // Регулярка проверяет формат +7 (7xx) xxx-xx-xx
                'regex:/^\+7\s\(\d{3}\)\s\d{3}-\d{2}-\d{2}$/'
            ],
            'name' => 'required|string|max:255',
            'delivery_type' => 'required|in:pickup,delivery',
            'city' => 'required_if:delivery_type,delivery|nullable|string',
            'address' => 'required_if:delivery_type,delivery|nullable|string',
        ], [
            'customer_phone.regex' => 'Введите номер телефона в формате +7 (7xx) xxx-xx-xx',
        ]);
        $cart = $request->session()->get('cart');

        $customer = $this->getOrCreateCustomer($request->customer_phone, $request->name);

        $isPickup = $request->delivery_type === 'pickup';

        $order = Order::create([
            'user_id' => auth()->id(),
            'customer_id' => $customer?->id, // Привязка к CRM
            'date' => date('d.m.Y'),
            'time' => date('H:i:s'),
            'sum' => $cart->total(),
            'sum_with_margine' => $cart->totalWithMargine(),
            'status' => 'заказано',
            'customer_phone' => $customer?->phone ?? $request->customer_phone,
            'sale_channel' => 'site',
            'delivery_type' => $request->delivery_type,
            // При самовывозе адрес — только наш, серверный (не то, что
            // могло прийти от клиента) — исключает любое расхождение.
            'city' => $isPickup ? 'Астана' : $request->city,
            'address' => $isPickup ? self::PICKUP_ADDRESS : $request->address,
            'vin' => $request->vin,
            'comment' => $request->comment,
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
                'admin_supplier_name' => $cartItem['adminSupplierName'] ?? null,
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

        //sending the transactional email
        if(auth()->user()->user_role != 'admin') {
            Mail::send(new OrderPlaced($order));
        }
        
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

    public function checkout(Request $request)
    {
        $cart = $request->session()->get('cart');
        
        if (!$cart || $cart->count() == 0) {
            return redirect()->route('cart.index');
        }

        // Предзаполняем данные, если клиент залогинен
        $user = auth()->user();
        
        return view('checkout', compact('cart', 'user'));
    }
}
