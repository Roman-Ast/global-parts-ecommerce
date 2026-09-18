<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-18 (просьба Романа): Фаэтону деньги
 * реально оплачены напрямую, но провести это сейчас нормальной формой
 * "Оплата поставщику" нельзя (нечем сверить/учесть) — обнуляем именно
 * кредиторку, БЕЗ проводки в cashflow_transactions (без реального списания
 * со счёта, раз оно уже произошло вне системы).
 *
 * Баланс поставщика считается как accrued - paid (см.
 * AdminPanelController::getSuppliersSettlements()):
 *   accrued = SUM(supplier_settlement.sum * -1) WHERE operation='realization'
 *   paid    = SUM(cashflow_transactions.amount) WHERE direction='out' AND
 *             cashflow_category_id=3 (оплата поставщику)
 * "Без проводок" в этой формуле означает трогать НЕ paid (не создавать
 * cashflow_transactions), а accrued — добавить корректирующую строку в
 * supplier_settlement с ПОЛОЖИТЕЛЬНЫМ sum (после *-1 в формуле она уйдёт
 * в минус и снизит accrued ровно на сумму долга).
 *
 * Живые цифры от Романа 2026-09-18 (supplier_id=32, Фаэтон):
 *   accrued=95877, paid=18171 → баланс (долг) = 77706
 */
return new class extends Migration
{
    private const SUPPLIER_ID = 32;
    private const SUPPLIER_NAME = 'Фаэтон';

    public function up(): void
    {
        $accrued = (float) DB::table('supplier_settlement')
            ->where('supplier_id', self::SUPPLIER_ID)
            ->where('operation', 'realization')
            ->sum(DB::raw('`sum` * -1'));

        $paid = (float) DB::table('cashflow_transactions')
            ->where('direction', 'out')
            ->where('cashflow_category_id', 3)
            ->where('supplier_id', self::SUPPLIER_ID)
            ->sum('amount');

        $currentBalance = round($accrued - $paid, 2);

        // Идемпотентность — если баланс уже близок к нулю (эта миграция уже
        // применена, или ситуация изменилась сама), ничего не делаем.
        if (abs($currentBalance) < 1) {
            return;
        }

        DB::table('supplier_settlement')->insert([
            'order_id' => null,
            'product_id' => null,
            'supplier' => self::SUPPLIER_NAME,
            'supplier_id' => self::SUPPLIER_ID,
            'sum' => $currentBalance, // положительный — после *-1 в формуле баланса снизит долг до 0
            'date' => now()->format('Y-m-d'),
            'operation' => 'realization',
            'payment_due_date' => null,
            'payment_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
