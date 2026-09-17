<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Accounts;
use App\Models\OrderProduct;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026-08-31: Kaspi платит только после того, как клиент получит заказ.
 * Заказ теперь заводится в системе сразу (кредиторка перед поставщиком
 * формируется как обычно), а дебиторка висит на "Kaspi" (не на клиенте)
 * до момента, когда все позиции заказа переведены в статус "выдано" —
 * тогда деньги автоматически поступают на счёт "Рома Kaspi Pay".
 */
class KaspiDeliveryPayoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_marking_all_items_issued_records_kaspi_payout_and_clears_receivable(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail();
        $kaspiPayAccount = Accounts::where('name', 'Рома Kaspi Pay')->firstOrFail();
        $phone = '+77779990020';

        $balanceOf = fn () => (float) DB::table('cashflow_transactions')
            ->where('account_id', $kaspiPayAccount->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN direction='out' THEN amount ELSE 0 END),0) as balance")
            ->value('balance');

        $accountBefore = $balanceOf();

        // Заказ на Kaspi заведён сразу, деньги ещё не пришли (amount=0).
        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Kaspi Выдача', $phone, 'kaspi'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 0, 'payment', 'Kaspi ещё не заплатил'],
                'products' => [
                    ['ART-KASPI-ISSUE', 'TESTBRAND', 'Тестовая деталь, Kaspi выдача', 1, 30000, 50000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ])->assertStatus(200);

        $orderProduct = OrderProduct::where('article', 'ART-KASPI-ISSUE')->latest('id')->firstOrFail();

        // Кредиторка перед поставщиком должна была сформироваться уже сейчас.
        $balance = collect(app(AdminPanelController::class)
            ->getSuppliersSettlements(request())['supplierBalances'])
            ->firstWhere('supplier_id', $supplier->id);
        $this->assertNotNull($balance);

        // Клиент получил заказ — ставим "выдано".
        $this->actingAs($user)->post('/product/change_status', [
            'data' => ['product_id' => $orderProduct->id, 'new_status' => 'issued'],
        ])->assertStatus(200);

        $orderProduct->refresh();
        $this->assertSame('issued', $orderProduct->status);

        $this->assertSame(
            50000.0,
            $balanceOf() - $accountBefore,
            'После выдачи вся сумма заказа должна поступить на счёт "Рома Kaspi Pay".'
        );

        // Повторное нажатие "выдано" не должно задваивать поступление.
        $this->actingAs($user)->post('/product/change_status', [
            'data' => ['product_id' => $orderProduct->id, 'new_status' => 'issued'],
        ])->assertStatus(200);

        $this->assertSame(
            50000.0,
            $balanceOf() - $accountBefore,
            'Повторное выставление "выдано" не должно создавать вторую выплату.'
        );
    }
}
