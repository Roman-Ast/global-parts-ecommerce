<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-19 — Роман попросил добавить 5960
 * к текущему долгу перед Шатэ-М, итоговый долг должен стать 173037
 * (balance = accrued-paid в терминах getSuppliersSettlements(),
 * положительное значение — мы должны поставщику).
 *
 * Ищем поставщика по имени (не хардкодим id — на проде он может
 * отличаться от локального), считаем текущий баланс живьём и подгоняем
 * его корректирующей строкой в supplier_settlement (order_id=null, без
 * проводок в cashflow — тот же приём, что и в остальных подобных
 * корректировках этой сессии). Идемпотентно: при повторном запуске
 * текущий баланс уже будет 173037, разница 0, ничего не изменится.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Шатэ-М')->first();

        if (!$supplier) {
            return;
        }

        $supplierId = $supplier->id;
        $target = 173037.00;

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
        $gap = round($target - $currentBalance, 2); // на сколько поднять accrued

        if (abs($gap) < 0.01) {
            return;
        }

        DB::table('supplier_settlement')->insert([
            'order_id' => null,
            'product_id' => null,
            'supplier' => $supplier->name,
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

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
