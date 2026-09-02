<?php

namespace Tests\Feature\Erp;

use App\Cart;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Полный путь обычного (не админ) зарегистрированного клиента —
 * добавление в корзину ОБОИМИ способами (/cart/add с главного поиска,
 * /cart/add-api с карточки каталога), просмотр корзины, оформление
 * заказа с самовывозом и с доставкой. Написано 2026-09-02 после того,
 * как реальные клиенты словили 500 на чекауте — миграция
 * delivery_type/city/address на проде не была прогнана вовремя после
 * деплоя кода. Локально это не всплыло раньше, потому что все
 * предыдущие тесты чекаута гоняли из-под admin-пользователя, а не
 * обычного клиента.
 */
class CustomerCheckoutFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function regularCustomer(): User
    {
        $user = User::create([
            'name' => 'Тест Клиент',
            'email' => 'test.customer.' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '+77011234567',
            'user_role' => 'common',
        ]);

        // email_verified_at не в $fillable (правильно — не должно быть
        // массово назначаемым из обычного запроса), поэтому напрямую.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_adding_via_cart_add_then_viewing_cart_shows_the_item(): void
    {
        $user = $this->regularCustomer();

        $addResponse = $this->actingAs($user)->post('/cart/add', [
            'data' => [
                'article' => 'PDS1961',
                'brand' => 'PATRON',
                'name' => 'Полуось',
                'searchedNumber' => 'PDS1961',
                'deliveryTime' => '1-2 дня',
                'price' => 30000,
                'qty' => 1,
                'stockFrom' => 'ast',
                'priceWithMargine' => 45000,
            ],
        ]);

        $addResponse->assertStatus(200);

        $cartResponse = $this->actingAs($user)->get('/cart');
        $cartResponse->assertStatus(200);
        $cartResponse->assertSee('PDS1961');
        $cartResponse->assertSee('PATRON');
        $cartResponse->assertSee('45 000', false);
    }

    public function test_adding_via_cart_add_api_then_viewing_cart_shows_the_item(): void
    {
        $user = $this->regularCustomer();

        $addResponse = $this->actingAs($user)->post('/cart/add-api', [
            'article' => 'CR0297',
            'brand' => 'CTR',
            'name' => 'Рулевая тяга',
            'delivery' => '1-2 дня',
            'price' => 10000,
            'quantity' => 1,
            'supplier' => 'москва',
            'retail_price' => 15000,
        ]);

        $addResponse->assertStatus(200);
        $addResponse->assertJson(['success' => true, 'cart_count' => 1]);

        $cartResponse = $this->actingAs($user)->get('/cart');
        $cartResponse->assertStatus(200);
        $cartResponse->assertSee('CR0297');
        $cartResponse->assertSee('15 000', false);
    }

    public function test_checkout_page_shows_cart_contents(): void
    {
        $user = $this->regularCustomer();
        $this->actingAs($user)->post('/cart/add', ['data' => $this->sampleCartItemData()]);

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertStatus(200);
        $response->assertSee('PDS1961');
    }

    public function test_pickup_checkout_creates_order_with_fixed_address_and_emails_customer(): void
    {
        Mail::fake();

        $user = $this->regularCustomer();
        $this->actingAs($user)->post('/cart/add', ['data' => $this->sampleCartItemData()]);

        $response = $this->actingAs($user)->post('/makeorder', [
            'customer_phone' => '+7 (701) 123-45-67',
            'name' => $user->name,
            'delivery_type' => 'pickup',
            'vin' => '',
            'comment' => '',
        ]);

        $response->assertRedirect('orders');
        $response->assertSessionHasNoErrors();

        $order = Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order, 'Заказ не создался — та же ошибка, что поймали в production.log');
        $this->assertSame('pickup', $order->delivery_type);
        $this->assertStringContainsString('Целинный', $order->address);
        $this->assertSame(45000.0, (float) $order->sum_with_margine);

        Mail::assertSent(OrderPlaced::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }

    public function test_delivery_checkout_creates_order_with_customer_address(): void
    {
        Mail::fake();

        $user = $this->regularCustomer();
        $this->actingAs($user)->post('/cart/add', ['data' => $this->sampleCartItemData()]);

        $response = $this->actingAs($user)->post('/makeorder', [
            'customer_phone' => '+7 (701) 123-45-67',
            'name' => $user->name,
            'delivery_type' => 'delivery',
            'city' => 'Астана',
            'address' => 'ул. Абая 10',
            'vin' => 'JN1TBNT32U0000120',
            'comment' => 'Позвонить за час',
        ]);

        $response->assertRedirect('orders');

        $order = Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('delivery', $order->delivery_type);
        $this->assertSame('Астана', $order->city);
        $this->assertSame('ул. Абая 10', $order->address);
        $this->assertSame('JN1TBNT32U0000120', $order->vin);
    }

    public function test_delivery_checkout_without_address_fails_validation_not_500(): void
    {
        $user = $this->regularCustomer();
        $this->actingAs($user)->post('/cart/add', ['data' => $this->sampleCartItemData()]);

        $response = $this->actingAs($user)->post('/makeorder', [
            'customer_phone' => '+7 (701) 123-45-67',
            'name' => $user->name,
            'delivery_type' => 'delivery',
        ]);

        $response->assertSessionHasErrors(['city', 'address']);
        $this->assertNull(Order::where('user_id', $user->id)->first());
    }

    public function test_order_email_shows_margin_price_not_cost(): void
    {
        Mail::fake();

        $user = $this->regularCustomer();
        $this->actingAs($user)->post('/cart/add', ['data' => $this->sampleCartItemData()]);

        $this->actingAs($user)->post('/makeorder', [
            'customer_phone' => '+7 (701) 123-45-67',
            'name' => $user->name,
            'delivery_type' => 'pickup',
        ]);

        $order = Order::where('user_id', $user->id)->latest('id')->first();

        Mail::assertSent(OrderPlaced::class, function ($mail) use ($order) {
            $html = $mail->render();
            return str_contains($html, '45 000') && !str_contains($html, '30 000');
        });
    }

    private function sampleCartItemData(): array
    {
        return [
            'article' => 'PDS1961',
            'brand' => 'PATRON',
            'name' => 'Полуось',
            'searchedNumber' => 'PDS1961',
            'deliveryTime' => '1-2 дня',
            'price' => 30000,
            'qty' => 1,
            'stockFrom' => 'ast',
            'priceWithMargine' => 45000,
        ];
    }
}
