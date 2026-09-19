<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая зачистка прода 2026-09-19 — подготовка к объединению карточек
 * "Переплата поставщикам" (живой accrued-paid) и "Сальдо у поставщиков
 * (зачёт)" (supplier_credits) в одну (просьба Романа, после того как
 * автоплатёж предоплатным поставщикам убрали целиком, различие между
 * источниками перестало быть значимым для него).
 *
 * У ТРЁХ поставщиков — Автотрейд (29), Тисс (30), Кулан (40) —
 * supplier_credits исторически писалась СИНХРОННО с автоплатежом
 * (applyAvailableSupplierCredit(), удалён в этой же сессии) — их текущий
 * остаток в supplier_credits это не независимая информация, а просто
 * зеркало того, что уже и так полностью отражено в accrued/paid
 * (supplier_settlement/cashflow_transactions). Если сложить оба источника
 * при объединении карточек — эти три задвоятся. У всех остальных
 * поставщиков (напр. Армтек — зачёт по возврату,
 * CustomerReturnController) такого зеркалирования никогда не было,
 * их не трогаем.
 *
 * Зануляем ТЕКУЩИЙ остаток (не удаляя историю строк — добавляем
 * компенсирующую запись) только для этих трёх supplier_id, только если
 * остаток ненулевой. Идемпотентно — на уже обнулённом поставщике ничего
 * не делает.
 */
return new class extends Migration
{
    public function up(): void
    {
        $suppliers = [
            29 => 'Автотрейд',
            30 => 'Тисс',
            40 => 'Кулан',
        ];

        foreach ($suppliers as $supplierId => $supplierName) {
            $currentBalance = (float) DB::table('supplier_credits')
                ->where('supplier_id', $supplierId)
                ->sum('amount');

            if (abs($currentBalance) < 0.01) {
                continue;
            }

            DB::table('supplier_credits')->insert([
                'supplier_id' => $supplierId,
                'amount' => -$currentBalance,
                'source_table' => null,
                'source_id' => null,
                'comment' => 'Обнуление — задваивало "Переплата поставщикам" (живой accrued-paid уже отражает эту сумму, зачёт был зеркалом убранной автоматики)',
                'date' => now()->format('Y-m-d'),
            ]);
        }
    }

    public function down(): void
    {
        // Осознанно пусто — разовая зачистка, откатывать нечем/незачем.
    }
};
