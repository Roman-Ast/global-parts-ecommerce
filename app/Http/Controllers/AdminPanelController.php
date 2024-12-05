<?php

namespace App\Http\Controllers;

use App\Models\AdminPanel;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Payment;
use App\Models\Setlement;
use App\Models\SupplierSettlement;
use App\Models\User;
use Illuminate\Http\Request;

class AdminPanelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $orders = Order::orderBy('created_at', 'desc')->get();
        
        $settlements = Setlement::all();
        $users = User::all();
        $payments = Payment::all();
        $sumOrders = $user->orders->sum('sum');
        $qtyOrders = $user->orders->count();
        $customers = Order::all()->where('customer_phone', !null)->pluck('customer_phone');
        $supplerSettlements = SupplierSettlement::orderBy('created_at', 'desc')->get();
        
        $usersCalculating = [];

        foreach ($users as $user) {
            $usersCalculating[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->user_role,
                'sumOrders' => $user->orders->sum('sum'),
                'qtyOrders' => $user->orders->count(),
            ];
        }
        
        $statuses = [
            'payment_waiting' => 'ожидание оплаты', 'processing' => 'принято в работу', 'supplier_refusal' => 'отказ поставщика',
            'arrived_at_the_point_of_delivery' => "поступило в ПВЗ", 'issued' => "выдано", 'returned' => 'возвращено'
        ];

        $suppliers = [
            'shtm' => 'Шатэ-М',
            'rssk' => 'Росско',
            'trd' => 'Автотрейд',
            'tss' => 'Тисс',
            'rmtk' => 'Армтек',
            'phtn' => 'Фаэтон',
            'atptr' => 'Автопитер',
            'rlm' => 'Рулим',
            'leopart' => 'Леопарт', 
            'fbst' => 'Фебест',
            'Krn' => 'Корея',
            'thr' => 'Сторонние'
        ];

        $suppliers_debt = [
            'shtm' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'shtm')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'shtm')->where('operation', 'payment')->sum('sum'),
            ],
            'rssk' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'rssk')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'rssk')->where('operation', 'payment')->sum('sum'),
            ],
            'trd' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'trd')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'trd')->where('operation', 'payment')->sum('sum'),
            ],
            'tss' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'tss')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'tss')->where('operation', 'payment')->sum('sum'),
            ],
            'rmtk' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'rmtk')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'rmtk')->where('operation', 'payment')->sum('sum'),
            ],
            'phtn' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'phtn')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'phtn')->where('operation', 'payment')->sum('sum'),
            ],
            'atptr' => [
                'ralizationSum' => SupplierSettlement::where('supplier', 'atptr')->where('operation', 'realization')->sum('sum'),
                'pay' => SupplierSettlement::where('supplier', 'atptr')->where('operation', 'payment')->sum('sum'),
            ],
        ];

        return view('admin/index', [
            'orders' => $orders,
            'settlements' => $settlements,
            'users' => $users,
            'payments' => $payments,
            'statuses' => $statuses,
            'usersCalculating' => $usersCalculating,
            'customers' => $customers,
            'supplerSettlements' => $supplerSettlements,
            'suppliers' => $suppliers,
            'suppliers_debt' => $suppliers_debt
        ]);
    }

    public function pay(Request $request)
    {
        $payment = Payment::create([
            'user_id' => $request->user_id,
            'date' => date('d.m.y', strtotime($request->date)),
            'sum' => $request->sum,
            'payment_method' => $request->payment_method,
            'comments' => $request->comments,
        ]);

        $settlement = Setlement::create([
            'user_id' => $request->user_id,
            'order_id' => $payment->id,
            'operation' => 'payment',
            'date' => date('d.m.y', strtotime($request->date)),
            'sum' => $request->sum,
            'released' => false,
            'paid' => true
        ]);
        
        return back()
            ->with('success_message', 'Оплата успешно проведена!')
            ->with('class', 'alert-success');
    }

    public function supplierPayment(Request $request)
    {
        $supplier_settlement = SupplierSettlement::create([
            'supplier' => $request->supplier,
            'sum' => $request->sum,
            'date' => date('d.m.y'),
            'operation' => 'payment'
        ]);

        return back()
            ->with('message', 'Оплата успешно проведена!')
            ->with('class', 'alert-success');
    }
    /**
     * Store a newly created resource in storage.
     */
    public function filter(Request $request)
    {
        $dateFrom = $request->data['date_from'];
        $dateTo = $request->data['date_to'];
        
        foreach ($request->data as $key => $value) {
            if ($value && $key != 'date_from' && $key != 'date_to') {
                $needThirdParametr = true;
                $thirdParametrKey = $key;
                $thirdParametrValue = $value;
            }
        }
        
        $filteredOrders = [];
        
        if (isset($needThirdParametr)) {
            $filteredOrders = Order::where($thirdParametrKey, $thirdParametrValue)
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->latest()
                ->get();
            
            foreach ($filteredOrders as $order) {
                $products = [];
                    
                foreach ($order->products as $product) {
                    array_push($products, $product);
                }
                $order->products = $products;
            }
        } else {
            $filteredOrders = Order::whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo)
                ->latest()
                ->get();
            
            foreach ($filteredOrders as $order) {
                $products = [];
                        
                foreach ($order->products as $product) {
                    array_push($products, $product);
                }
                $order->products = $products;
            }
        }
        foreach ($filteredOrders as $order) {
            $order['user_name'] = $order->user->name;
        }
        
        return json_encode([
            'filtered_orders' => $filteredOrders
        ]);
    }

    public function filterDrop(Request $request)
    {
        $orders = Order::latest()->get();

        foreach ($orders as $order) {
            $products = [];
                
            foreach ($order->products as $product) {
                array_push($products, $product);
            }
        }

        foreach ($orders as $order) {
            $order['user_name'] = $order->user->name;
        }

        return [
            'orders' => $orders
        ];
    }
    /**
     * Display the specified resource.
     */
    public function changeStatus(Request $request)
    {
        $data = $request['data'];
        $product = OrderProduct::find($data['product_id']);
        
        if($data['new_status'] == 'returned') {
            $product->status = $data['new_status'];
            $product->item_sum = 0;
            $product->save();

            $order_id = $product->order_id;
            $new_order_sum = OrderProduct::where('order_id', $order_id)->sum('item_sum');
            $order = Order::find($order_id); 
            $order->sum = $new_order_sum;
            $order->save();

            $settlement = Setlement::where('order_id', $order_id)->first();
            $settlement->sum = $new_order_sum;
            $settlement->save();

            $supplierSettlement = SupplierSettlement::where('product_id', $product->id)->delete();
            $supplierSettlement->save();
        } else {
            $product->status = $data['new_status'];
            $product->save();
        }
        

        return [
            'message' => 'Статус успешно изменен!',
            'status' => $data['new_status']
        ];
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AdminPanel $adminPanel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdminPanel $adminPanel)
    {
        //
    }
}
