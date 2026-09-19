<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-19 — Роман сверил реальный баланс
 * Автотрейда напрямую (не через нашу БД) и попросил выставить его должны
 * НАМ 16700 (balance = accrued-paid = -16700 в терминах
 * getSuppliersSettlements()). Тот же приём, что и в более ранних
 * корректировках этой сессии (Фаэтон, исторический дрейф Автотрейда на
 * 2900) — считаем текущий разрыв живьём и добавляем ОДНУ корректирующую
 * строку в supplier_settlement (order_id=null, не привязана к заказу),
 * без проводок в cashflow — идемпотентно, безопасно перезапускать.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplierId = 29; // Автотрейд
        $targetBalance = -16700.0; // отрицательный = поставщик должен нам

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
