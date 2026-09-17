<?php

namespace Tests\Feature\Erp;

use App\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CheckoutDeliveryTypeTest extends TestCase
{
    use DatabaseTransactions;

    private function seedCart(): void
    {
        $cart = new Cart();
        $cart->add('ART123', 'NGK', 'Свеча зажигания', 'ART123', '1-2 дня', '1000', 1, 'ast', 1500);
        session()->put('cart', $cart);
    }

    public function test_pickup_order_gets_fixed_pickup_address(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $this->actingAs($user);
        $this->seedCart();

        $response = $this->post('/makeorder', [
            'customer_phone' => '+7 (777) 123-45-67',
            'name' => 'Тест Клиент',
            'delivery_type' => 'pickup',
            'vin' => '',
            'comment' => '',
        ]);

        $response->assertRedirect('orders');

        $order = Order::latest('id')->first();
        $this->assertSame('pickup', $order->delivery_type);
        $this->assertStringContainsString('Целинный', $order->address);
    }

    public function test_delivery_order_saves_customer_provided_address(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $this->actingAs($user);
        $this->seedCart();

        $response = $this->post('/makeorder', [
            'customer_phone' => '+7 (777) 123-45-67',
            'name' => 'Тест Клиент',
            'delivery_type' => 'delivery',
            'city' => 'Астана',
            'address' => 'ул. Тестовая 1',
            'vin' => '',
            'comment' => '',
        ]);

        $response->assertRedirect('orders');

        $order = Order::latest('id')->first();
        $this->assertSame('delivery', $order->delivery_type);
        $this->assertSame('ул. Тестовая 1', $order->address);
        $this->assertSame('Астана', $order->city);
    }

    public function test_delivery_order_without_address_fails_validation(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $this->actingAs($user);
        $this->seedCart();

        $response = $this->post('/makeorder', [
            'customer_phone' => '+7 (777) 123-45-67',
            'name' => 'Тест Клиент',
            'delivery_type' => 'delivery',
        ]);

        $response->assertSessionHasErrors(['city', 'address']);
    }
}
