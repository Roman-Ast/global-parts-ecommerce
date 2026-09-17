<?php

namespace Tests\Feature\Erp;

use App\Http\Controllers\AdminPanelController;
use App\Models\Accounts;
use App\Models\SupplierSettlement;
use App\Models\Suppliers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupplierRefundReceivedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_receiving_a_refund_nets_the_earlier_opening_balance_overpayment(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $account = Accounts::where('is_active', 1)->firstOrFail();
        $supplier = Suppliers::firstOrFail();

        // Симулируем то же самое, что делает форма "Начальные остатки"
        // для положительной кредиторки поставщика (он нам должен).
        SupplierSettlement::create([
            'supplier' => $supplier->name,
            'supplier_id' => $supplier->id,
            'sum' => 22855,
            'date' => now()->format('Y-m-d'),
            'operation' => 'realization',
        ]);

        $findBalance = function (array $data) use ($supplier) {
            foreach ($data['supplierBalances'] as $row) {
                if ($row->supplier_id === $supplier->id) {
                    return (float) $row->balance;
                }
            }
            return null;
        };

        $balanceBefore = $findBalance(app(AdminPanelController::class)->getSuppliersSettlements(request()));

        $response = $this->actingAs($user)->post(route('supplier.receive-refund'), [
            'date' => now()->format('Y-m-d'),
            'sum' => 22855,
            'supplier_id' => $supplier->id,
            'account_id' => $account->id,
        ]);

        $response->assertRedirect();

        $balanceAfter = $findBalance(app(AdminPanelController::class)->getSuppliersSettlements(request()));

        // balance = accrued - paid должен вырасти ровно на сумму
        // полученного возврата (двигая supplier из "Переплата
        // поставщикам" обратно к нулю).
        $this->assertSame(22855.0, $balanceAfter - $balanceBefore);
    }
}
