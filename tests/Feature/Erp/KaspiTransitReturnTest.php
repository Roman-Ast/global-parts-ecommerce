<?php

namespace Tests\Feature\Erp;

use App\Models\OrderProduct;
use App\Models\Suppliers;
use App\Models\SupplierSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Возврат на Kaspi во время доставки: клиент отказался от посылки в пути,
 * деньги от Kaspi так и не поступили (в отличие от обычного возврата, тут
 * НЕЧЕГО возвращать клиенту — денег от него/Kaspi мы не видели), но
 * кредиторка перед поставщиком с отсрочкой всё равно должна закрыться,
 * когда мы вернём ему товар.
 */
class KaspiTransitReturnTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kaspi_transit_refusal_clears_creditor_debt_without_any_customer_cash_movement(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail(); // deferred_after_receipt

        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Kaspi Отказ В Пути', '+77779990003', 'kaspi'],
                // Kaspi ещё не платил — amount=0, ровно как в реальности до доставки.
                'paymentInfo' => [1, now()->format('Y-m-d'), 0, 'payment', 'ожидаем выплату Kaspi'],
                'products' => [
                    ['ART-KASPI-REFUSED', 'TESTBRAND', 'Тестовая деталь, отказ в пути', 1, 7000, 12000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ])->assertStatus(200);

        $orderProduct = OrderProduct::where('article', 'ART-KASPI-REFUSED')->latest('id')->firstOrFail();

        $before = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')->firstOrFail();
        $this->assertSame(-7000.0, (float) $before->sum, 'Кредиторка перед поставщиком формируется сразу, ещё до выплаты Kaspi.');

        // Клиент отказался в пути — возвращаем товар поставщику, деньги
        // клиенту возвращать нечего (customer_refund_paid=0, мы их не получали).
        $this->actingAs($user)->post('/makeCustomerReturn', [
            'order_id' => $orderProduct->order_id,
            'order_product_id' => $orderProduct->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'user_id' => $user->id,
            'customer_phone' => '+77779990003',
            'customer_id' => 'нет данных',
            'qty' => 1,
            'sale_price' => 12000,
            'customer_refund_amount' => 0,
            'supplier_purchase_price' => 7000,
            'supplier_refund_amount' => 7000,
            'customer_refund_paid' => 0,
            'return_date' => now()->format('Y-m-d'),
            'account_id_out' => 1,
            'reason' => 'Отказ клиента в пути на Kaspi',
            'comment' => 'тест',
            'status' => 'pending',
        ])->assertRedirect();

        $orderProduct->refresh();
        $this->assertSame('returned', $orderProduct->status);
        $this->assertSame(0.0, (float) $orderProduct->item_sum);

        $after = SupplierSettlement::where('product_id', $orderProduct->id)
            ->where('operation', 'realization')->firstOrFail();
        $this->assertSame(0.0, (float) $after->sum, 'Кредиторка должна полностью закрыться после возврата товара поставщику.');
    }
}
