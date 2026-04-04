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
use Illuminate\Support\Carbon;

class AdminPanelController extends Controller
{
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
        $kaspiComissionFromBegin = Order::where('sale_channel', 'kaspi')->sum('sum_with_margine') * 12 / 100;
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

		//$orders = Order::whereBetween('date', [$start, $end])->orderBy('date', 'desc')->get();
        $orders = Order::whereBetween('created_at', [
            Carbon::parse('2026-02-01')->startOfDay(),
            Carbon::parse('2026-03-25')->endOfDay(),
        ])->orderBy('date','desc')->get();
        $user = auth()->user();
        //$settlements = Setlement::all();
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
        
        $kaspiComission = Order::whereBetween('date', [$start, $end])->where('sale_channel', 'kaspi')->sum('sum_with_margine') * 12 / 100;
        $marginClear = round($totalSalesSum - $totalPrimeCostSum - $kaspiComission);

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

        $financeDashboard = $this->getFinanceDashboardData($request);
        $supplierSettlementsDebts = $this->getSuppliersSettlements($request);
        
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
        ], 
        $financeDashboard, 
        $supplierSettlementsDebts
        ));
    }

    private function getFinanceDashboardData(Request $request): array
    {
        $dateFrom = Carbon::parse(
            $request->get('date_from', now()->startOfMonth()->format('Y-m-d'))
        )->startOfDay();

        $dateTo = Carbon::parse(
            $request->get('date_to', now()->format('Y-m-d'))
        )->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | KPI по ДДС
        |--------------------------------------------------------------------------
        */

        $income = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'in')
            ->sum('amount');

        $expense = CashflowTransactions::whereBetween('txn_at', [$dateFrom, $dateTo])
            ->where('direction', 'out')
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

        $financeKpi = [
            'balance' => round($balance, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net_flow' => round($income - $expense, 2),
            'customer_returns' => round($customerReturnsAmount, 2),
            'supplier_refunds' => round($supplierRefundsAmount, 2),
            'supplier_debt' => round($supplierDebt, 2),
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
        | Возвраты
        |--------------------------------------------------------------------------
        */

        $returnsQuery = CustomerReturn::whereBetween('return_date', [
            $dateFrom->toDateString(),
            $dateTo->toDateString()
        ]);

        $openCount = (clone $returnsQuery)->where('status', 'pending')->count();

        $closedCount = (clone $returnsQuery)->where('status', 'completed')->count();

        $customerPaid = (clone $returnsQuery)->sum('customer_refund_paid');

        $supplierReceived = (clone $returnsQuery)->sum('supplier_refund_received');

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

        $latestTransactionsRows = CashflowTransactions::with('account')
            ->whereBetween('txn_at', [$dateFrom, $dateTo])
            ->orderBy('txn_at', 'desc')
            ->limit(10)
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
            'financeReturnsStats' => $financeReturnsStats,
            'financeLatestTransactions' => $financeLatestTransactions,
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

        // Просроченная кредиторка
        $overdueSupplierDebt = $supplierBalances->sum('overdue_balance');

        return [
            'supplierBalances' => $supplierBalances,
            'supplierDebts' => $supplierDebts,
            'totalSupplierDebt' => $totalSupplierDebt,
            'suppliersWithDebtCount' => $suppliersWithDebtCount,
            'totalSupplierOverpayment' => $totalSupplierOverpayment,
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
        //dd($request);
        //если поставщик сразу вернул деньги или возврат записывается постфактум, когда все процессы завершены
        if ($request->account_id_in) {
            $cashflowTransactionIn = CashflowTransactions::create([
                'txn_at' => $request->return_date, // дата фактической оплаты
                'direction' => 'in',
                'cashflow_category_id' => 4, // например: "Оплата от клиента"
                'expense_category_id' => null,
                'supplier_id' => $request->supplier_id,
                'user_id' => $request->user_id,
                'account_id' => $request->account_id_in,
                'amount' => $request->supplier_refund_received,
                'subcategory' => 'возврат от поставщика',
                'counterparty' => $request->supplier_name ?? null,
                'related_table' => 'customer_returns',
                'related_id' => null,
                'comment' => $request->comment == '' ? 'Возврат от поставщика по заказу №' . $request->order_id : $request->comment,
            ]);
        } 

        //запись в cashflow_transactions расхода по возврату клиенту
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
            'comment' => $request->comment == '' ? 'Возврат клиенту по заказу номер ' . $request->order_id : $request->comment,
        ]);

        $customer_id = $request->customer_id = 'нет данных' ? null : $request->customer_id;

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
            'supplier_refund_received' => $request->supplier_refund_received,
            'return_date' => $request->return_date,
            'customer_refund_date' => $request->customer_refund_date,
            'supplier_refund_date' => $request->supplier_refund_date,
            'closed_at' => $request->closed_at,
            'reason' => $request->reason,
            'comment' => $request->comment,
            'status' => $request->status,
            'supplier_refund_status' => $request->supplier_refund_status,
            'customer_cashflow_transaction_id' => $cashflowTransactionOut->id,
            'supplier_cashflow_transaction_id' => $request->supplier_cashflow_transaction_id,
        ]);

        $cashflowTransactionOut->update(['related_id' => $customer_returns->id]);
        
        if (isset($cashflowTransactionIn)) {
            $cashflowTransactionIn->update(['related_id' => $customer_returns->id]);
        }

        return back()->with([
                'message' => 'возврат успешно сохранен!',
            ]
        );
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

        return back()->with([
            'message' => 'Запись успешно создана!'
        ]);
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
            'customer_id' => $newCustomer->id,
            'date' => $request->data['orderInfo'][1],
            'time' => date('H:i:s'),
            'sum' => $orderSum,
            'sum_with_margine' => $orderSumWithMargine,
            'status' => 'заказано',
            'customer_phone' => $phone,
            'sale_channel' => $request->data['orderInfo'][4]
        ]);

        /*$orderPayment = OrderPayment::create([
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
        ]);*/

        foreach ($request->data['products'] as $product) {
            $supplierId = (int)$product[6];
            $supplierCode = Suppliers::find($supplierId)?->code;

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
                'fromStock' => $supplierCode,
                'deliveryTime' => $product[7],
                'payment_policy_snapshot' => $supplier?->payment_policy,
                'payment_delay_days_snapshot' => $supplier?->payment_delay_days ?? 0,
                'status' => 'payment_waiting'
            ]);
            $paymentDueDate = null;
            $orderDate = $request->data['orderInfo'][1];
            $deliveryDate = $product[7] ?? null;

            if ($orderProduct->payment_policy_snapshot === 'prepaid') {
                $paymentDueDate = $orderDate;
            } elseif ($orderProduct->payment_policy_snapshot === 'on_receipt') {
                $paymentDueDate = $deliveryDate;
            } elseif ($orderProduct->payment_policy_snapshot === 'deferred_after_receipt') {
                $paymentDueDate = $deliveryDate
                    ? date('Y-m-d', strtotime($deliveryDate . ' + ' . (int)$orderProduct->payment_delay_days_snapshot . ' days'))
                    : null;
            }

            SupplierSettlement::create([
                'order_id' => $order->id,
                'product_id' => $orderProduct->id,
                'supplier' => $supplierCode,
                'supplier_id' => $supplierId ?: null,
                'sum' => -((float)$product[4] * (int)$product[3]),
                'date' => $request->data['orderInfo'][1],
                'operation' => 'realization',
                'payment_due_date' => $paymentDueDate,
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
