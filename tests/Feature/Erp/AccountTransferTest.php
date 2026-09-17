<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Accounts;
use App\Models\CashflowTransactions;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountTransferTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transfer_moves_balance_between_accounts_without_changing_total(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $accounts = Accounts::where('is_active', 1)->limit(2)->get();
        $from = $accounts[0];
        $to = $accounts[1];

        $balance = fn () => (float) CashflowTransactions::selectRaw(
            "COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END), 0) as b"
        )->value('b');

        $accountBalance = fn ($id) => (float) CashflowTransactions::where('account_id', $id)->selectRaw(
            "COALESCE(SUM(CASE WHEN direction='in' THEN amount ELSE -amount END), 0) as b"
        )->value('b');

        $totalBefore = $balance();
        $fromBefore = $accountBalance($from->id);
        $toBefore = $accountBalance($to->id);

        $response = $this->actingAs($user)->post(route('transfer-between-accounts'), [
            'txn_at' => now()->format('Y-m-d'),
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 15000,
        ]);

        $response->assertRedirect();

        $this->assertSame($totalBefore, $balance(), 'Общий остаток по всем счетам не должен измениться');
        $this->assertSame(-15000.0, $accountBalance($from->id) - $fromBefore);
        $this->assertSame(15000.0, $accountBalance($to->id) - $toBefore);
    }

    public function test_transfer_to_same_account_is_rejected(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $account = Accounts::where('is_active', 1)->firstOrFail();

        $response = $this->actingAs($user)->post(route('transfer-between-accounts'), [
            'txn_at' => now()->format('Y-m-d'),
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 1000,
        ]);

        $response->assertSessionHasErrors(['to_account_id']);
    }
}
