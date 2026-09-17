<?php

namespace Tests\Feature\Erp;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NetFinancialPositionCardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_shows_liquidity_and_net_position_cards(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin_panel');

        $response->assertStatus(200);
        $response->assertSee('Ликвидность сегодня');
        $response->assertSee('Чистая финансовая позиция');
        $response->assertSee('Главный показатель');
    }
}
