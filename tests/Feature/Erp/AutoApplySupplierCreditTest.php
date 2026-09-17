<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Order;
use App\Models\SupplierCredit;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Роман 2026-09-02: "поставщик всё равно деньги не вернёт, зачёт
 * применится к следующей закупке" — при создании нового заказа система
 * теперь сама проверяет зачёт у поставщика и гасит им новый долг
 * (min(зачёт, сумма позиции)), а не оставляет висеть неиспользованным.
 */
class AutoApplySupplierCreditTest extends TestCase
{
    use DatabaseTransactions;

    private function balanceFor(AdminPanelController $c, int $supplierId): float
    {
        $settlements = $c->getSuppliersSettlements(request());
        $row = collect($settlements['supplierBalances'])->firstWhere('supplier_id', $supplierId);
        return $row ? (float) $row->balance : 0.0;
    }

    public function test_existing_credit_smaller_than_purchase_fully_consumed(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();
        $c = app(AdminPanelController::class);

        SupplierCredit::create([
            'supplier_id' => $supplier->id,
            'amount' => 2726,
            'date' => now()->format('Y-m-d'),
        ]);
        $creditBefore = (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount');
        $balanceBefore = $this->balanceFor($c, $supplier->id);

        $response = $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Тестов', '+77771234567', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 20000, 'payment', 'тестовая оплата'],
                'products' => [
                    ['ART-1', 'TESTBRAND', 'Тестовая деталь', 1, 17644, 25000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ]);

        $response->assertStatus(200);

        // Начислено 17644, из них 2726 сразу погашено зачётом -> баланс
        // вырос ровно на 14918 (17644 - 2726), не на полную сумму покупки.
        $this->assertSame(14918.0, $this->balanceFor($c, $supplier->id) - $balanceBefore);
        // Зачёт полностью израсходован (вернулся к тому, что было до теста).
        $this->assertSame(
            $creditBefore - 2726,
            (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount')
        );
    }

    public function test_existing_credit_larger_than_purchase_leaves_remainder(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();
        $c = app(AdminPanelController::class);

        $creditBefore = (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount');
        SupplierCredit::create([
            'supplier_id' => $supplier->id,
            'amount' => 20000,
            'date' => now()->format('Y-m-d'),
        ]);
        $balanceBefore = $this->balanceFor($c, $supplier->id);

        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Тестов', '+77771234567', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 20000, 'payment', 'тестовая оплата'],
                'products' => [
                    ['ART-1', 'TESTBRAND', 'Тестовая деталь', 1, 5000, 8000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ]);

        // Долг (5000) полностью погашен зачётом (5000 из 20000 доступных) -> баланс не сдвинулся.
        $this->assertSame(0.0, $this->balanceFor($c, $supplier->id) - $balanceBefore);
        // Остаток зачёта сверх того, что было до теста: 20000 - 5000 = 15000.
        $this->assertSame(
            $creditBefore + 15000,
            (float) SupplierCredit::where('supplier_id', $supplier->id)->sum('amount')
        );
    }

    public function test_no_credit_leaves_debt_unaffected(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();
        $c = app(AdminPanelController::class);
        $balanceBefore = $this->balanceFor($c, $supplier->id);

        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Тестов', '+77771234567', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 20000, 'payment', 'тестовая оплата'],
                'products' => [
                    ['ART-1', 'TESTBRAND', 'Тестовая деталь', 1, 9000, 15000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ]);

        $this->assertSame(9000.0, $this->balanceFor($c, $supplier->id) - $balanceBefore);
    }
}
