<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-23 — Роман: были возвраты старых
 * товаров по заказам ДО ЕРП, Росско зачёл их на баланс и тем самым
 * уменьшил реальный долг. В ERP эти возвраты никогда не заводились (сами
 * заказы были до системы), поэтому кредиторка Росско завышена. Роман
 * сверил реальный долг напрямую и попросил выставить 390261 (дашборд
 * показывал -402575, т.е. разница ровно 12314 — сумма тех старых
 * возвратов).
 *
 * Тот же приём, что и в остальных подобных корректировках этой сессии
 * (Автотрейд, Шатэ-М) — ищем поставщика по имени (не хардкодим id),
 * считаем текущий баланс живьём по формуле getSuppliersSettlements()
 * (accrued - paid) и добавляем ОДНУ корректирующую строку в
 * supplier_settlement на разницу, без проводок в cashflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Росско')->first();

        if (!$supplier) {
            return;
        }

        $supplierId = $supplier->id;
        $targetBalance = 390261.0; // положительный = мы должны поставщику

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
