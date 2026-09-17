<?php

namespace Tests\Feature\Erp;

use App\Models\CashflowTransactions;
use App\Models\OrderProduct;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Живой случай 2026-08-31: форма возврата прислала customer_id заказа
 * вместо customer_id клиента (баг в data-customer-id на фронте, уже
 * исправлен отдельно) — CashflowTransactions успевал создаться, а
 * CustomerReturn::create() падал следом на внешнем ключе, оставляя
 * осиротевшую запись "возврат клиенту" в кассе (задваивало "Возвраты
 * клиентам" на дашборде). makeCustomerReturn() теперь обёрнут в
 * DB::transaction() — проверяем, что при аналогичном сбое НИЧЕГО не
 * остаётся в базе.
 */
class ReturnTransactionAtomicityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_failed_return_leaves_no_orphaned_cashflow_transaction(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rmtk')->firstOrFail();

        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Атомарность', '+77779990099', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 5000, 'payment', 'тест'],
                'products' => [
                    ['ART-ATOMIC', 'TESTBRAND', 'Тестовая деталь', 1, 3000, 5000, $supplier->id, 'Сегодня'],
                ],
            ],
        ])->assertStatus(200);

        $orderProduct = OrderProduct::where('article', 'ART-ATOMIC')->latest('id')->firstOrFail();
        $cashflowCountBefore = CashflowTransactions::count();

        // customer_id заведомо несуществующий — воспроизводит ровно тот
        // FK-сбой, что случился в реальности.
        $response = $this->actingAs($user)->post('/makeCustomerReturn', [
            'order_id' => $orderProduct->order_id,
            'order_product_id' => $orderProduct->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'user_id' => $user->id,
            'customer_phone' => '+77779990099',
            'customer_id' => 999999999,
            'qty' => 1,
            'sale_price' => 5000,
            'customer_refund_amount' => 5000,
            'supplier_purchase_price' => 3000,
            'supplier_refund_amount' => 3000,
            'customer_refund_paid' => 5000,
            'return_date' => now()->format('Y-m-d'),
            'account_id_out' => 1,
            'reason' => 'тест',
            'comment' => 'тест атомарности',
            'status' => 'pending',
        ]);

        $this->assertSame(500, $response->status(), 'Ожидаем реальный сбой на внешнем ключе.');

        $this->assertSame(
            $cashflowCountBefore,
            CashflowTransactions::count(),
            'После отката не должно остаться ни одной новой записи в кассе.'
        );

        $orderProduct->refresh();
        $this->assertSame(1, (int) $orderProduct->qty, 'Позиция заказа не должна была измениться при откате.');
    }
}
