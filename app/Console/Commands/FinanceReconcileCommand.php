<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminPanelController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Быстрая сверка прибыли по начислению против фактического движения
 * кассы за период — та же логика, что и страница "Финансовая сверка" в
 * админке (AdminPanelController::getReconciliationData()), но из
 * консоли, без захода в браузер. См. комментарий у самого метода за
 * подробным разбором математики моста.
 */
class FinanceReconcileCommand extends Command
{
    protected $signature = 'finance:reconcile {--date_from=} {--date_to=}';

    protected $description = 'Сверка прибыли по начислению и фактической кассы за период';

    public function handle(): int
    {
        $request = new Request();
        if ($this->option('date_from')) {
            $request->merge(['recon_date_from' => $this->option('date_from')]);
        }
        if ($this->option('date_to')) {
            $request->merge(['recon_date_to' => $this->option('date_to')]);
        }

        $data = app(AdminPanelController::class)->getReconciliationData($request);
        $r = $data['reconciliation'];

        $this->info("Период: {$data['reconDateFrom']} — {$data['reconDateTo']}");
        $this->newLine();

        $this->line('Прибыль по начислению:');
        $this->table(['', 'Сумма'], [
            ['Выручка', number_format($r['revenue'], 2, '.', ' ')],
            ['Себестоимость', number_format($r['cogs'], 2, '.', ' ')],
            ['Валовая маржа', number_format($r['gross_margin'], 2, '.', ' ')],
            ['Расходы (опекс)', number_format($r['opex'], 2, '.', ' ')],
            ['Потери на возвратах', number_format($r['returns_loss'], 2, '.', ' ')],
            ['ЧИСТАЯ ПРИБЫЛЬ (начисление)', number_format($r['net_accrual_profit'], 2, '.', ' ')],
        ]);

        $this->line('Факт по кассе:');
        $this->table(['', 'Сумма'], [
            ['Остаток на начало периода', number_format($r['cash_balance_before'], 2, '.', ' ')],
            ['Остаток на конец периода', number_format($r['cash_balance_after'], 2, '.', ' ')],
            ['Фактическое изменение кассы', number_format($r['actual_cash_delta'], 2, '.', ' ')],
        ]);

        $this->line('Мост (сверка):');
        $this->table(['', 'Сумма'], [
            ['Чистая прибыль (начисление)', number_format($r['net_accrual_profit'], 2, '.', ' ')],
            ['± Изменение кредиторки', number_format($r['payable_delta'], 2, '.', ' ')],
            ['− Изменение дебиторки', number_format(-$r['receivable_delta'], 2, '.', ' ')],
            ['− Личные изъятия', number_format(-$r['owner_withdrawals'], 2, '.', ' ')],
            ['+ Внесение начальных остатков', number_format($r['opening_balance_injections'], 2, '.', ' ')],
            ['= Ожидаемое изменение кассы', number_format($r['expected_cash_delta'], 2, '.', ' ')],
        ]);

        $unexplained = $r['unexplained'];
        $label = abs($unexplained) < 1 ? 'РАСХОЖДЕНИЕ (норма, ~0)' : 'РАСХОЖДЕНИЕ (не объяснено моделью)';
        $this->newLine();
        $this->line("{$label}: " . number_format($unexplained, 2, '.', ' ') . ' ₸');

        return self::SUCCESS;
    }
}
