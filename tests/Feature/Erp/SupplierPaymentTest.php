<?php

namespace Tests\Feature\Erp;

use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Проверка фикса supplierPayment() от 2026-08-31: раньше кнопка "Провести
 * оплату" не создавала CashflowTransactions вообще, поэтому долг перед
 * поставщиком (getSuppliersSettlements()) не уменьшался ни на тенге.
 */
class SupplierPaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_paying_a_supplier_reduces_their_debt_on_the_dashboard(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();
        $account = \App\Models\Accounts::where('is_active', 1)->firstOrFail();

        // Замеряем ДО — в базе могут уже лежать реальные (не тестовые)
        // остатки по этому поставщику, тест должен работать не только на
        // чистой БД, а через разницу до/после.
        $balanceOf = fn () => (float) (collect(app(\App\Http\Controllers\AdminPanelController::class)
            ->getSuppliersSettlements(request())['supplierBalances'])
            ->firstWhere('supplier_id', $supplier->id)?->balance ?? 0);

        $initial = $balanceOf();

        // Формируем кредиторку: покупка с отсрочкой на 10000.
        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Тестов', '+77771234567', 'site'],
                'paymentInfo' => [$account->id, now()->format('Y-m-d'), 20000, 'payment', 'тест'],
                'products' => [
                    ['ART-2', 'TESTBRAND', 'Тестовая деталь 2', 1, 10000, 20000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ])->assertStatus(200);

        $afterPurchase = $balanceOf();
        $this->assertSame(10000.0, $afterPurchase - $initial, 'Покупка на 10000 должна поднять кредиторку ровно на 10000.');

        // Платим поставщику 4000 из реального счёта.
        $this->actingAs($user)->post(route('supplier.payment'), [
            'date' => now()->format('Y-m-d'),
            'sum' => 4000,
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
        ])->assertRedirect();

        $afterPayment = $balanceOf();
        $this->assertSame(6000.0, $afterPayment - $initial, 'Оплата 4000 должна уменьшить добавленный долг 10000 до 6000.');
    }
}
