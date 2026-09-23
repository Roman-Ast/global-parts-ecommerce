<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-23 — вторая по счёту для Автотрейда
 * (первая: 2026_09_19_000004_correct_avtotreid_balance_to_16700, тогда
 * выставили "они должны нам 16700"). Роман: "сложная история — резерв
 * баланса под заказную позицию, потом её вернули вне нашей системы" —
 * очередной внешний, не отражённый в ERP манёвр разошёл баланс снова.
 * Сейчас дашборд показывает +16400 (мы им должны), Роман сверил реальный
 * баланс напрямую и попросил выставить -300 (они должны нам 300).
 *
 * Тот же приём, что и в прошлый раз — живьём считаем текущий разрыв по
 * ТОЙ ЖЕ формуле, что и getSuppliersSettlements() (accrued - paid), и
 * добавляем одну корректирующую строку в supplier_settlement без
 * проводок в cashflow — идемпотентно, безопасно перезапускать.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplierId = 29; // Автотрейд
        $targetBalance = -300.0; // отрицательный = поставщик должен нам

        $accrued = (float) DB::table('supplier_settlement')
            ->where('supplier_id', $supplierId)
            ->where('operation', 'realization')
            ->sum(DB::raw('`sum` * -1'));

        $paid = (float) DB::table('cashflow_transactions')
            ->where('direction', 'out')
            ->where('cashflow_category_id', 3)
            ->where('supplier_id', $supplierId)
            ->sum('amount');

        $currentBalance = round($accrued - $paid, 2);
        $gap = round($targetBalance - $currentBalance, 2); // на сколько поднять accrued

        if (abs($gap) >= 1) {
            DB::table('supplier_settlement')->insert([
                'order_id' => null,
                'product_id' => null,
                'supplier' => 'Автотрейд',
                'supplier_id' => $supplierId,
                'sum' => -$gap, // после *-1 в формуле accrued поднимется ровно на $gap
                'date' => now()->format('Y-m-d'),
                'operation' => 'realization',
                'payment_due_date' => null,
                'payment_status' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
