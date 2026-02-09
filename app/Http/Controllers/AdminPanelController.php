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
use App\Models\OfficePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdminPanelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stats = $this->getDataByMonths();

        $today = Carbon::now();

		if ($today->day >= 8) {
			$start = Carbon::create($today->year, $today->month, 8)->startOfDay();
			$end = $start->copy()->addMonth()->subDay()->endOfDay(); // 7 число в 23:59:59
		} else {
			$end = Carbon::create($today->year, $today->month, 7)->endOfDay();
			$start = $end->copy()->subMonth()->addDay()->startOfDay(); // 8 число в 00:00:00
		}

		$orders = Order::whereBetween('date', [$start, $end])->orderBy('date', 'desc')->get();
        $user = auth()->user();
        $settlements = Setlement::all();
        $users = User::all();
        $payments = Payment::all();
        $sumOrders = $user->orders->sum('sum');
        $qtyOrders = $user->orders->count();
        $customers = Order::all()->where('customer_phone', !null)->pluck('customer_phone')->toArray();
        $supplerSettlements = SupplierSettlement::orderBy('created_at', 'desc')->get();
        $usersCalculating = [];
        $goods_in_office = OfficePrice::orderBy('id', 'desc')->get()->toArray();
        $goods_in_office_count = OfficePrice::sum('qty');
        $goods_in_office_sum = 0;

        foreach ($goods_in_office as $good) {
            $goods_in_office_sum += ($good['price'] * $good['qty']);
        }
        
        //сбор статистики продаж
        $sales_statistics = [
            'kaspi' => [],
            '2gis' => [],
            'olx' => [],
            'friends' => [],
            'site' => [],
            'repeat_request' => [],
        ];

        foreach ($sales_statistics as $sale_channel => $data) {
            $sales_statistics[$sale_channel]['totalSalesPrimeCostSum'] = Order::whereBetween('date', [$start, $end])->where('sale_channel', $sale_channel)->sum('sum');
            $sales_statistics[$sale_channel]['totalSalesSum'] = Order::whereBetween('date', [$start, $end])->where('sale_channel', $sale_channel)->sum('sum_with_margine');
            $sales_statistics[$sale_channel]['countOfSales'] = Order::whereBetween('date', [$start, $end])->where('sale_channel', $sale_channel)->count();
        }

        $totalSalesSum = Order::whereBetween('date', [$start, $end])->sum('sum_with_margine');
        $totalPrimeCostSum = Order::whereBetween('date', [$start, $end])->sum('sum');
        $totalCountOfSales = Order::whereBetween('date', [$start, $end])->count();
        $totalTax = round($totalSalesSum * 3 / 100);
        $kaspiComission = Order::whereBetween('date', [$start, $end])->where('sale_channel', 'kaspi')->sum('sum_with_margine') * 12 / 100;
        $marginClear = round($totalSalesSum - $totalPrimeCostSum - $totalTax - $kaspiComission);

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
            'avtozakup' => 'Автозакуп',
            'emex' => 'emex',
            'rlm' => 'Рулим',
            'radle' => 'Radle', 
            'fbst' => 'Фебест',
            'Krn' => 'Корея',
            'kln' => 'Кулан',
            'frmt' => 'Форумавто',
            'china_ata' => 'Китайцы Алматы',
            'china_igor' => 'Китай Игорь',
            'voltag_ast' => 'Вольтаж Астана',
            'kz_starter' => 'КЗ стартер',
            'cc_motors_talgat' => 'СС моторс Талгат',
            'gerat_ast' => 'Герат Астана',
            'kainar_razbor_tima' => 'Кайнар Тима',
            'zakaz_auto' => 'заказ авто',
            'thr' => 'Сторонние'
        ];

        $today = Carbon::today();
        $startPeriod = Carbon::create(2025, 4, 8); // начало учётного периода

        // 1️⃣ Получаем продажи с учётным месяцем
        $sales = SupplierSettlement::select(
                'supplier',
                DB::raw("
                    DATE_FORMAT(
                        CASE
                            -- текущий учётный месяц (с 8 числа текущего месяца по сегодня)
                            WHEN YEAR(`date`) = YEAR(CURDATE()) AND MONTH(`date`) = MONTH(CURDATE()) AND DAY(`date`) >= 8 THEN `date`
                            -- даты с 8 числа → текущий месяц
                            WHEN DAY(`date`) >= 8 THEN `date`
                            -- даты 1–7 числа → предыдущий месяц
                            ELSE DATE_SUB(`date`, INTERVAL 1 MONTH)
                        END,
                        '%m.%Y'
                    ) as accounting_month
                "),
                DB::raw('SUM(`sum`) as total')
            )
            ->whereDate('date', '>=', $startPeriod)
            ->whereDate('date', '<=', $today)
            ->groupBy('supplier', 'accounting_month')
            ->orderBy('supplier')
            ->orderByRaw("STR_TO_DATE(accounting_month, '%m.%Y') ASC")
            ->get();

        // 2️⃣ Собираем все учётные месяцы
        $allMonths = [];
        foreach ($sales as $sale) {
            $allMonths[$sale->accounting_month] = true;
        }

        // Убедимся, что текущий учётный месяц есть в списке
        $currentAccountingMonth = $today->day >= 8
            ? $today->format('m.Y')
            : $today->subMonth()->format('m.Y');

        $allMonths[$currentAccountingMonth] = true;

        // Преобразуем в массив и сортируем по дате
        $allMonths = array_keys($allMonths);
        usort($allMonths, function($a, $b) {
            return Carbon::createFromFormat('m.Y', $a)->timestamp <=> Carbon::createFromFormat('m.Y', $b)->timestamp;
        });

        // 3️⃣ Формируем массив поставщиков с нулями по всем месяцам
        $suppliers_settlements = [];

        foreach ($sales as $sale) {
            $supplier = $sale->supplier;
            $month = $sale->accounting_month;
            $sum = $sale->total;

            if (!isset($suppliers_settlements[$supplier])) {
                $suppliers_settlements[$supplier] = array_fill_keys($allMonths, 0);
            }

            $suppliers_settlements[$supplier][$month] = abs($sum);
        }

        // 4️⃣ Добавляем total
        foreach ($suppliers_settlements as $supplier => $months) {
            $suppliers_settlements[$supplier]['total'] = array_sum($months);
        }

        function cmp($a, $b) {
            if ($a == $b) {
                return 0;
            }
            return ($a < $b) ? -1 : 1;
        }

        //uasort(suppliers_settlements);

        //dd($suppliers_settlements);

        //статистика по дням недели за текущий период
        $startForDailyStats = now()->day >= 8
            ? now()->copy()->startOfMonth()->addDays(7)
            : now()->copy()->subMonth()->startOfMonth()->addDays(7);

        $endForDailyStats = $startForDailyStats->copy()->addMonth()->subDay();

        $ordersInPeriod = $orders->filter(function($order) use ($startForDailyStats, $endForDailyStats) {
            return $order->date >= $startForDailyStats && $order->date <= $endForDailyStats;
        });

        $dailyStats = [];
        $pointColors = [];

        $planPerDay = 300000;
        $upperThreshold = 390000;
        $actualSum = 0;

        foreach ($startForDailyStats->copy()->toPeriod($endForDailyStats) as $date) {
            $key = $date->format('d.m');

            $ordersOfDay = $ordersInPeriod->filter(function($order) use ($date) {
                return $order->date->isSameDay($date);
            });

            $sales = round($ordersOfDay->sum('sum_with_margine'), 2);
            $purchases = round($ordersOfDay->sum('sum'), 2);
            $actualSum += $sales;

            // Цвет точек по условию
            if ($sales < $planPerDay) {
                $pointColors[] = 'rgba(255, 99, 132, 1)'; // красный
            } elseif ($sales <= $upperThreshold) {
                $pointColors[] = 'rgba(255, 206, 86, 1)'; // жёлтый
            } else {
                $pointColors[] = 'rgba(75, 192, 192, 1)'; // зелёный
            }

            $dailyStats[$key] = [
                'sales' => $sales,
                'purchases' => $purchases,
            ];
        }

        $labels = array_keys($dailyStats);
        $salesData = array_column($dailyStats, 'sales');
        $purchaseData = array_column($dailyStats, 'purchases');
        $plannedSum = $planPerDay * $startForDailyStats->copy()->toPeriod($endForDailyStats)->filter(function($d) {
            return $d->lte(now());
        })->count();

        return view('admin/index', [
            'orders' => $orders,
            'settlements' => $settlements,
            'users' => $users,
            'payments' => $payments,
            'statuses' => $statuses,
            'usersCalculating' => $usersCalculating,
            'customers' => array_unique($customers),
            'supplerSettlements' => $supplerSettlements,
            'suppliers' => $suppliers,
            'suppliers_settlements' => $suppliers_settlements,
            'sales_statistics' => $sales_statistics,
            'totalSalesSum' => $totalSalesSum,
            'totalPrimeCostSum' => $totalPrimeCostSum,
            'totalCountOfSales' => $totalCountOfSales,
            'goods_in_office' => $goods_in_office,
            'goods_in_office_count' => $goods_in_office_count,
            'goods_in_office_sum' => $goods_in_office_sum,
            'totalTax' => $totalTax,
            'kaspiComission' => $kaspiComission,
            'marginClear' => $marginClear,
            'stats' => $stats,
            'labels' => $labels,
            'salesData' => $salesData,
            'purchaseData' => $purchaseData,
            'plannedSum' => $plannedSum,
            'actualSum' => $actualSum,
            'pointColors' => $pointColors,
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
            $product->itemSumWithMargine = 0;
            $product->save();

            $order_id = $product->order_id;
            $new_order_sum = OrderProduct::where('order_id', $order_id)->sum('item_sum');
            $newItemSumWithMargine = OrderProduct::where('order_id', $order_id)->sum('itemSumWithMargine');
            $order = Order::find($order_id); 
            $order->sum = $new_order_sum;
            $order->sum_with_margine = $newItemSumWithMargine;
            $order->save();

            $settlement = Setlement::where('order_id', $order_id)->first();
            $settlement->sum = $new_order_sum;
            $settlement->sumWithMargine = $newItemSumWithMargine;
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
    public function manuallyMakeOrder(Request $request)
    {
        $orderSumWithMargine = 0;
        $orderSum = 0;

        foreach ($request->data['products'] as $product) {
            $orderSumWithMargine += ($product[3] * $product[5]);
            $orderSum += ($product[3] * $product[4]);
        }
        
        $order = Order::create([
            'user_id' => $request->data['orderInfo'][0],
            'date' => date("d.m.Y", strtotime($request->data['orderInfo'][1])),
            'time' => date('H:i:s'),
            'sum' => $orderSum,
            'sum_with_margine' => $orderSumWithMargine,
            'status' => 'заказано',
            'customer_phone' => $request->data['orderInfo'][2],
            'sale_channel' => $request->data['orderInfo'][3]
        ]);
        
        foreach ($request->data['products'] as $product) {
            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'article' => $product[0],
                'brand' => $product[1],
                'name' => $product[2],
                'price' => $product[4],
                'priceWithMargine' => $product[5],
                'qty' => $product[3],
                'item_sum' => $product[4] * $product[3],
                'itemSumWithMargine' => $product[5] * $product[3],
                'searched_number' => '',
                'fromStock' => $product[6],
                'deliveryTime' => $product[7],
                'status' => 'payment_waiting'
            ]);
            $supplier_settlement = SupplierSettlement::create([
                'order_id' => $order->id,
                'product_id' => $orderProduct->id,
                'supplier' => $product[6],
                'sum' => -($product[4] * $product[3]),
                'date' => $request->data['orderInfo'][1],
                'operation' => 'realization'
            ]);
        }
        $settlement = Setlement::create([
            'order_id' => $order->id,
            'user_id' => $request->data['orderInfo'][0],
            'operation' => 'realization',
            'date' => $request->data['orderInfo'][1],
            'sum' => -$orderSum,
            'sumWithMargine' => -$orderSumWithMargine,
            'released' => true,
            'paid' => false
        ]);

        $order->setlement_id = $settlement->id;

        return [
            'message' => 'Заказ успешно создан!'
        ];
    }

    public function addNewGoodInOffice(Request $request)
    {
        $officePrice = OfficePrice::create([
            'oem' => $request->oem,
            'article' => $request->article,
            'brand' => $request->brand,
            'name' => $request->name,
            'price' => $request->price,
            'qty' => $request->qty
        ]);
        
        return back()->with('message', 'Товар успешно добавлен!')
            ->with('class', 'alert-succes');
    }

    public function getDataByMonths()
    {
        $orders = Order::all()->toArray();
        //dd($orders);
        $stats = $this->groupOrdersWithStatsByPeriod($orders);
        
        return $stats;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $deletingItem = OfficePrice::where('id', $request->data['deletingItemId'])->delete();
        
        return json_encode('success');
    }

    function groupOrdersByCustomMonth(array $orders): array
    {
            $grouped = [];

            foreach ($orders as $order) {
                // Парсим дату
                $date = Carbon::parse($order['date']);

                // Определяем, к какому отчетному месяцу относится заказ
                if ($date->day >= 8) {
                    $periodStart = Carbon::create($date->year, $date->month, 8)->startOfDay();
                } else {
                    $periodStart = Carbon::create($date->year, $date->month, 1)->subMonth()->day(8)->startOfDay();
                }

                $key = $periodStart->translatedFormat('F Y'); // например, "Апрель 2025" (если стоит локаль ru_RU)

                // Группировка по ключу
                $grouped[$key][] = $order;
            }

            return $grouped;
    }

    function groupOrdersWithStatsByPeriod(array $orders): array
    {
        $result = [];

        foreach ($orders as $order) {
            $date = Carbon::parse($order['date']);

            // Определяем начало отчетного периода
            if ($date->day >= 8) {
                $periodStart = Carbon::create($date->year, $date->month, 8)->startOfDay();
            } else {
                $periodStart = Carbon::create($date->year, $date->month, 1)->subMonth()->day(8)->startOfDay();
            }

            // Ключ периода (можно заменить на $periodStart->format('Y-m') для технической группировки)
            $periodKey = $periodStart->translatedFormat('F Y');

            // Инициализация, если впервые видим период
            if (!isset($result[$periodKey])) {
                $result[$periodKey] = [
                    'period_range' => $periodStart->toDateString() . ' по ' . $periodStart->copy()->addMonth()->subDay()->toDateString(),
                    'total_sales_sum' => 0,
                    'total_purchase_sum' => 0,
                    'order_count' => 0,
                    'channels' => [] // для sale_channel
                ];
            }

            // Общие данные по периоду
            $result[$periodKey]['total_sales_sum'] += $order['sum_with_margine'];
            $result[$periodKey]['total_purchase_sum'] += $order['sum'];
            $result[$periodKey]['order_count']++;

            // Канал продаж
            $channel = $order['sale_channel'] ?? 'неизвестно';

            if (!isset($result[$periodKey]['channels'][$channel])) {
                $result[$periodKey]['channels'][$channel] = [
                    'sales_sum' => 0,
                    'purchase_sum' => 0,
                    'order_count' => 0
                ];
            }

            $result[$periodKey]['channels'][$channel]['sales_sum'] += $order['sum_with_margine'];
            $result[$periodKey]['channels'][$channel]['purchase_sum'] += $order['sum'];
            $result[$periodKey]['channels'][$channel]['order_count']++;
        }

        return $result;
    }
}
