<?php

namespace Tests\Feature\Erp;

use App\Models\Accounts;
use App\Models\CashflowTransactions;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Личное изъятие и фиксированные расходы (см. "сценарии работы Global
 * Parts.txt"). Личное изъятие — новая cashflow_categories id=8, намеренно
 * исключена из KPI "Расход" на дашборде (см. getFinanceDashboardData()).
 */
class CashflowScenariosTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_withdrawal_reduces_account_balance_but_not_expense_kpi(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $account = Accounts::where('is_active', 1)->firstOrFail();

        $before = $this->actingAs($user)->get('/admin_panel');
        $expenseBefore = (float) $before->viewData('financeKpi')['expense'];

        CashflowTransactions::create([
            'txn_at' => now(),
            'direction' => 'out',
            'cashflow_category_id' => 8, // Личное изъятие
            'expense_category_id' => null,
            'supplier_id' => null,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 50000,
            'subcategory' => 'Личное изъятие',
            'counterparty' => $user->name,
            'related_table' => null,
            'related_id' => null,
            'comment' => 'Тест: личное изъятие',
        ]);

        $after = $this->actingAs($user)->get('/admin_panel');

        $this->assertSame(
            $expenseBefore,
            (float) $after->viewData('financeKpi')['expense'],
            'Личное изъятие не должно менять KPI "Расход".'
        );

        $accountRow = collect($after->viewData('financeAccountsSummary'))->firstWhere('name', $account->name);
        $this->assertNotNull($accountRow, 'Счёт должен появиться в сводке по счетам.');
    }

    public function test_fixed_expense_counts_toward_expense_kpi_and_breakdown(): void
    {
        $user = User::where('user_role', 'admin')->firstOrFail();
        $account = Accounts::where('is_active', 1)->firstOrFail();

        $before = $this->actingAs($user)->get('/admin_panel');
        $expenseBefore = (float) $before->viewData('financeKpi')['expense'];

        CashflowTransactions::create([
            'txn_at' => now(),
            'direction' => 'out',
            'cashflow_category_id' => 2, // Расход
            'expense_category_id' => 1, // Аренда
            'supplier_id' => null,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => 120000,
            'subcategory' => 'Аренда офиса',
            'counterparty' => 'Арендодатель',
            'related_table' => null,
            'related_id' => null,
            'comment' => 'Тест: фиксированный расход, аренда',
        ]);

        $after = $this->actingAs($user)->get('/admin_panel');

        $this->assertSame(
            $expenseBefore + 120000,
            (float) $after->viewData('financeKpi')['expense'],
            'Обычный расход (аренда) должен полностью попадать в KPI.'
        );

        $rentRow = collect($after->viewData('financeExpenseBreakdown'))->firstWhere('name', 'Аренда');
        $this->assertNotNull($rentRow);
        $this->assertGreaterThanOrEqual(120000, $rentRow['amount']);
    }
}
