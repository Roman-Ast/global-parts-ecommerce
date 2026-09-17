<?php

namespace Tests\Feature\Erp;

use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Частичная оплата: клиент заплатил не 100%, а часть суммы. Дебиторка
 * (getReceivablesData()) считает недоплату как order.sum_with_margine минус
 * сумма реальных OrderPayment — проверяем, что это действительно так.
 *
 * Тот же механизм покрывает и "отложенную выплату Kaspi" — пока Kaspi не
 * прислал деньги, OrderPayment по заказу не создан вообще (или создан на
 * 0), и вся сумма заказа висит в дебиторке до момента реальной выплаты.
 */
class CustomerReceivablesTest extends TestCase
{
    use DatabaseTransactions;

    private function receivableFor(string $label): float
    {
        $response = $this->actingAs(User::where('user_role', 'admin')->firstOrFail())->get('/admin_panel');

        $row = collect($response->viewData('customerReceivables'))->firstWhere('name', $label);

        return (float) ($row['amount'] ?? 0);
    }

    public function test_partial_customer_payment_shows_remaining_amount_as_receivable(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rmtk')->firstOrFail();
        $phone = '+77779990001';

        // Клиент платит только половину: 10000 из 20000.
        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Частичная Оплата', $phone, 'site'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 10000, 'payment', 'частичная оплата 50%'],
                'products' => [
                    ['ART-PARTIAL', 'TESTBRAND', 'Тестовая деталь, частичная оплата', 1, 8000, 20000, $supplier->id, 'Сегодня'],
                ],
            ],
        ])->assertStatus(200);

        $this->assertSame(10000.0, $this->receivableFor($phone), 'Клиент должен остаться должен ровно 10000 (20000 - 10000 оплачено).');
    }

    public function test_kaspi_order_without_any_payment_shows_full_amount_as_kaspi_receivable(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $supplier = Suppliers::where('code', 'rmtk')->firstOrFail();
        $phone = '+77779990002';

        // Долг за Kaspi-заказы группируется общей строкой "Kaspi", а не по
        // телефону клиента — замеряем дельту, в базе может уже быть другой
        // реальный незакрытый Kaspi-заказ.
        $initial = $this->receivableFor('Kaspi');

        // Kaspi ещё не заплатил вообще ничего — paymentInfo с amount=0.
        $this->actingAs($user)->post('/manually_make_order', [
            'data' => [
                'orderInfo' => [$user->id, now()->format('Y-m-d'), 'Тест Kaspi Отложенная', $phone, 'kaspi'],
                'paymentInfo' => [1, now()->format('Y-m-d'), 0, 'payment', 'Kaspi ещё не выплатил'],
                'products' => [
                    ['ART-KASPI-PENDING', 'TESTBRAND', 'Тестовая деталь, Kaspi отложенная выплата', 1, 8000, 15000, $supplier->id, 'Сегодня'],
                ],
            ],
        ])->assertStatus(200);

        $this->assertSame(15000.0, $this->receivableFor('Kaspi') - $initial, 'Пока Kaspi не заплатил ни тенге, вся сумма заказа должна висеть в дебиторке "Kaspi".');
    }
}
