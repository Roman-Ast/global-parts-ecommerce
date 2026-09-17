<?php

namespace Tests\Feature\Erp;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Suppliers;
use App\Models\SupplierSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Унификация возврата (2026-08-31): раньше "возвращено" выставлялось вручную
 * через выпадающий список статуса заказа и просто обнуляло позицию целиком.
 * Теперь единственная точка входа — makeCustomerReturn(), статус/суммы
 * позиции и заказа проставляются автоматически, пропорционально фактически
 * возвращённому количеству, а кредиторка уменьшается только у поставщиков
 * с отсрочкой платежа.
 */
class CustomerReturnScenariosTest extends TestCase
{
    use DatabaseTransactions;

    private function createDeferredOrder(User $user, Suppliers $supplier, int $qty, float $price, float $priceMargine): OrderProduct
    {
        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Возврат', '+77779998877', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), $priceMargine * $qty, 'payment', 'тест'],
                'products' => [
                    ['ART-RET', 'TESTBRAND', 'Тестовая деталь для возврата', $qty, $price, $priceMargine, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ])->assertStatus(200);

        return OrderProduct::where('article', 'ART-RET')->latest('id')->firstOrFail();
    }

    public function test_partial_return_from_deferred_supplier_reduces_sums_and_creditor_debt_proportionally(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail(); // deferred_after_receipt, 5 дней

        $orderProduct = $this->createDeferredOrder($user, $supplier, qty: 3, price: 1000, priceMargine: 2000);

        $settlementBefore = SupplierSettlement::where('product_id', $orderProduct->id)->firstOrFail();
        $this->assertSame(-3000.0, (float) $settlementBefore->sum);

        // Возвращаем 1 из 3 штук.
        $this->actingAs($user)->post('/makeCustomerReturn', [
            'order_id' => $orderProduct->order_id,
            'order_product_id' => $orderProduct->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'user_id' => $user->id,
            'customer_phone' => '+77779998877',
            'customer_id' => 'нет данных',
            'qty' => 1,
            'sale_price' => 2000,
            'customer_refund_amount' => 2000,
            'supplier_purchase_price' => 1000,
            'supplier_refund_amount' => 1000,
            'customer_refund_paid' => 2000,
            'return_date' => now()->format('Y-m-d'),
            'account_id_out' => 1,
            'reason' => 'тест',
            'comment' => 'частичный возврат',
            'status' => 'pending',
        ])->assertRedirect();

        $orderProduct->refresh();
        $this->assertSame(2, (int) $orderProduct->qty, 'Осталось 2 из 3 купленных.');
        $this->assertSame(2000.0, (float) $orderProduct->item_sum);
        $this->assertSame(4000.0, (float) $orderProduct->itemSumWithMargine);
        $this->assertNotSame('returned', $orderProduct->status, 'Частичный возврат не должен полностью закрывать позицию.');

        $order = Order::find($orderProduct->order_id);
        $this->assertSame(2000.0, (float) $order->sum);
        $this->assertSame(4000.0, (float) $order->sum_with_margine);

        $settlementAfter = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')->firstOrFail();
        $this->assertSame(-2000.0, (float) $settlementAfter->sum, 'Кредиторка должна уменьшиться ровно на возвращённую 1 штуку (1000).');
    }

    public function test_full_return_from_deferred_supplier_zeroes_out_and_marks_returned(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'shtm')->firstOrFail(); // Шатэ-М: deferred_after_receipt, 5 дней

        $orderProduct = $this->createDeferredOrder($user, $supplier, qty: 2, price: 5000, priceMargine: 8000);

        $this->actingAs($user)->post('/makeCustomerReturn', [
            'order_id' => $orderProduct->order_id,
            'order_product_id' => $orderProduct->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'user_id' => $user->id,
            'customer_phone' => '+77779998877',
            'customer_id' => 'нет данных',
            'qty' => 2,
            'sale_price' => 8000,
            'customer_refund_amount' => 16000,
            'supplier_purchase_price' => 5000,
            'supplier_refund_amount' => 10000,
            'customer_refund_paid' => 16000,
            'return_date' => now()->format('Y-m-d'),
            'account_id_out' => 1,
            'reason' => 'тест',
            'comment' => 'полный возврат',
            'status' => 'pending',
        ])->assertRedirect();

        $orderProduct->refresh();
        $this->assertSame(0, (int) $orderProduct->qty);
        $this->assertSame(0.0, (float) $orderProduct->item_sum);
        $this->assertSame(0.0, (float) $orderProduct->itemSumWithMargine);
        $this->assertSame('returned', $orderProduct->status);

        $settlement = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')->firstOrFail();
        $this->assertSame(0.0, (float) $settlement->sum, 'При полном возврате кредиторка по этой позиции должна обнулиться.');
    }

    public function test_return_from_non_deferred_supplier_does_not_touch_creditor_debt(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rmtk')->firstOrFail(); // Армтек: on_receipt, без отсрочки

        $orderProduct = $this->createDeferredOrder($user, $supplier, qty: 1, price: 3000, priceMargine: 5000);

        $settlementBefore = SupplierSettlement::where('product_id', $orderProduct->id)->firstOrFail();

        $this->actingAs($user)->post('/makeCustomerReturn', [
            'order_id' => $orderProduct->order_id,
            'order_product_id' => $orderProduct->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'user_id' => $user->id,
            'customer_phone' => '+77779998877',
            'customer_id' => 'нет данных',
            'qty' => 1,
            'sale_price' => 5000,
            'customer_refund_amount' => 5000,
            'supplier_purchase_price' => 3000,
            'supplier_refund_amount' => 3000,
            'customer_refund_paid' => 5000,
            'return_date' => now()->format('Y-m-d'),
            'account_id_out' => 1,
            'reason' => 'тест',
            'comment' => 'возврат без отсрочки',
            'status' => 'pending',
        ])->assertRedirect();

        $settlementAfter = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')->firstOrFail();

        $this->assertSame(
            (float) $settlementBefore->sum,
            (float) $settlementAfter->sum,
            'У поставщика без отсрочки кредиторка не должна меняться при возврате.'
        );
    }
}
