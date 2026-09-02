<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\SparePartControllerTest as SparePartController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Проверяет весь путь токена реального поставщика: генерация +
 * кэширование при рендере результатов поиска (tokenizeSupplierNames) →
 * долетание через hidden input/JS до /cart/add → разворачивание обратно
 * в admin_supplier_name на строке заказа. Написано 2026-09-02 вместе с
 * самим фиксом (см. CLAUDE.md/переписку — заменили открытую передачу
 * реального имени поставщика на непрозрачный токен, чтобы не палить
 * поставщиков конкурентам через "Просмотр кода страницы").
 */
class SupplierTokenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tokenize_supplier_names_generates_a_resolvable_token(): void
    {
        $controller = app(SparePartController::class);
        $method = new \ReflectionMethod($controller, 'tokenizeSupplierNames');
        $method->setAccessible(true);

        $items = [
            ['article' => 'PDS1961', 'brand' => 'PATRON', 'supplier_name' => 'rssk', 'supplier_city' => 'Астана'],
            ['article' => 'CR0297', 'brand' => 'CTR', 'supplier_city' => 'Москва'], // без supplier_name — не должно всё равно ломаться
        ];

        $result = $method->invoke($controller, $items);

        $this->assertArrayHasKey('supplier_token', $result[0]);
        $this->assertNotEmpty($result[0]['supplier_token']);
        $this->assertSame('rssk', Cache::get('supplier_token:' . $result[0]['supplier_token']));

        // Второй элемент без supplier_name — токен просто не добавляется, не падает.
        $this->assertArrayNotHasKey('supplier_token', $result[1]);
    }

    public function test_cart_add_resolves_real_supplier_from_token(): void
    {
        $user = User::create([
            'name' => 'Тест Клиент',
            'email' => 'token.test.' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '+77011234567',
            'user_role' => 'common',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $token = 'testtoken123456';
        Cache::put("supplier_token:{$token}", 'rssk', now()->addHours(2));

        $response = $this->actingAs($user)->post('/cart/add', [
            'data' => [
                'article' => 'PDS1961',
                'brand' => 'PATRON',
                'name' => 'Полуось',
                'searchedNumber' => 'PDS1961',
                'deliveryTime' => '1-2 дня',
                'price' => 30000,
                'qty' => 1,
                'stockFrom' => 'Астана', // то, что видит клиент
                'supplierToken' => $token,
                'priceWithMargine' => 45000,
            ],
        ]);

        $response->assertStatus(200);

        $cart = session('cart');
        $this->assertSame('rssk', $cart->items[0]['adminSupplierName']);
        $this->assertSame('Астана', $cart->items[0]['stockFrom']);
    }

    public function test_cart_add_without_token_falls_back_to_price_lookup(): void
    {
        $user = User::create([
            'name' => 'Тест Клиент',
            'email' => 'notoken.test.' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'phone' => '+77011234567',
            'user_role' => 'common',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->post('/cart/add', [
            'data' => [
                'article' => 'NONEXISTENT999',
                'brand' => 'NOBRAND',
                'name' => 'Что-то',
                'searchedNumber' => 'NONEXISTENT999',
                'deliveryTime' => '1-2 дня',
                'price' => 1000,
                'qty' => 1,
                'stockFrom' => 'Алматы',
                'supplierToken' => '',
                'priceWithMargine' => 1500,
            ],
        ]);

        $response->assertStatus(200);

        $cart = session('cart');
        // supplier_offers не знает такой артикул — best-effort вернёт null,
        // без токена и без падения запроса.
        $this->assertNull($cart->items[0]['adminSupplierName']);
    }
}
