<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FinanceDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $kpi = [
            'balance' => 1284500,
            'income' => 865000,
            'expense' => 534000,
            'net_flow' => 331000,
            'customer_returns' => 84100,
            'supplier_refunds' => 38652,
            'supplier_debt' => 742300,
        ];

        $accountsSummary = [
            [
                'name' => 'Kaspi Gold (Roman)',
                'income' => 350000,
                'expense' => 120000,
                'balance' => 230000,
            ],
            [
                'name' => 'Kaspi Gold (Igor)',
                'income' => 120000,
                'expense' => 45000,
                'balance' => 75000,
            ],
            [
                'name' => 'Kaspi Pay',
                'income' => 280000,
                'expense' => 160000,
                'balance' => 120000,
            ],
            [
                'name' => 'Halyk (Roman)',
                'income' => 65000,
                'expense' => 15000,
                'balance' => 50000,
            ],
            [
                'name' => 'Cash',
                'income' => 50000,
                'expense' => 10000,
                'balance' => 40000,
            ],
        ];

        $expenseBreakdown = [
            [
                'name' => 'Google Ads',
                'amount' => 120000,
            ],
            [
                'name' => 'Аренда',
                'amount' => 180000,
            ],
            [
                'name' => 'ГСМ',
                'amount' => 45000,
            ],
            [
                'name' => 'OLX',
                'amount' => 30000,
            ],
            [
                'name' => 'Еда',
                'amount' => 12000,
            ],
            [
                'name' => 'Прочее',
                'amount' => 147000,
            ],
        ];

        $returnsStats = [
            'open_count' => 3,
            'closed_count' => 7,
            'customer_paid' => 84100,
            'supplier_received' => 38652,
            'loss' => 45448,
        ];

        $latestTransactions = [
            [
                'txn_at' => now()->subHours(1)->format('Y-m-d H:i:s'),
                'direction' => 'in',
                'subcategory' => 'Возврат от поставщика по заказу №1121',
                'counterparty' => 'TSS',
                'account_name' => 'Kaspi Pay',
                'amount' => 22312,
            ],
            [
                'txn_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
                'direction' => 'out',
                'subcategory' => 'Возврат клиенту',
                'counterparty' => '+77073176705',
                'account_name' => 'Kaspi Gold (Roman)',
                'amount' => 34600,
            ],
            [
                'txn_at' => now()->subHours(4)->format('Y-m-d H:i:s'),
                'direction' => 'out',
                'subcategory' => 'Оплата Google Ads',
                'counterparty' => 'Google',
                'account_name' => 'Kaspi Gold (Roman)',
                'amount' => 45000,
            ],
            [
                'txn_at' => now()->subHours(6)->format('Y-m-d H:i:s'),
                'direction' => 'out',
                'subcategory' => 'Оплата поставщику',
                'counterparty' => 'ATPTR',
                'account_name' => 'Kaspi Pay',
                'amount' => 125000,
            ],
            [
                'txn_at' => now()->subHours(8)->format('Y-m-d H:i:s'),
                'direction' => 'in',
                'subcategory' => 'Оплата заказа #1542',
                'counterparty' => '+77078508810',
                'account_name' => 'Cash',
                'amount' => 75000,
            ],
        ];

        return view('dashboard.finance', compact(
            'dateFrom',
            'dateTo',
            'kpi',
            'accountsSummary',
            'expenseBreakdown',
            'returnsStats',
            'latestTransactions'
        ));
    }
}
