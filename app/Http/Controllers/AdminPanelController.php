<?php

namespace App\Http\Controllers;

use App\Models\AdminPanel;
use App\Models\Suppliers;
use App\Models\Accounts;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderPayment;
use App\Models\CashflowTransactions;
use App\Models\CashflowCategories;
use App\Models\ExpenseCategories;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\Payment;
use App\Models\Setlement;
use App\Models\SupplierSettlement;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\OfficePrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use App\Traits\HasCustomerLogic;
use App\Models\SupplierCredit;

class AdminPanelController extends Controller
{
    use HasCustomerLogic;

    /**
     * Дата, с которой ERP реально в строю (2026-09-17, дата прогона
     * миграций на проде) — заказы ДО этой даты оформлялись без учёта
     * оплат через order_payments, поэтому у них "оплачено=0" не значит
     * "клиент должен", просто платёж никогда не заносился в систему.
     * Без этой отсечки getReceivablesData() тянул ВСЮ историю заказов
     * (Order::all()) в "Дебиторка от клиентов" — живой случай 2026-09-17,
     * дашборд показал 151 617 137₸ дебиторки вместо реальных цифр.
     * Конкретные реальные долги с ДО этой даты — заносятся вручную через
     * форму "Начальные остатки" (opening-balances.store), не через это.
     */
    const ERP_GO_LIVE_DATE = '2026-09-17';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //выгружаем данные по продажам за весь период
        $salesSumFromBegin = Order::sum('sum_with_margine');
        $primeCostSumFromBegin = Order::sum('sum');
        $countOfSalesFromBegin = Order::count();
        $totalItemsSoldFromBegin = OrderProduct::count();
        // kaspi_bypassed — заказы, оформленные мимо магазина Kaspi, реальной
        // комиссии не несут, хоть канал и "kaspi" (просьба Романа 2026-09-18).
        $kaspiComissionFromBegin = Order::where('sale_channel', 'kaspi')->where('kaspi_bypassed', false)->sum('sum_with_margine') * 12 / 100;
        $marginClearFromBegin = round($salesSumFromBegin - $primeCostSumFromBegin - $kaspiComissionFromBegin);

        //выгружаем данные продаж по каналам за весь период
        $sales_statistics_from_begin = [
            'kaspi' => [],
            '2gis' => [],
            'olx' => [],
            'friends' => [],
            'site' => [],
            'repeat_request' => [],
        ];

        foreach ($sales_statistics_from_begin as $sale_channel => $data) {
            $sales_statistics_from_begin[$sale_channel]['totalSalesPrimeCostSum'] = Order::where('sale_channel', $sale_channel)->sum('sum');
            $sales_statistics_from_begin[$sale_channel]['totalSalesSum'] = Order::where('sale_channel', $sale_channel)->sum('sum_with_margine');
            $sales_statistics_from_begin[$sale_channel]['countOfSales'] = Order::where('sale_channel', $sale_channel)->count();
        }

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
        //$settlements = Setlement::all();
        $users = User::all();
        $payments = Payment::all();
        $sumOrders = $user->orders->sum('sum_with_margine');
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

        // Помесячная статистика по каналам продаж (учётный период — с 8 числа по 7-е)
        $channelStatsRaw = Order::select(
                'sale_channel',
                DB::raw("
                    DATE_FORMAT(
                        CASE
                            WHEN DAY(`date`) >= 8 THEN `date`
                            ELSE DATE_SUB(`date`, INTERVAL 1 MONTH)
                        END,
                        '%m.%Y'
                    ) as accounting_month
                "),
                DB::raw('SUM(sum_with_margine) as total_sales_sum'),
                DB::raw('SUM(sum) as total_prime_cost_sum'),
                DB::raw('COUNT(*) as count_of_sales')
            )
            ->whereNotNull('sale_channel')
            ->groupBy('sale_channel', 'accounting_month')
            ->get();

        $channelKeys = ['kaspi', '2gis', 'olx', 'friends', 'site', 'satu', 'repeat_request'];

        $salesStatisticsByMonth = [];

        foreach ($channelStatsRaw as $row) {
            $month   = $row->accounting_month;
            $channel = $row->sale_channel;

            if (!isset($salesStatisticsByMonth[$month])) {
                $salesStatisticsByMonth[$month] = [];
                foreach ($channelKeys as $ck) {
                    $salesStatisticsByMonth[$month][$ck] = [
                        'totalSalesSum'          => 0,
                        'totalSalesPrimeCostSum' => 0,
                        'countOfSales'           => 0,
                    ];
                }
            }

            if (in_array($channel, $channelKeys, true)) {
                $salesStatisticsByMonth[$month][$channel] = [
                    'totalSalesSum'          => (float) $row->total_sales_sum,
                    'totalSalesPrimeCostSum' => (float) $row->total_prime_cost_sum,
                    'countOfSales'           => (int) $row->count_of_sales,
                ];
            }
        }

        // Сортируем месяцы от новых к старым для выпадающего списка
        $monthsSorted = array_keys($salesStatisticsByMonth);
        usort($monthsSorted, function ($a, $b) {
            return Carbon::createFromFormat('m.Y', $b)->timestamp <=> Carbon::createFromFormat('m.Y', $a)->timestamp;
        });

        // Текущий учётный месяц — выбран по умолчанию в UI
        $currentAccountingMonthKey = $today->day >= 8
            ? $today->format('m.Y')
            : $today->copy()->subMonth()->format('m.Y');

        $totalSalesSum = Order::whereBetween('date', [$start, $end])->sum('sum_with_margine');
        $totalPrimeCostSum = Order::whereBetween('date', [$start, $end])->sum('sum');
        $totalCountOfSales = Order::whereBetween('date', [$start, $end])->count();

        // kaspi_bypassed — см. пояснение у $kaspiComissionFromBegin выше.
        $kaspiComission = Order::whereBetween('date', [$start, $end])
            ->where('sale_channel', 'kaspi')
            ->where('kaspi_bypassed', false)
            ->sum('sum_with_margine') * 12.5 / 100;

        // Закреплённый пункт "Весь период" — берём из уже посчитанной статистики с начала работы
        $salesStatisticsByMonth['all'] = [];
        foreach ($channelKeys as $ck) {
            $salesStatisticsByMonth['all'][$ck] = [
                'totalSalesSum'          => (float) ($sales_statistics_from_begin[$ck]['totalSalesSum'] ?? 0),
                'totalSalesPrimeCostSum' => (float) ($sales_statistics_from_begin[$ck]['totalSalesPrimeCostSum'] ?? 0),
                'countOfSales'           => (int) ($sales_statistics_from_begin[$ck]['countOfSales'] ?? 0),
            ];
        }

        $marginClear = round($totalSalesSum - $totalPrimeCostSum - $kaspiComission);

        // Помесячная статистика по поставщикам (тот же учётный период —
        // с 8 числа по 7-е, что и у каналов продаж выше), для таблицы
        // "Статистика по поставщикам" в админке. Группируем по
        // op.fromStock (текстовое имя поставщика на order_product), а не
        // supplier_id — исторически supplier_id заполнялся не всегда,
        // fromStock проставляется на каждой позиции с самого начала (см.
        // AdminPanelController::manuallyMakeOrder()).
        $supplierStatsRaw = DB::table('order_product as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->select(
                'op.fromStock as supplier_name',
                DB::raw("
                    DATE_FORMAT(
                        CASE
                            WHEN DAY(o.date) >= 8 THEN o.date
                            ELSE DATE_SUB(o.date, INTERVAL 1 MONTH)
                        END,
                        '%m.%Y'
                    ) as accounting_month
                "),
                DB::raw('COUNT(*) as positions_count'),
                DB::raw('COUNT(DISTINCT op.order_id) as orders_count'),
                DB::raw('SUM(op.item_sum) as total_cost_sum')
            )
            ->whereNotNull('op.fromStock')
            ->where('op.fromStock', '!=', '')
            ->groupBy('op.fromStock', 'accounting_month')
            ->get();

        $supplierStatisticsByMonth = [];
        foreach ($supplierStatsRaw as $row) {
            $supplierStatisticsByMonth[$row->accounting_month][$row->supplier_name] = [
                'positionsCount' => (int) $row->positions_count,
                'ordersCount'    => (int) $row->orders_count,
                'totalSum'       => (float) $row->total_cost_sum,
            ];
        }

        // "Весь период" — та же идея, что и у $salesStatisticsByMonth['all']
        // выше, но без промежуточной from_begin-переменной — считаем сразу.
        $supplierStatsAllRaw = DB::table('order_product as op')
            ->select(
                'op.fromStock as supplier_name',
                DB::raw('COUNT(*) as positions_count'),
                DB::raw('COUNT(DISTINCT op.order_id) as orders_count'),
                DB::raw('SUM(op.item_sum) as total_cost_sum')
            )
            ->whereNotNull('op.fromStock')
            ->where('op.fromStock', '!=', '')
            ->groupBy('op.fromStock')
            ->get();

        $supplierStatisticsByMonth['all'] = [];
        foreach ($supplierStatsAllRaw as $row) {
            $supplierStatisticsByMonth['all'][$row->supplier_name] = [
                'positionsCount' => (int) $row->positions_count,
                'ordersCount'    => (int) $row->orders_count,
                'totalSum'       => (float) $row->total_cost_sum,
            ];
        }

        // Сортировка по доле в продажах
        uasort($sales_statistics, function($a, $b) use ($totalSalesSum) {
            $shareA = $totalSalesSum ? $a['totalSalesSum'] / $totalSalesSum : 0;
            $shareB = $totalSalesSum ? $b['totalSalesSum'] / $totalSalesSum : 0;
            return $shareB <=> $shareA;
        });

        foreach ($users as $user) {
            $usersCalculating[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->user_role,
                'sumOrders' => $user->orders->sum('sum_with_margine'),
                'qtyOrders' => $user->orders->count(),
            ];
        }
        
        // "возвращено" здесь больше нет — раньше это был ручной пункт в
        // выпадающем списке статуса заказа, теперь единственная точка входа
        // для возврата — форма оформления возврата (makeCustomerReturn()),
        // статус позиции проставляется оттуда автоматически.
        $statuses = [
            'payment_waiting' => 'ожидание оплаты', 'processing' => 'принято в работу', 'supplier_refusal' => 'отказ поставщика',
            'arrived_at_the_point_of_delivery' => "поступило в ПВЗ", 'issued' => "выдано"
        ];

        $suppliers = Suppliers::all()->toArray();
        $accounts = Accounts::all()->toArray();
        $cashflow_transactions = CashflowTransactions::with(['account','user','supplier', 'cashflowCategory'])->orderBy('created_at', 'desc')->get();
        $order_payments = OrderPayment::all()->toArray();
        $cashflow_categories = CashflowCategories::all()->toArray();
        $expense_categories = ExpenseCategories::all()->toArray();
        $customer_returns = CustomerReturn::all();

        //dd($cashflow_transactions);

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

        $suppliersInStock = [
            'shtm', 'rssk', 'trd', 'tss', 'rmtk', 'phtn', 'fbst',
            'kln', 'frmt', 'voltag_ast', 'kz_starter', 'cc_motors_talgat',
            'gerat_ast', 'kainar_razbor_tima', 'kap', 'alem_auto',
        ];

        // 4️⃣ Добавляем total
        foreach ($suppliers_settlements as $supplier => $months) {
            $suppliers_settlements[$supplier]['total'] = array_sum($months);

            if (in_array($supplier, $suppliersInStock)) {
                $suppliers_settlements[$supplier]['color'] = '#066402';
                $suppliers_settlements[$supplier]['type'] = 'in_stock';
            } else {
                $suppliers_settlements[$supplier]['color'] = '#1c64b6';
                $suppliers_settlements[$supplier]['type'] = 'for_order';
            }
        }
        //dd($suppliers_settlements);
        uasort($suppliers_settlements, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }
            return ($a < $b) ? 1 : -1;
        });

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

        // План поднят с 9М/мес до 11М/мес по просьбе Романа 2026-09-18 —
        // НЕ задним числом: новый план действует с учётного периода,
        // начинающегося 2026-09-08 (тот самый "8-е по 7-е", см.
        // $startForDailyStats выше), более ранние периоды остаются на
        // старых 9М, чтобы раскраска точек графика мерила каждый период
        // тем планом, что реально действовал тогда, а не текущим.
        $monthlyPlan = $startForDailyStats->greaterThanOrEqualTo('2026-09-08') ? 11_000_000 : 9_000_000;
        $daysInPeriod = $startForDailyStats->copy()->toPeriod($endForDailyStats)->count();
        $planPerDay = $monthlyPlan / $daysInPeriod;
        $upperThreshold = $planPerDay * 1.3;
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

        $financeDashboard = $this->getFinanceDashboardData($request);
        $supplierSettlementsDebts = $this->getSuppliersSettlements($request);
        $receivables = $this->getReceivablesData($request);
        $reconciliation = $this->getReconciliationData($request);
        
        //dd($supplierSettlementsDebts);

        return view('admin/index', array_merge([
            'financeDashboard' => $financeDashboard,
            'orders' => $orders,
            'users' => $users,
            'payments' => $payments,
            'statuses' => $statuses,
            'usersCalculating' => $usersCalculating,
            'customers' => array_unique($customers),
            'supplerSettlements' => $supplerSettlements,
            'cashflow_transactions' => $cashflow_transactions,
            'cashflow_categories' => $cashflow_categories,
            'expense_categories' => $expense_categories,
            'order_payments' => $order_payments,
            'suppliers' => $suppliers,
            'suppliers_settlements' => $suppliers_settlements,
            'customer_returns' => $customer_returns,
            'sales_statistics' => $sales_statistics,
            'sales_statistics_from_begin' => $sales_statistics_from_begin,
            'totalSalesSum' => $totalSalesSum,
            'totalPrimeCostSum' => $totalPrimeCostSum,
            'totalCountOfSales' => $totalCountOfSales,
            'goods_in_office' => $goods_in_office,
            'goods_in_office_count' => $goods_in_office_count,
            'goods_in_office_sum' => $goods_in_office_sum,
            'kaspiComission' => $kaspiComission,
            'marginClear' => $marginClear,
            'stats' => $stats,
            'labels' => $labels,
            'salesData' => $salesData,
            'purchaseData' => $purchaseData,
            'plannedSum' => $plannedSum,
            'actualSum' => $actualSum,
            'pointColors' => $pointColors,
            'salesSumFromBegin' => $salesSumFromBegin,
            'primeCostSumFromBegin' => $primeCostSumFromBegin,
            'countOfSalesFromBegin' => $countOfSalesFromBegin,
            'totalItemsSoldFromBegin' => $totalItemsSoldFromBegin,
            'kaspiComissionFromBegin' => $kaspiComissionFromBegin,
            'marginClearFromBegin' => $marginClearFromBegin,
            'accounts' => $accounts,
            'salesStatisticsByMonth'     => $salesStatisticsByMonth,
            'monthsSorted'               => $monthsSorted,
            'currentAccountingMonthKey'  => $currentAccountingMonthKey,
            'salesStatisticsByMonth'     => $salesStatisticsByMonth,
            'salesStatisticsByMonth'     => $salesStatisticsByMonth,
            'monthsSorted'               => $monthsSorted,
            'currentAccountingMonthKey'  => $currentAccountingMonthKey,
            'supplierStatisticsByMonth'  => $supplierStatisticsByMonth,
            'start'                      => $start,
            'end'                        => $end,
        ], 
        $financeDashboard,
        $supplierSettlementsDebts,
        $receivables,
        $reconciliation
        ));
    }

    private function getFinanceDashboardData(Request $request): array
    {
        // Учётный период — с 8 числа по 7-е следующего месяца (тот же
        // период, что и везде в остальном админ-панели, см. index()/
        // getDataByMonths()), а не календарный месяц — иначе дашборд
        // считал бы "приход/расход за месяц" не в те даты, что Роман
        // реально закрывает как отчётный период.
        $today = Carbon::now();
        if ($today->day >= 8) {
            $defaultFrom = Carbon::create($today->year, $today->month, 8)->startOfDay();
            $defaultTo = $defaultFrom->copy()->addMonth()->subDay()->endOfDay();
        } else {
            $defaultTo = Carbon::create($today->year, $today->month, 7)->endOfDay();
            $defaultFrom = $defaultTo->copy()->subMonth()->addDay()->startOfDay();
        }

        $dateFrom = Carbon::parse(
            $request->get('date_from', $defaultFrom->format('Y-m-d'))
        )->startOfDay();

        $dateTo = Carbon::parse(
            $request->get('date_to', $defaultTo->format('Y-m-d'))
        )->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | KPI по ДДС
        |--------------------------------------------------------------------------
        */

        $income = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'in')
            ->sum('amount');

        // cashflow_category_id=8 "Личное изъятие" исключается намеренно —
        // это не бизнес-расход, а личное изъятие владельца из кассы, оно не
        // должно занижать картину прибыльности бизнеса на дашборде.
        $expense = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'out')
            ->where('cashflow_category_id', '!=', 8)
            ->sum('amount');

        $balance = CashflowTransactions::selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0)
                -
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0)
                as balance
            ")
            ->value('balance');

        $customerReturnsAmount = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('cashflow_category_id', 7) // возврат клиенту
            ->where('direction', 'out')
            ->sum('amount');

        $supplierRefundsAmount = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('cashflow_category_id', 4) // возврат от поставщика
            ->where('direction', 'in')
            ->sum('amount');

        // отрицательный итог supplier_settlement = ты должен поставщикам
        $supplierDebtRaw = SupplierSettlement::sum('sum');
        $supplierDebt = $supplierDebtRaw < 0 ? abs($supplierDebtRaw) : 0;

        // Налог 3% от выручки (по продажам за период, не от факта поступления
        // денег) — оценка, не проведённая транзакция ДДС, Роман сам заносит
        // фактическую оплату налога отдельной операцией через категорию
        // "Налоги" в make-cashflow-transaction, когда платит.
        $periodRevenue = Order::whereBetween('date', [$dateFrom, $dateTo])->sum('sum_with_margine');
        $estimatedTax = round($periodRevenue * 0.03, 2);

        // Заработали за период (запрошено Романом 2026-09-03) — грязная
        // маржа продаж (выручка минус себестоимость), ДО вычета расходов
        // бизнеса (аренда/зарплата/реклама и т.д.) — та же пара полей
        // Order.sum_with_margine/Order.sum, что и в estimated_tax выше.
        $periodPrimeCost = Order::whereBetween('date', [$dateFrom, $dateTo])->sum('sum');
        $periodGrossMargin = round($periodRevenue - $periodPrimeCost, 2);

        $financeKpi = [
            'balance' => round($balance, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net_flow' => round($income - $expense, 2),
            'customer_returns' => round($customerReturnsAmount, 2),
            'supplier_refunds' => round($supplierRefundsAmount, 2),
            'supplier_debt' => round($supplierDebt, 2),
            'revenue' => round($periodRevenue, 2),
            'estimated_tax' => $estimatedTax,
            'prime_cost' => round($periodPrimeCost, 2),
            'gross_margin' => $periodGrossMargin,
        ];

        /*
        |--------------------------------------------------------------------------
        | Остатки по счетам
        |--------------------------------------------------------------------------
        */

        $accounts = Accounts::where('is_active', 1)->get();

        $financeAccountsSummary = $accounts->map(function ($account) use ($dateFrom, $dateTo) {
            $income = CashflowTransactions::where('account_id', $account->id)
                ->whereBetween('txn_at', [$dateFrom, $dateTo])
                ->where('direction', 'in')
                ->sum('amount');

            $expense = CashflowTransactions::where('account_id', $account->id)
                ->whereBetween('txn_at', [$dateFrom, $dateTo])
                ->where('direction', 'out')
                ->sum('amount');

            $balance = CashflowTransactions::where('account_id', $account->id)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0)
                    -
                    COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0)
                    as balance
                ")
                ->value('balance');

            return [
                'name' => $account->name,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'balance' => round($balance, 2),
            ];
        })->values()->toArray();

        /*
        |--------------------------------------------------------------------------
        | Расходы по категориям
        |--------------------------------------------------------------------------
        */

        $expenseBreakdownRows = CashflowTransactions::selectRaw('expense_category_id, SUM(amount) as total_amount')
            ->whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'out')
            ->whereNotNull('expense_category_id')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->get();

        $financeExpenseBreakdown = $expenseBreakdownRows->map(function ($row) {
            $category = ExpenseCategories::find($row->expense_category_id);

            return [
                'name' => $category?->rus_name ?? $category?->name ?? 'Без категории',
                'amount' => round($row->total_amount, 2),
            ];
        })->toArray();

        /*
        |--------------------------------------------------------------------------
        | Личные изъятия (cashflow_category_id=8) — отдельно от бизнес-расходов,
        | намеренно не входят в KPI "Расход" (см. выше), но кто/когда/сколько
        | взял и какую долю это составляет от реальных бизнес-расходов —
        | отдельная видимость, которую попросил Роман 2026-08-31.
        |--------------------------------------------------------------------------
        */

        $ownerWithdrawalsRaw = CashflowTransactions::with('user')
            ->whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('cashflow_category_id', 8)
            ->orderByDesc('txn_at')
            ->get();

        $financeOwnerWithdrawals = $ownerWithdrawalsRaw->map(function ($txn) {
            return [
                'txn_at' => $txn->txn_at,
                'user_name' => $txn->user?->name ?? '—',
                'amount' => round((float) $txn->amount, 2),
            ];
        })->toArray();

        $totalOwnerWithdrawals = round((float) $ownerWithdrawalsRaw->sum('amount'), 2);

        // Тот же знаменатель, что и у процентов в "Расходы по категориям" —
        // сравнимо между виджетами.
        $ownerWithdrawalsPercentOfExpense = $expense > 0
            ? round(($totalOwnerWithdrawals / $expense) * 100, 1)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Возвраты
        |--------------------------------------------------------------------------
        */

        // Открыт/закрыт — состояние возврата на текущий момент (как
        // Кредиторка/Дебиторка), не режется периодом: возврат, открытый в
        // прошлом месяце и всё ещё не закрытый, должен быть виден как
        // открытый независимо от выбранного на дашборде периода.
        $openCount = CustomerReturn::where('status', 'pending')->count();

        $closedCount = CustomerReturn::where('status', 'completed')->count();

        // А вот суммы выплат/получений — поток денег ЗА ПЕРИОД, здесь
        // фильтр по return_date уместен и остаётся.
        $returnsQuery = CustomerReturn::whereBetween('return_date', [
            $dateFrom->toDateString(),
            $dateTo->toDateString()
        ]);

        $customerPaid = (clone $returnsQuery)->sum('customer_refund_paid');

        // "received" — реальные деньги на счёт, "credited" — остались на
        // балансе у поставщика зачётом в следующую закупку (напр.
        // Автотрейд). Оба варианта — компенсация, которую поставщик
        // реально предоставил, просто в разной форме; раньше здесь
        // считались только "received", и зачёты не попадали ни в "получено
        // от поставщиков", ни в расчёт "Потери на возвратах" — искажало
        // обе цифры.
        $supplierReceived = (clone $returnsQuery)
            ->whereIn('supplier_refund_status', ['received', 'credited'])
            ->sum('supplier_refund_received');

        $financeReturnsStats = [
            'open_count' => $openCount,
            'closed_count' => $closedCount,
            'customer_paid' => round($customerPaid, 2),
            'supplier_received' => round($supplierReceived, 2),
            'loss' => round($customerPaid - $supplierReceived, 2),
        ];

        /*
        |--------------------------------------------------------------------------
        | Последние операции
        |--------------------------------------------------------------------------
        */

        // Лента последних операций — все движения за СЕГОДНЯ (не топ-10
        // по времени в целом — при активном тестировании счёт быстро
        // уходит за пределы 10 строк, и старые сегодняшние операции
        // становится не видно, хотя они всё ещё "сегодняшние", см.
        // просьбу Романа 2026-09-02).
        $latestTransactionsRows = CashflowTransactions::with('account')
            ->whereDate('txn_at', now()->toDateString())
            ->orderBy('txn_at', 'desc')
            ->get();

        $financeLatestTransactions = $latestTransactionsRows->map(function ($txn) {
            return [
                'txn_at' => $txn->txn_at,
                'direction' => $txn->direction,
                'subcategory' => $txn->subcategory,
                'counterparty' => $txn->counterparty,
                'account_name' => $txn->account->name ?? '—',
                'amount' => round($txn->amount, 2),
            ];
        })->toArray();

        return [
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
            'financeKpi' => $financeKpi,
            'financeAccountsSummary' => $financeAccountsSummary,
            'financeExpenseBreakdown' => $financeExpenseBreakdown,
            'financeOwnerWithdrawals' => $financeOwnerWithdrawals,
            'totalOwnerWithdrawals' => $totalOwnerWithdrawals,
            'ownerWithdrawalsPercentOfExpense' => $ownerWithdrawalsPercentOfExpense,
            'financeReturnsStats' => $financeReturnsStats,
            'financeLatestTransactions' => $financeLatestTransactions,
        ];
    }

    /**
     * Финансовая сверка: мост между "прибылью по начислению" (P&L — что
     * должно было получиться по марже минус расходы) и фактическим
     * изменением остатка денег за тот же период. Цель — не абстрактная
     * "сходимость дебета с кредитом", а конкретный диагностический
     * инструмент: почему на бумаге прибыль есть, а по факту кассовые
     * разрывы и нечего откладывать (запрос Романа 2026-09-01).
     *
     * Все компоненты моста считаются как ДЕЛЬТА ЗА ПЕРИОД (не
     * реконструкция баланса "на дату X" — так надёжнее и проще
     * проверить), поэтому расхождение в самом конце — это честный
     * остаток, не объяснённый моделью (переводы между своими счетами,
     * прочие доходы, ошибки в данных и т.д.), а не подгонка под ноль.
     */
    public function getReconciliationData(Request $request): array
    {
        $today = Carbon::now();
        if ($today->day >= 8) {
            $defaultFrom = Carbon::create($today->year, $today->month, 8)->startOfDay();
            $defaultTo = $defaultFrom->copy()->addMonth()->subDay()->endOfDay();
        } else {
            $defaultTo = Carbon::create($today->year, $today->month, 7)->endOfDay();
            $defaultFrom = $defaultTo->copy()->subMonth()->addDay()->startOfDay();
        }

        $dateFrom = Carbon::parse($request->get('recon_date_from', $defaultFrom->format('Y-m-d')))->startOfDay();
        $dateTo = Carbon::parse($request->get('recon_date_to', $defaultTo->format('Y-m-d')))->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Блок 1: прибыль по начислению за период
        |--------------------------------------------------------------------------
        */

        $revenue = (float) Order::whereBetween('date', [$dateFrom, $dateTo])->sum('sum_with_margine');
        $cogs = (float) Order::whereBetween('date', [$dateFrom, $dateTo])->sum('sum');
        $grossMargin = $revenue - $cogs;

        // Только реальные операционные расходы (категория 2 "expense") —
        // НЕ оплата поставщикам (это уже в себестоимости выше) и НЕ
        // личные изъятия (отдельная строка моста, см. ниже).
        $opex = (float) CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'out')
            ->where('cashflow_category_id', 2)
            ->sum('amount');

        $returnsCustomerPaid = (float) CustomerReturn::whereBetween('return_date', [
            $dateFrom->toDateString(), $dateTo->toDateString(),
        ])->sum('customer_refund_paid');

        $returnsSupplierReceived = (float) CustomerReturn::whereBetween('return_date', [
            $dateFrom->toDateString(), $dateTo->toDateString(),
        ])->whereIn('supplier_refund_status', ['received', 'credited'])->sum('supplier_refund_received');

        $returnsLoss = $returnsCustomerPaid - $returnsSupplierReceived;

        $netAccrualProfit = $grossMargin - $opex - $returnsLoss;

        /*
        |--------------------------------------------------------------------------
        | Блок 2: факт по кассе за период (истина в последней инстанции)
        |--------------------------------------------------------------------------
        */

        $actualCashDelta = (float) CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as delta")
            ->value('delta');

        $cashBalanceBefore = (float) CashflowTransactions::where('txn_at', '<', $dateFrom)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');

        $cashBalanceAfter = $cashBalanceBefore + $actualCashDelta;

        /*
        |--------------------------------------------------------------------------
        | Блок 3: мост (объясняем разницу между начислением и кассой)
        |--------------------------------------------------------------------------
        */

        // Кредиторка поставщикам выросла (начислили больше, чем оплатили)
        // → кэш сохранён, плюс к мосту. Погасили долг сверх начисленного
        // за период → кэш ушёл, минус к мосту. supplier_settlement.sum
        // отрицательный = мы должны, поэтому дельта payable = -Δ(sum).
        $payableDelta = -1 * (float) SupplierSettlement::whereBetween('created_at', [$dateFrom, $dateTo])->sum('sum');

        // Дебиторка (клиенты + Kaspi) выросла (заработали по начислению
        // больше, чем реально получили деньгами) → кэш недополучен, минус
        // к мосту.
        $cashCollectedFromCustomers = (float) OrderPayment::whereBetween('order_payments.created_at', [$dateFrom, $dateTo])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END), 0) as collected")
            ->value('collected');
        $receivableDelta = $revenue - $cashCollectedFromCustomers;

        $ownerWithdrawals = (float) CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'out')
            ->where('cashflow_category_id', 8)
            ->sum('amount');

        $openingBalanceInjections = (float) CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'in')
            ->where('cashflow_category_id', 9)
            ->sum('amount');

        $expectedCashDelta = $netAccrualProfit + $payableDelta - $receivableDelta - $ownerWithdrawals + $openingBalanceInjections;

        // То, что мост не объяснил — переводы между своими счетами
        // (категория 6), прочие доходы, любые нестыковки в данных. Если
        // эта цифра стабильно большая — модель моста нужно уточнять, если
        // около нуля — прибыль и касса реально сходятся, просто в разные
        // моменты времени (задержки выплат/платежей).
        $unexplained = $actualCashDelta - $expectedCashDelta;

        return [
            'reconDateFrom' => $dateFrom->format('Y-m-d'),
            'reconDateTo' => $dateTo->format('Y-m-d'),
            'reconciliation' => [
                'revenue' => round($revenue, 2),
                'cogs' => round($cogs, 2),
                'gross_margin' => round($grossMargin, 2),
                'opex' => round($opex, 2),
                'returns_loss' => round($returnsLoss, 2),
                'net_accrual_profit' => round($netAccrualProfit, 2),

                'cash_balance_before' => round($cashBalanceBefore, 2),
                'cash_balance_after' => round($cashBalanceAfter, 2),
                'actual_cash_delta' => round($actualCashDelta, 2),

                'payable_delta' => round($payableDelta, 2),
                'receivable_delta' => round($receivableDelta, 2),
                'owner_withdrawals' => round($ownerWithdrawals, 2),
                'opening_balance_injections' => round($openingBalanceInjections, 2),
                'expected_cash_delta' => round($expectedCashDelta, 2),

                'unexplained' => round($unexplained, 2),
            ],
        ];
    }

    /**
     * Кнопка "Пересчитать" на странице "Финансовая сверка" — реально
     * прогоняет `php artisan finance:reconcile` (ту же логику, что и сама
     * страница, просто через консольную команду, как Роман попросил) и
     * возвращает на страницу с её текстовым выводом во флеш-сообщении.
     * Сама страница и без этой кнопки всегда показывает свежий расчёт при
     * каждой загрузке — кнопка нужна, чтобы явно увидеть именно
     * консольный вывод команды, один в один как в терминале.
     */
    public function runFinanceReconcile(Request $request)
    {
        $params = [];
        if ($request->filled('recon_date_from')) {
            $params['--date_from'] = $request->get('recon_date_from');
        }
        if ($request->filled('recon_date_to')) {
            $params['--date_to'] = $request->get('recon_date_to');
        }

        Artisan::call('finance:reconcile', $params);
        $output = Artisan::output();

        return redirect()->route('admin_panel', [
            'recon_date_from' => $request->get('recon_date_from'),
            'recon_date_to' => $request->get('recon_date_to'),
            'open_section' => 'finance_reconciliation',
        ])->with('reconcileOutput', $output);
    }

    private function getReceivablesData(Request $request): array
    {
        // Дебиторка от поставщиков за возвраты (сгруппировано по поставщику)
        $supplierReturnReceivablesRaw = CustomerReturn::where('supplier_refund_status', 'pending')
            ->whereRaw('supplier_refund_amount > supplier_refund_received')
            ->get();

        $supplierReturnReceivables = $supplierReturnReceivablesRaw
            ->groupBy('supplier_name')
            ->map(function ($group, $supplierName) {
                return [
                    'name' => $supplierName ?: 'Неизвестный поставщик',
                    'amount' => round($group->sum(fn ($ret) => (float) $ret->supplier_refund_amount - (float) $ret->supplier_refund_received), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        $totalSupplierReturnReceivable = round($supplierReturnReceivables->sum('amount'), 2);

        // Дебиторка от клиентов (недоплата по заказу), сгруппировано по телефону
        $paidByOrder = OrderPayment::selectRaw("order_id, SUM(CASE WHEN type = 'payment' THEN amount WHEN type = 'refund' THEN -amount ELSE 0 END) as paid")
            ->groupBy('order_id')
            ->pluck('paid', 'order_id');

        // См. ERP_GO_LIVE_DATE — заказы до этой даты не тянем в дебиторку
        // вообще, у них никогда не было настоящих записей в order_payments.
        $customerReceivablesRaw = Order::where('date', '>=', self::ERP_GO_LIVE_DATE)
            ->get()
            ->map(function ($order) use ($paidByOrder) {
            $paid = (float) ($paidByOrder[$order->id] ?? 0);
            $due = round((float) $order->sum_with_margine - $paid, 2);

            return [
                'order_id' => $order->id,
                // Для Kaspi недоплата — это не долг конкретного клиента, а
                // задержка выплаты от самого Kaspi (маркетплейс платит уже
                // после того, как клиент получит заказ). Группируем отдельно
                // общей строкой "Kaspi", а не по телефону — иначе выглядит
                // так, будто это клиент должен доплатить, хотя на самом деле
                // просто ещё не пришла выплата.
                'group' => $order->sale_channel === 'kaspi' ? 'Kaspi' : ($order->customer_phone ?: 'Без телефона'),
                'due' => $due,
            ];
        })->filter(fn ($row) => $row['due'] > 0);

        $customerReceivables = $customerReceivablesRaw
            ->groupBy('group')
            ->map(function ($group, $label) {
                return [
                    'name' => $label,
                    'amount' => round($group->sum('due'), 2),
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        $totalCustomerReceivable = round($customerReceivables->sum('amount'), 2);

        return [
            'supplierReturnReceivables' => $supplierReturnReceivables,
            'totalSupplierReturnReceivable' => $totalSupplierReturnReceivable,
            'customerReceivables' => $customerReceivables,
            'totalCustomerReceivable' => $totalCustomerReceivable,
        ];
    }

    public function getSuppliersSettlements(Request $request)
    {
        // Начисления по поставщикам
        $accrualsSub = SupplierSettlement::query()
            ->select(
                'supplier_id',
                'supplier',
                DB::raw('SUM(`sum` * -1) as accrued')
            )
            ->where('operation', 'realization')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id', 'supplier');

        // Оплаты поставщикам
        $paymentsSub = CashflowTransactions::query()
            ->select(
                'supplier_id',
                DB::raw('SUM(amount) as paid')
            )
            ->where('direction', 'out')
            ->where('cashflow_category_id', 3) // оплата поставщику
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id');

        // Просроченные начисления по поставщикам
        $overdueSub = SupplierSettlement::query()
            ->select(
                'supplier_id',
                DB::raw('SUM(`sum` * -1) as overdue_accrued')
            )
            ->where('operation', 'realization')
            ->where('payment_status', 'overdue')
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id');

        // Баланс по поставщикам
        $supplierBalances = DB::query()
            ->fromSub($accrualsSub, 'a')
            ->leftJoinSub($paymentsSub, 'p', 'a.supplier_id', '=', 'p.supplier_id')
            ->leftJoinSub($overdueSub, 'o', 'a.supplier_id', '=', 'o.supplier_id')
            ->select(
                'a.supplier_id',
                'a.supplier',
                DB::raw('a.accrued as accrued'),
                DB::raw('COALESCE(p.paid, 0) as paid'),
                DB::raw('(a.accrued - COALESCE(p.paid, 0)) as balance'),
                DB::raw('COALESCE(o.overdue_accrued, 0) as overdue_accrued')
            )
            ->orderByDesc('balance')
            ->get()
            ->map(function ($row) {
                $balance = (float) $row->balance;
                $overdueAccrued = (float) $row->overdue_accrued;

                $row->balance = $balance;
                $row->overdue_accrued = $overdueAccrued;

                // Просрочка не может быть больше остатка долга
                $row->overdue_balance = min($overdueAccrued, max($balance, 0));

                return $row;
            });
        $supplierBalancesTable = $supplierBalances
            ->filter(fn ($row) => $row->balance != 0)
            ->values();
        // Только поставщики с долгом
        $supplierDebts = $supplierBalances
            ->filter(fn ($row) => $row->balance > 0)
            ->values();

        // Общая кредиторка
        $totalSupplierDebt = $supplierDebts->sum('balance');

        // Поставщиков с долгом
        $suppliersWithDebtCount = $supplierDebts->count();

        // Переплата поставщикам
        $totalSupplierOverpayment = $supplierBalances
            ->filter(fn ($row) => $row->balance < 0)
            ->sum(fn ($row) => abs($row->balance));

        // Разбивка переплаты по поставщикам (просьба Романа 2026-09-19) —
        // раньше эта же роль ("сколько у нас аванса лежит у поставщика")
        // играл отдельный виджет "Сальдо у поставщиков (зачёт)"
        // (getSupplierCreditsData(), таблица supplier_credits) — но после
        // того как автосписание/автопополнение этого зачёта убрали целиком
        // (см. manuallyMakeOrder()/supplierPayment()), та таблица перестала
        // куда-либо писаться на обычном пути и начала врать статичным
        // числом. $supplierBalances уже и так живьём считает balance =
        // accrued-paid из supplier_settlement/cashflow_transactions на
        // каждый рендер — тот же источник, что и "Долг"/"Переплата" выше,
        // так что отдельная ручная бухгалтерия ему не нужна, обновляется
        // сама с каждым новым заказом/платежом.
        $supplierOverpayments = $supplierBalances
            ->filter(fn ($row) => $row->balance < 0)
            ->map(fn ($row) => ['name' => $row->supplier, 'amount' => round(abs($row->balance), 2)])
            ->sortByDesc('amount')
            ->values();

        // Просроченная кредиторка
        $overdueSupplierDebt = $supplierBalances->sum('overdue_balance');

        return [
            'supplierBalances' => $supplierBalances,
            'supplierDebts' => $supplierDebts,
            'totalSupplierDebt' => $totalSupplierDebt,
            'suppliersWithDebtCount' => $suppliersWithDebtCount,
            'totalSupplierOverpayment' => $totalSupplierOverpayment,
            'supplierOverpayments' => $supplierOverpayments,
            'overdueSupplierDebt' => $overdueSupplierDebt,
            'supplierBalancesTable' => $supplierBalancesTable,

        ];
    }

    public function chooseProductsFromOrder(Request $request)
    {
        $order_id = $request->data['order_id'];

        $products = OrderProduct::where('order_id', $order_id)->get();

        return response()->json($products);
    }

    public function makeCustomerReturn(Request $request)
    {
        // Обёрнуто в транзакцию 2026-08-31: живой случай — CashflowTransactions
        // (возврат клиенту) успевал создаться, а CustomerReturn::create()
        // падал следом на внешнем ключе (customer_id от заказа без клиента),
        // и осиротевшая запись "возврат клиенту" оставалась в кассе, задваивая
        // "Возвраты клиентам" на дашборде — хотя реального второго возврата
        // не было. Теперь любой сбой на любом шаге откатывает всё разом.
        DB::transaction(function () use ($request) {
        $cashflowTransactionOut = CashflowTransactions::create([
            'txn_at' => $request->return_date,
            'direction' => 'out',
            'cashflow_category_id' => 7,
            'expense_category_id' => null,
            'supplier_id' => $request->supplier_id,
            'user_id' => $request->user_id,
            'account_id' => $request->account_id_out,
            'amount' => $request->customer_refund_paid,
            'subcategory' => 'возврат клиенту',
            'counterparty' => $request->customer_phone ?? null,
            'related_table' => 'customer_returns',
            'related_id' => null,
            'comment' => $request->comment ?: 'Возврат клиенту по заказу номер ' . $request->order_id,
        ]);

        // Пусто/"нет данных" — оба варианта означают "клиент не привязан",
        // раньше проверялось только точное совпадение со строкой "нет
        // данных", а пустая строка (заказ без customer_id) улетала в БД
        // как есть и падала на внешнем ключе.
        $customer_id = in_array($request->customer_id, ['нет данных', null, ''], true) ? null : $request->customer_id;

        $customer_returns = CustomerReturn::create([
            'customer_id' => $customer_id,
            'order_id' => $request->order_id,
            'order_product_id' => $request->order_product_id,
            'supplier_id' => $request->supplier_id,
            'supplier_name' => $request->supplier_name,
            'user_id' => $request->user_id,
            'customer_phone' => $request->customer_phone,
            'qty' => $request->qty,
            'sale_price' => $request->sale_price,
            'customer_refund_amount' => $request->customer_refund_amount,
            'supplier_purchase_price' => $request->supplier_purchase_price,
            'supplier_refund_amount' => $request->supplier_refund_amount,
            'customer_refund_paid' => $request->customer_refund_paid,
            'supplier_refund_received' => 0,
            'return_date' => $request->return_date,
            'customer_refund_date' => $request->customer_refund_date,
            'reason' => $request->reason,
            'comment' => $request->comment,
            'status' => $request->status,
            'supplier_refund_status' => 'pending',
            'customer_cashflow_transaction_id' => $cashflowTransactionOut->id,
        ]);

        $cashflowTransactionOut->update(['related_id' => $customer_returns->id]);

        // Унификация 2026-08-31: раньше "возвращено" выставлялось вручную
        // через выпадающий список статуса в Заказах (changeStatus()) и
        // просто обнуляло позицию целиком, без учёта частичного возврата и
        // без всякой связи с реальным движением денег. Теперь единственная
        // точка входа — этот дашборд-воркфлоу, а статус/суммы позиции и
        // заказа выставляются автоматически, пропорционально фактически
        // возвращённому количеству (qty может быть меньше исходного).
        $orderProduct = OrderProduct::find($request->order_product_id);

        if ($orderProduct) {
            $returnedQty = (int) $request->qty;
            $originalQty = (int) $orderProduct->qty;

            if ($returnedQty > 0 && $originalQty > 0) {
                $unitPrice = (float) $orderProduct->price;
                $unitPriceWithMargine = (float) $orderProduct->priceWithMargine;

                $orderProduct->item_sum = max(0, $orderProduct->item_sum - $unitPrice * $returnedQty);
                $orderProduct->itemSumWithMargine = max(0, $orderProduct->itemSumWithMargine - $unitPriceWithMargine * $returnedQty);
                $orderProduct->qty = max(0, $originalQty - $returnedQty);

                if ($orderProduct->qty === 0) {
                    $orderProduct->status = 'returned';
                }

                $orderProduct->save();

                // Кредиторка уменьшается только если у поставщика была
                // отсрочка платежа — при оплате по факту получения или
                // предоплатой долга к этому моменту уже нет, трогать нечего.
                if ($orderProduct->payment_policy_snapshot === 'deferred_after_receipt') {
                    $supplierSettlement = SupplierSettlement::where('product_id', $orderProduct->id)
                        ->where('operation', 'realization')
                        ->first();

                    if ($supplierSettlement) {
                        $supplierSettlement->sum += $unitPrice * $returnedQty;
                        $supplierSettlement->save();
                    }
                }

                $orderId = $orderProduct->order_id;
                $newOrderSum = OrderProduct::where('order_id', $orderId)->sum('item_sum');
                $newOrderSumWithMargine = OrderProduct::where('order_id', $orderId)->sum('itemSumWithMargine');

                $order = Order::find($orderId);
                if ($order) {
                    $order->sum = $newOrderSum;
                    $order->sum_with_margine = $newOrderSumWithMargine;
                    $order->save();
                }

                $orderSettlement = Setlement::where('order_id', $orderId)->first();
                if ($orderSettlement) {
                    $orderSettlement->sum = $newOrderSum;
                    $orderSettlement->sumWithMargine = $newOrderSumWithMargine;
                    $orderSettlement->save();
                }
            }
        }
        });

        return back()->with(['message' => 'Возврат клиенту успешно сохранён!']);
    }

    /**
     * Правка цены закупа/розницы конкретной позиции заказа (запрошено
     * Романом 2026-09-03) — нужна на два реальных сценария: (1) поставщик
     * отказал, перезаказали у другого/по другой цене -> должна поменяться
     * кредиторка именно по этой позиции; (2) сказали клиенту, что товар
     * подорожал, он доплачивает -> должна поменяться розница и, как
     * следствие, дебиторка/сумма заказа. Скидки клиенту тоже проводятся
     * этим полем (правкой розницы), отдельного поля на сумму заказа
     * больше нет — сумма заказа считается ИЗ позиций, а не наоборот.
     */
    public function updateOrderProductPrice(Request $request)
    {
        $request->validate([
            'order_product_id' => 'required|integer|exists:order_product,id',
            'price' => 'required|numeric|min:0',
            'price_with_margine' => 'required|numeric|min:0',
        ]);

        $orderProduct = OrderProduct::findOrFail($request->order_product_id);
        $qty = (int) $orderProduct->qty;

        $orderProduct->price = round((float) $request->price, 2);
        $orderProduct->priceWithMargine = round((float) $request->price_with_margine, 2);
        $orderProduct->item_sum = round($orderProduct->price * $qty, 2);
        $orderProduct->itemSumWithMargine = round($orderProduct->priceWithMargine * $qty, 2);
        $orderProduct->save();

        // Кредиторка поставщику — та же строка realization, что и в
        // makeCustomerReturn(), просто пересчитанная под новую цену закупа,
        // а не уменьшенная на возврат.
        $supplierSettlement = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')
            ->first();
        if ($supplierSettlement) {
            $supplierSettlement->sum = -$orderProduct->item_sum;
            $supplierSettlement->save();
        }

        $orderId = $orderProduct->order_id;
        $newOrderSum = OrderProduct::where('order_id', $orderId)->sum('item_sum');
        $newOrderSumWithMargine = OrderProduct::where('order_id', $orderId)->sum('itemSumWithMargine');

        $order = Order::find($orderId);
        if ($order) {
            $order->sum = $newOrderSum;
            $order->sum_with_margine = $newOrderSumWithMargine;
            $order->save();
        }

        $orderSettlement = Setlement::where('order_id', $orderId)->first();
        if ($orderSettlement) {
            $orderSettlement->sum = $newOrderSum;
            $orderSettlement->sumWithMargine = $newOrderSumWithMargine;
            $orderSettlement->save();
        }

        return back()->with(['message' => 'Цены позиции №' . $orderProduct->id . ' обновлены']);
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

    /**
     * Разовая форма для go-live ERP (2026-08-31) — заносит исторические
     * остатки, накопившиеся ДО начала учёта в системе: реальные деньги на
     * счетах, кредиторка/зачёты по поставщикам, дебиторка по клиентам.
     * Пустые/нулевые поля просто пропускаются.
     */
    public function saveOpeningBalances(Request $request)
    {
        $date = $request->date ?: now()->format('Y-m-d');
        $created = 0;

        DB::transaction(function () use ($request, $date, &$created) {
            // Счета — реальные деньги, которые уже лежат на счету.
            foreach (($request->accounts ?? []) as $accountId => $amount) {
                $amount = (float) $amount;
                if ($amount <= 0) {
                    continue;
                }

                CashflowTransactions::create([
                    'txn_at' => $date,
                    'direction' => 'in',
                    'cashflow_category_id' => 9, // входящий остаток
                    'expense_category_id' => null,
                    'supplier_id' => null,
                    'user_id' => auth()->id(),
                    'account_id' => $accountId,
                    'amount' => $amount,
                    'subcategory' => 'Входящий остаток',
                    'counterparty' => null,
                    'related_table' => null,
                    'related_id' => null,
                    'comment' => 'Историческое сальдо на ' . $date,
                ]);
                $created++;
            }

            // Поставщики — кредиторка (реальный долг без привязки к заказу)
            // и зачёты (деньги, которые уже держит поставщик на балансе).
            foreach (($request->supplier_debts ?? []) as $supplierId => $amount) {
                $amount = (float) $amount;
                if ($amount <= 0) {
                    continue;
                }

                $supplier = Suppliers::find($supplierId);

                SupplierSettlement::create([
                    'order_id' => null,
                    'product_id' => null,
                    'supplier' => $supplier?->name,
                    'supplier_id' => $supplierId,
                    'sum' => -$amount,
                    'date' => $date,
                    'operation' => 'realization',
                    'payment_due_date' => null,
                ]);
                $created++;
            }

            foreach (($request->supplier_credits ?? []) as $supplierId => $amount) {
                $amount = (float) $amount;
                if ($amount <= 0) {
                    continue;
                }

                SupplierCredit::create([
                    'supplier_id' => $supplierId,
                    'amount' => $amount,
                    'source_table' => null,
                    'source_id' => null,
                    'comment' => 'Историческое сальдо на ' . $date,
                    'date' => $date,
                ]);
                $created++;
            }

            // Клиенты — дебиторка. Заводим как "псевдозаказ" без оплаты,
            // потому что дебиторка от клиентов считается именно от заказов
            // (sum_with_margine минус оплаченное) — другого входа в эту
            // цифру в системе нет. sale_channel специально не входит в
            // список отслеживаемых каналов ($channelKeys в index()), чтобы
            // не искажать статистику по каналам продаж задним числом.
            $rawLines = array_filter(array_map('trim', explode("\n", (string) $request->customer_receivables_raw)));

            foreach ($rawLines as $line) {
                $parts = array_map('trim', explode(';', $line));
                if (count($parts) < 3) {
                    continue;
                }

                [$phone, $name, $amount] = $parts;
                $amount = (float) $amount;

                if ($amount <= 0 || $phone === '') {
                    continue;
                }

                $customer = $this->getOrCreateCustomer($phone, $name ?: null);

                Order::create([
                    'user_id' => auth()->id(),
                    'customer_id' => $customer?->id,
                    'date' => $date,
                    'time' => date('H:i:s'),
                    'sum' => $amount,
                    'sum_with_margine' => $amount,
                    'status' => 'заказано',
                    'customer_phone' => $customer?->phone ?? $phone,
                    'sale_channel' => 'opening_balance',
                ]);
                $created++;
            }
        });

        return back()->with([
            'message' => "Начальные остатки внесены: {$created} записей.",
        ]);
    }

    public function supplierPayment(Request $request)
    {
        // ВАЖНО: раньше писалась только запись в supplier_settlement, но
        // остаток долга по поставщику (getSuppliersSettlements()) считается
        // ИСКЛЮЧИТЕЛЬНО из CashflowTransactions (direction=out,
        // cashflow_category_id=3) — без неё кредиторка на дашборде не
        // уменьшалась ни на тенге, кнопка была фактически нерабочей.
        // Заодно поле формы называлось "supplier", но реально отправляло
        // ID из <select>, а не имя — тот же баг, что чинили во fromStock.
        $supplier = Suppliers::findOrFail($request->supplier_id);

        $cashflowTransaction = CashflowTransactions::create([
            'txn_at' => $request->date,
            'direction' => 'out',
            'cashflow_category_id' => 3, // оплата поставщику
            'expense_category_id' => null,
            'supplier_id' => $supplier->id,
            'user_id' => auth()->id(),
            'account_id' => $request->account_id,
            'amount' => $request->sum,
            'subcategory' => 'Оплата поставщику',
            'counterparty' => $supplier->name,
            'related_table' => 'suppliers',
            'related_id' => $supplier->id,
            'comment' => $request->comment ?: 'Оплата поставщику ' . $supplier->name,
        ]);

        SupplierSettlement::create([
            'supplier' => $supplier->name,
            'supplier_id' => $supplier->id,
            'sum' => $request->sum,
            'date' => $request->date,
            'operation' => 'payment',
        ]);

        return back()
            ->with('message', 'Оплата успешно проведена!')
            ->with('class', 'alert-success');
    }

    /**
     * Зеркало supplierPayment() — деньги, которые поставщик возвращает
     * НАМ, не привязанные к оформленному возврату клиента (для этого уже
     * есть отдельный путь через CustomerReturnController::update() —
     * там при supplier_refund_status=received с mode=account тоже
     * создаётся приходная CashflowTransactions). Нужен для случаев без
     * самого возврата в системе — легаси-долги поставщиков с ДО
     * внедрения ERP, будущие корректировки цены/недопоставки и т.п.
     * (запрошено Романом 2026-09-02).
     *
     * ВАЖНО: остаток по поставщику (getSuppliersSettlements()) считает
     * accrued ИСКЛЮЧИТЕЛЬНО из supplier_settlement где operation=
     * 'realization' — сумма в CashflowTransactions.category=4 сама по
     * себе на баланс НЕ влияет (paid там считается только по исходящим
     * category=3). Поэтому, в отличие от supplierPayment() (где
     * operation='payment' — по факту не участвует в этом расчёте вообще,
     * баланс двигает только сама CashflowTransactions), здесь
     * ОБЯЗАТЕЛЬНО нужна именно 'realization' с отрицательной суммой —
     * иначе часть "Переплата поставщикам" не уменьшится ни на тенге.
     */
    public function receiveSupplierRefund(Request $request)
    {
        $supplier = Suppliers::findOrFail($request->supplier_id);

        CashflowTransactions::create([
            'txn_at' => $request->date,
            'direction' => 'in',
            'cashflow_category_id' => 4, // возврат от поставщика
            'expense_category_id' => null,
            'supplier_id' => $supplier->id,
            'user_id' => auth()->id(),
            'account_id' => $request->account_id,
            'amount' => $request->sum,
            'subcategory' => 'Получено от поставщика (не по возврату клиента)',
            'counterparty' => $supplier->name,
            'related_table' => 'suppliers',
            'related_id' => $supplier->id,
            'comment' => $request->comment ?: 'Получено от поставщика ' . $supplier->name,
        ]);

        SupplierSettlement::create([
            'supplier' => $supplier->name,
            'supplier_id' => $supplier->id,
            'sum' => -$request->sum,
            'date' => $request->date,
            'operation' => 'realization',
        ]);

        return back()
            ->with('message', 'Поступление от поставщика зафиксировано!')
            ->with('class', 'alert-success');
    }


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
    
    public function changeStatus(Request $request)
    {
        $data = $request['data'];
        $product = OrderProduct::find($data['product_id']);
        
        // "returned" здесь больше не обрабатывается отдельной веткой —
        // раньше просто обнуляла позицию целиком без учёта частичного
        // возврата и без связи с реальными деньгами. Единственная точка
        // входа для возврата теперь makeCustomerReturn() — она сама
        // проставляет статус/суммы пропорционально фактически
        // возвращённому количеству. Если это значение всё же придёт сюда
        // (не должно — убрано из выпадающего списка), падаем в обычную
        // ветку ниже: просто меняем статус, ничего не обнуляем.
        if ($data['new_status'] == 'arrived_at_the_point_of_delivery') {
            $product->status = $data['new_status'];
            $product->save();

            // Кредиторка по on_receipt/deferred_after_receipt наступает
            // именно сейчас, а не по оценке срока доставки при оформлении
            // заказа (см. manuallyMakeOrder()) — поставщика никогда не
            // угадать заранее. prepaid сюда не попадает, для него дата
            // оплаты уже зафиксирована в момент оформления заказа.
            $paymentDueDate = match ($product->payment_policy_snapshot) {
                'on_receipt' => now()->toDateString(),
                'deferred_after_receipt' => now()->addDays((int) $product->payment_delay_days_snapshot)->toDateString(),
                default => null,
            };

            if ($paymentDueDate !== null) {
                SupplierSettlement::where('product_id', $product->id)
                    ->update(['payment_due_date' => $paymentDueDate]);
            }
        } elseif ($data['new_status'] == 'issued') {
            $product->status = $data['new_status'];
            $product->save();

            // Kaspi платит только после того, как клиент реально получит
            // заказ — раньше заказ заводился в системе только в этот
            // момент, но так кредиторка перед поставщиком не отражалась
            // сразу (см. обсуждение 2026-08-31). Теперь заказ заводится
            // сразу, а фактическое поступление денег от Kaspi привязано
            // именно к этому статусу — "выдано" на КАЖДОЙ позиции заказа
            // (Kaspi платит за весь заказ разом, не по позициям).
            $order = Order::find($product->order_id);

            // kaspi_bypassed — заказ пришёл через Kaspi, но оформлен мимо их
            // магазина: оплата уже получена обычным путём при создании
            // заказа (см. manuallyMakeOrder()), ждать автовыплату от Kaspi
            // после выдачи не нужно, это не тот случай (просьба Романа
            // 2026-09-18).
            if ($order && $order->sale_channel === 'kaspi' && !$order->kaspi_bypassed) {
                $allResolved = !OrderProduct::where('order_id', $order->id)
                    ->whereNotIn('status', ['issued', 'returned'])
                    ->exists();

                if ($allResolved) {
                    $alreadyPaid = (float) OrderPayment::where('order_id', $order->id)
                        ->selectRaw("SUM(CASE WHEN type = 'payment' THEN amount WHEN type = 'refund' THEN -amount ELSE 0 END) as paid")
                        ->value('paid');

                    $stillOwed = round((float) $order->sum_with_margine - $alreadyPaid, 2);

                    if ($stillOwed > 0) {
                        $kaspiPayAccount = Accounts::where('name', 'Рома Kaspi Pay')->first();

                        if ($kaspiPayAccount) {
                            $orderPayment = OrderPayment::create([
                                'order_id' => $order->id,
                                'account_id' => $kaspiPayAccount->id,
                                'paid_at' => now()->format('Y-m-d'),
                                'amount' => $stillOwed,
                                'type' => 'payment',
                                'comment' => 'Автоматическое поступление от Kaspi по факту выдачи заказа',
                            ]);

                            CashflowTransactions::create([
                                'txn_at' => $orderPayment->paid_at,
                                'direction' => 'in',
                                'cashflow_category_id' => 1, // оплата по заказу
                                'expense_category_id' => null,
                                'supplier_id' => null,
                                'user_id' => auth()->id(),
                                'account_id' => $kaspiPayAccount->id,
                                'amount' => $stillOwed,
                                'subcategory' => 'Оплата по заказу (Kaspi, авто)',
                                'counterparty' => $order->customer_phone,
                                'related_table' => 'orders',
                                'related_id' => $order->id,
                                'comment' => 'Автоматическое поступление от Kaspi по заказу №' . $order->id,
                            ]);
                        }
                    }
                }
            }
        } else {
            $product->status = $data['new_status'];
            $product->save();
        }


        return [
            'message' => 'Статус успешно изменен!',
            'status' => $data['new_status']
        ];
    }

    public function additionalPayment(Request $request)
    {
        $orders = Order::where('created_at', '>=', now()->subMonth())->get();

        return json_encode($orders);
    }

    public function makeCashflowTransaction(Request $request)
    {
        //dd($request);
        
        $relatedTable = null;
        $relatedId = null;

        if ($request->cashflow_categories_id == 1 && $request->filled('order_id')) {
            $relatedTable = 'orders';
            $relatedId = $request->order_id;
        }

        if (in_array($request->cashflow_category_id, [3, 4]) && $request->filled('supplier_settlement_id')) {
            $relatedTable = 'supplier_settlement';
            $relatedId = $request->supplier_settlement_id;
        }
        $newCashflowTransaction = CashflowTransactions::create([
            'txn_at' => $request->txn_at, // дата фактической оплаты
            'direction' => $request->direction,
            'cashflow_category_id' => $request->cashflow_categories_id, // например: "Оплата от клиента"
            'expense_category_id' => $request->expense_categories_id ?? null,
            'supplier_id' => $request->supplier_id,
            'user_id' => auth()->id(),
            'account_id' => $request->account_id,
            'amount' => $request->amount,
            'subcategory' => $request->subcategory ?? null,
            'counterparty' => $request->counterparty ?? null,
            'related_table' => $relatedTable,
            'related_id' => $relatedId,
            'comment' => $request->comment ?? null,
        ]);

        // Роман 2026-09-03: доплаты по заказу заводятся ТОЛЬКО через эту
        // общую форму ДДС (не через форму самого заказа — там пришлось бы
        // заново вбивать товарные данные, которые уже есть в заказе). Но
        // дебиторка клиентов (getFinanceDashboardData) считается по
        // отдельной таблице order_payments, не по cashflow_transactions —
        // без этой синхронизации доплата корректно ложится в кассу, но
        // клиент продолжает висеть в дебиторке как должник. Заводим и
        // здесь, автоматически, при каждой такой записи.
        if ($relatedTable === 'orders') {
            OrderPayment::create([
                'order_id' => $relatedId,
                'account_id' => $request->account_id,
                'paid_at' => $request->txn_at,
                'amount' => $request->amount,
                'type' => $request->direction === 'out' ? 'refund' : 'payment',
                'comment' => $request->comment ?? null,
            ]);
        }

        return back()->with([
            'message' => 'Запись успешно создана!'
        ]);
    }

    /**
     * Перевод между СВОИМИ счетами (запрошено Романом 2026-09-02) —
     * например перепутал счёт при внесении и хочет выровнять баланс, или
     * реально нужно перекинуть деньги (клиент заплатил на Kaspi Pay, а
     * поставщику может заплатить только с Kaspi Gold). В отличие от
     * обычной формы ДДС — здесь ОДНА отправка создаёт СРАЗУ две
     * проводки (расход с одного счёта + приход на другой), одной суммой,
     * так что общий остаток по всем счетам не сдвигается, меняется
     * только то, где именно лежат деньги. Категория 6 "transfer" уже
     * существовала в cashflow_categories, просто раньше не было формы,
     * которая создавала бы обе стороны атомарно.
     */
    public function transferBetweenAccounts(Request $request)
    {
        $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|different:from_account_id|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $fromAccount = Accounts::findOrFail($request->from_account_id);
        $toAccount = Accounts::findOrFail($request->to_account_id);
        $date = $request->txn_at ?: now();

        DB::transaction(function () use ($request, $fromAccount, $toAccount, $date) {
            CashflowTransactions::create([
                'txn_at' => $date,
                'direction' => 'out',
                'cashflow_category_id' => 6,
                'expense_category_id' => null,
                'supplier_id' => null,
                'user_id' => auth()->id(),
                'account_id' => $fromAccount->id,
                'amount' => $request->amount,
                'subcategory' => 'Перевод между счетами',
                'counterparty' => $toAccount->name,
                'comment' => $request->comment ?: "Перевод на «{$toAccount->name}»",
            ]);

            CashflowTransactions::create([
                'txn_at' => $date,
                'direction' => 'in',
                'cashflow_category_id' => 6,
                'expense_category_id' => null,
                'supplier_id' => null,
                'user_id' => auth()->id(),
                'account_id' => $toAccount->id,
                'amount' => $request->amount,
                'subcategory' => 'Перевод между счетами',
                'counterparty' => $fromAccount->name,
                'comment' => $request->comment ?: "Перевод с «{$fromAccount->name}»",
            ]);
        });

        return back()
            ->with('message', "Переведено {$request->amount} ₸: {$fromAccount->name} → {$toAccount->name}")
            ->with('class', 'alert-success');
    }

    public function manuallyMakeOrder(Request $request)
    {
        $orderSumWithMargine = 0;
        $orderSum = 0;

        foreach ($request->data['products'] as $product) {
            $orderSumWithMargine += ((float)$product[3] * (float)$product[5]);
            $orderSum += ((float)$product[3] * (float)$product[4]);
        }

        $phone = preg_replace('/\D+/', '', $request->data['orderInfo'][3]);

        $phoneRaw = $request->data['orderInfo'][3];
        $customerName = $request->data['orderInfo'][2] ?? null;

        // Пытаемся найти/создать клиента
        $customer = $this->getOrCreateCustomer($phoneRaw, $customerName);

        if (strlen($phone) === 11 && $phone[0] === '8') {
            $phone[0] = '7';
        }

        if (strlen($phone) === 10) {
            $phone = '7' . $phone;
        }

        $phone = '+' . $phone;

        $customerName = $request->data['orderInfo'][2] ?? null;

        $newCustomer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $customerName,
                'comment' => ''
            ]
        );

        if (!$newCustomer->name && $customerName) {
            $newCustomer->update([
                'name' => $customerName
            ]);
        }

        $order = Order::create([
            'user_id' => $request->data['orderInfo'][0],
            'customer_id' => $customer?->id, // Теперь заказ связан с клиентом!
            'date' => $request->data['orderInfo'][1],
            'time' => date('H:i:s'),
            'sum' => $orderSum,
            'sum_with_margine' => $orderSumWithMargine,
            'status' => 'заказано',
            'customer_phone' => $customer?->phone ?? $phoneRaw,
            'sale_channel' => $request->data['orderInfo'][4],
            // Клиент пришёл через Kaspi, но оформился мимо магазина Kaspi —
            // комиссию не платим, но канал привлечения остаётся "kaspi"
            // (просьба Романа 2026-09-18, см. миграцию add_kaspi_bypassed).
            // Не в orderInfo (позиционный массив) — отдельным полем в payload.
            'kaspi_bypassed' => (bool) ($request->data['kaspiBypassed'] ?? false),
        ]);

        // Kaspi-заказы с отложенной оплатой (не kaspi_bypassed) приходят сюда
        // с paymentInfo[2]=0 — JS-форма намеренно обнуляет сумму оплаты для
        // такого канала (см. admin.js: saleChannel==='kaspi' && !kaspiBypassed
        // => amount=0), потому что реальной оплаты ещё не было, деньги придут
        // от Kaspi позже, отдельным автоплатежом при выдаче заказа (см.
        // changeStatus()). Раньше это всё равно создавало настоящую строку в
        // order_payments/cashflow_transactions с amount=0.00 — "Оплата по
        // заказу" на 0₸, которой физически не было (жалоба Романа 2026-09-19
        // на заказ №2052). Ни платежа, ни возврата на 0 не бывает по смыслу —
        // пропускаем обе записи целиком, если реальных денег не было.
        $cashflowAmountRaw = (float) $request->data['paymentInfo'][2];

        if ($cashflowAmountRaw > 0) {
            $orderPayment = OrderPayment::create([
                'order_id' => $order->id,
                'account_id' => $request->data['paymentInfo'][0],
                'paid_at' => $request->data['paymentInfo'][1],
                'amount' => $request->data['paymentInfo'][2],
                'type' => $request->data['paymentInfo'][3],
                'comment' => $request->data['paymentInfo'][4],
            ]);

            $cashflowDirection = $orderPayment->type === 'refund' ? 'out' : 'in';
            $cashflowAmount = (float)$orderPayment->amount;

            $cashflowTransaction = CashflowTransactions::create([
                'txn_at' => $orderPayment->paid_at,
                'direction' => $cashflowDirection,
                'cashflow_category_id' => 1,
                'expense_category_id' => null,
                'supplier_id' => null,
                'user_id' => auth()->id(),
                'account_id' => $orderPayment->account_id,
                'amount' => $cashflowAmount,
                'subcategory' => $orderPayment->type === 'refund' ? 'Возврат по заказу' : 'Оплата по заказу',
                'counterparty' => $phone,
                'related_table' => 'orders',
                'related_id' => $order->id,
                'comment' => ($orderPayment->type === 'refund' ? 'Возврат по заказу №' : 'Оплата по заказу №') . $order->id,
            ]);
        }

        // Снимок сценария репрайсинга (kaspi:reprice) на момент продажи —
        // только для канала kaspi, только этот запрос на весь заказ разом
        // (не по одной строке за раз). Раньше это нигде не сохранялось,
        // и через месяц узнать, в каком сценарии (etalon_competitive /
        // beat_tomorrow_competitor / min_margin_dumping / etc.) была
        // продана конкретная позиция, можно было только реконструкцией
        // задним числом — ненадёжно, конкуренты и цены меняются каждый
        // день. См. миграцию add_price_strategy_to_order_product_table.
        $saleChannel = $request->data['orderInfo'][4];
        $priceStrategyByArticle = [];
        if ($saleChannel === 'kaspi') {
            $articles = array_column($request->data['products'], 0);
            $priceStrategyByArticle = DB::table('kaspi_feed_items')
                ->whereIn('our_article', $articles)
                ->where('is_active', 1)
                ->orderByDesc('updated_at')
                ->get(['our_article', 'price_strategy'])
                ->unique('our_article')
                ->pluck('price_strategy', 'our_article')
                ->all();
        }

        foreach ($request->data['products'] as $product) {
            $supplierId = (int)$product[6];
            $supplier = Suppliers::find($supplierId);

            $orderProduct = OrderProduct::create([
                'order_id' => $order->id,
                'supplier_id' => $supplierId ?: null,
                'article' => $product[0],
                'brand' => $product[1],
                'name' => $product[2],
                'price' => (float)$product[4],
                'priceWithMargine' => (float)$product[5],
                'qty' => (int)$product[3],
                'item_sum' => (float)$product[4] * (int)$product[3],
                'itemSumWithMargine' => (float)$product[5] * (int)$product[3],
                'searched_number' => '',
                'fromStock' => $supplier?->name ?? 'Неизвестно', // теперь всегда имя
                'deliveryTime' => $product[7],
                'price_strategy' => $priceStrategyByArticle[$product[0]] ?? null,
                'payment_policy_snapshot' => $supplier?->payment_policy,
                'payment_delay_days_snapshot' => $supplier?->payment_delay_days ?? 0,
                'status' => 'payment_waiting'
            ]);
            // Дата оплаты для on_receipt/deferred_after_receipt больше НЕ
            // считается от заявленного при оформлении срока доставки
            // (deliveryTime) — поставщик может привезти раньше или позже
            // обещанного, угадать нельзя. Кредиторка по этим двум политикам
            // теперь наступает только когда сам подтвердишь поступление в
            // ПВЗ — см. changeStatus(), кейс 'arrived_at_the_point_of_delivery'.
            // Только prepaid известен сразу — платим в день оформления
            // заказа, доставка тут ни при чём.
            $paymentDueDate = $orderProduct->payment_policy_snapshot === 'prepaid'
                ? $request->data['orderInfo'][1]
                : null;

            SupplierSettlement::create([
                'order_id' => $order->id,
                'product_id' => $orderProduct->id,
                'supplier' => $supplier?->name,
                'supplier_id' => $supplierId ?: null,
                'sum' => -((float)$product[4] * (int)$product[3]),
                'date' => $request->data['orderInfo'][1],
                'operation' => 'realization',
                'payment_due_date' => $paymentDueDate,
            ]);

            // Автоматическая оплата предоплатным поставщикам (и автосписание
            // зачёта) убраны целиком по прямой просьбе Романа 2026-09-19 —
            // взаимодействие зачёта и автоплатежа регулярно расходилось
            // ("Долг" vs "Сальдо", двойные списания — см. историю правок
            // выше в git blame) и давало путаницу, которую тяжело было
            // диагностировать постфактум. Теперь ЛЮБОЙ поставщик (включая
            // бывших "prepaid" вроде Автотрейда) просто копит долг в
            // supplier_settlement как обычно (см. выше), а оплату Роман
            // заводит вручную через supplierPayment() ("Оплата поставщику"),
            // когда сам решит — без автоматики, без различий по типу
            // поставщика. Существующие остатки в supplier_credits (Тисс,
            // Кулан и т.п.) этим не удаляются, но и не расходуются
            // автоматически — Роман учитывает их сам при следующей оплате.
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
        $order->save();

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
