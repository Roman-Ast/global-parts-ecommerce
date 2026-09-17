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
 * Проверка сценариев из "сценарии работы Global Parts.txt" на реальной
 * локальной БД, каждый тест в транзакции с автоматическим откатом
 * (DatabaseTransactions) — ничего не остаётся в базе после прогона.
 *
 * Сценарий 1: покупка у поставщика с отсрочкой платежа, товар в наличии.
 * Клиент оплатил 100%, забрали товар у поставщика без оплаты — формируется
 * кредиторка. Дата оплаты НЕ считается от срока доставки — только когда
 * админ вручную подтвердит статус "поступило в ПВЗ" (см. правку
 * changeStatus()/manuallyMakeOrder() от 2026-08-31).
 */
class PurchaseScenariosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deferred_payment_supplier_in_stock_creates_creditor_debt_without_due_date_until_arrival(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rssk')->firstOrFail(); // Росско: deferred_after_receipt, 5 дней

        $this->assertSame('deferred_after_receipt', $supplier->payment_policy);
        $this->assertSame(5, (int) $supplier->payment_delay_days);

        $response = $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Тестов', '+77771234567', 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 20000, 'payment', 'тестовая оплата'],
                'products' => [
                    ['ART-1', 'TESTBRAND', 'Тестовая деталь', 1, 10000, 20000, $supplier->id, 'Сегодня-завтра'],
                ],
            ],
        ]);

        $response->assertStatus(200);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(10000.0, (float) $order->sum);
        $this->assertSame(20000.0, (float) $order->sum_with_margine);

        $orderProduct = OrderProduct::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('payment_waiting', $orderProduct->status);
        $this->assertSame($supplier->name, $orderProduct->fromStock);
        $this->assertSame('deferred_after_receipt', $orderProduct->payment_policy_snapshot);
        $this->assertSame(5, (int) $orderProduct->payment_delay_days_snapshot);

        $settlement = SupplierSettlement::where('product_id', $orderProduct->id)->firstOrFail();
        $this->assertSame('realization', $settlement->operation);
        $this->assertSame(-10000.0, (float) $settlement->sum);
        $this->assertNull($settlement->payment_due_date, 'До подтверждения поступления в ПВЗ дата оплаты не должна быть известна.');

        // Кредиторка перед поставщиком должна отразиться в дашборде уже сейчас,
        // ещё до того, как мы вообще что-то заплатили.
        $before = $this->actingAs($user)->post('/product/change_status', [
            'data' => ['product_id' => $orderProduct->id, 'new_status' => 'payment_waiting'],
        ]);
        $before->assertStatus(200);

        // Подтверждаем поступление в ПВЗ — только теперь должна появиться дата оплаты.
        $arrive = $this->actingAs($user)->post('/product/change_status', [
            'data' => ['product_id' => $orderProduct->id, 'new_status' => 'arrived_at_the_point_of_delivery'],
        ]);
        $arrive->assertStatus(200);

        $settlement->refresh();
        $this->assertNotNull($settlement->payment_due_date);
        $this->assertSame(
            now()->addDays(5)->toDateString(),
            \Carbon\Carbon::parse($settlement->payment_due_date)->toDateString()
        );
    }
}
