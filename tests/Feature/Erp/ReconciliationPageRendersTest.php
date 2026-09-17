<?php

namespace Tests\Feature\Erp;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReconciliationPageRendersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_panel_renders_with_reconciliation_section(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin_panel');

        $response->assertStatus(200);
        $response->assertSee('finance_reconciliation', false);
        $response->assertSee('Финансовая сверка');
    }

    public function test_run_button_executes_command_and_flashes_output(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();

        $response = $this->actingAs($user)->post(route('finance-reconcile.run'), [
            'recon_date_from' => now()->startOfMonth()->format('Y-m-d'),
            'recon_date_to' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('reconcileOutput');
        $this->assertStringContainsString('РАСХОЖДЕНИЕ', session('reconcileOutput'));

        $follow = $this->actingAs($user)->get($response->headers->get('Location'));
        $follow->assertSee('php artisan finance:reconcile', false);
    }
}
