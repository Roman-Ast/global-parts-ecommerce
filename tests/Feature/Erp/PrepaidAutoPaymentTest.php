<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Автооплата prepaid-поставщиков (2026-08-31): раньше оплата поставщику
 * была отдельным ручным шагом даже для тех, кому по факту всегда платится
 * сразу (Автотрейд и т.п.) — заказ повисал в кредиторке до отдельного
 * "Провести оплату". Теперь для payment_policy=prepaid оплата проводится
 * автоматически при оформлении заказа, с фиксированного счёта "Рома
 * Kaspi Gold".
 */
class PrepaidAutoPaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_prepaid_supplier_order_shows_zero_debt_immediately(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'trd')->firstOrFail(); // Автотрейд: prepaid

        $balanceOf = fn () => (float) (collect(app(AdminPanelController::class)
            ->getSuppliersSettlements(request())['supplierBalances'])
            ->firstWhere('supplier_id', $supplier->id)?->balance ?? 0);

        $initial = $balanceOf();

        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Предоплата', '+77779990010', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 20000, 'payment', 'тест'],
                'products' => [
                    ['ART-PREPAID', 'TESTBRAND', 'Тестовая деталь, предоплата', 1, 10000, 20000, $supplier->id, 'Сегодня'],
                ],
            ],
        ])->assertStatus(200);

        $this->assertSame(0.0, $balanceOf() - $initial, 'У prepaid-поставщика долга висеть не должно — оплата уже проведена автоматически.');
    }
}
