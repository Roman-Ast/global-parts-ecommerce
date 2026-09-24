<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-24 — Автозакуп (supplier_id=34),
 * снова из-за старых долгов (та же категория, что и предыдущие
 * корректировки Автотрейда/Шатэ-М/Росско в эту же сессию — исторический
 * хвост до-ЕРП, не отражённый в supplier_settlement). Роман сверил
 * реальный долг напрямую и попросил выставить 107011 (мы должны
 * поставщику).
 *
 * Тот же приём: ищем поставщика по имени (не хардкодим id), считаем
 * текущий баланс живьём по формуле getSuppliersSettlements()
 * (accrued - paid) и добавляем ОДНУ корректирующую строку в
 * supplier_settlement на разницу, без проводок в cashflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Автозакуп')->first();

        if (!$supplier) {
            return;
        }

        $supplierId = $supplier->id;
        $targetBalance = 107011.0; // положительный = мы должны поставщику

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
