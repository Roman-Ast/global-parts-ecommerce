<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Accounts;
use App\Models\CashflowTransactions;
use App\Models\Order;
use App\Models\SupplierCredit;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Форма разового ввода исторических остатков перед go-live ERP
 * (2026-08-31): счета, кредиторка/зачёты по поставщикам, дебиторка по
 * клиентам — заносятся без привязки к реальным заказам, но корректно
 * подхватываются существующей отчётностью.
 */
class OpeningBalancesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_opening_balances_form_seeds_accounts_supplier_debt_credit_and_customer_receivable(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $account = Accounts::where('is_active', 1)->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();

        $accountBalanceBefore = (float) CashflowTransactions::where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as balance")
            ->value('balance');

        $supplierBalanceBefore = (float) (collect(app(AdminPanelController::class)
            ->getSuppliersSettlements(request())['supplierBalances'])
            ->firstWhere('supplier_id', $supplier->id)?->balance ?? 0);

        $supplierCreditBefore = (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount');

        $phone = '+77779990030';

        $this->actingAs($user)->post(route('opening-balances.store'), [
            'date' => now()->format('Y-m-d'),
            'accounts' => [$account->id => 25000],
            'supplier_debts' => [$supplier->id => 12000],
            'supplier_credits' => [$supplier->id => 3000],
            'customer_receivables_raw' => "{$phone};Тест Клиент;7000",
        ])->assertRedirect();

        $accountBalanceAfter = (float) CashflowTransactions::where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as balance")
            ->value('balance');
        $this->assertSame(25000.0, $accountBalanceAfter - $accountBalanceBefore);

        $supplierBalanceAfter = (float) (collect(app(AdminPanelController::class)
            ->getSuppliersSettlements(request())['supplierBalances'])
            ->firstWhere('supplier_id', $supplier->id)?->balance ?? 0);
        $this->assertSame(12000.0, $supplierBalanceAfter - $supplierBalanceBefore);

        $supplierCreditAfter = (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount');
        $this->assertSame(3000.0, $supplierCreditAfter - $supplierCreditBefore);

        $order = Order::where('customer_phone', $phone)->where('sale_channel', 'opening_balance')->first();
        $this->assertNotNull($order);
        $this->assertSame(7000.0, (float) $order->sum_with_margine);

        // Не должен попасть в статистику по каналам продаж.
        $response = $this->actingAs($user)->get('/admin_panel');
        $months = $response->viewData('salesStatisticsByMonth');
        foreach ($months as $month => $channels) {
            $this->assertArrayNotHasKey('opening_balance', $channels);
        }
    }
}
