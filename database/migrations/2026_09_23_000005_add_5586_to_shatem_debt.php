<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-23 — вторая для Шатэ-М (первая:
 * 2026_09_19_000007_add_5960_to_shatem_debt.php). Роман: добавить 5586 к
 * ТЕКУЩЕМУ долгу перед Шатэ-М — это наши долги перед клиентами, возникшие
 * ДО ЕРП (исторический хвост, не отражённый в supplier_settlement).
 *
 * В отличие от прошлой корректировки (там был известен итоговый target
 * 173037) — здесь известна только сама добавка, без фиксированного
 * итога, поэтому просто вставляем ОДНУ строку на +5586 (balance —
 * accrued-paid в терминах getSuppliersSettlements(), положительное
 * значение — мы должны поставщику). Ищем поставщика по имени (не
 * хардкодим id — на проде он может отличаться от локального). Повторный
 * запуск этой миграции самим Laravel исключён (одна запись в таблице
 * `migrations`), поэтому целиться в конкретный target не требуется.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Шатэ-М')->first();

        if (!$supplier) {
            return;
        }

        DB::table('supplier_settlement')->insert([
            'order_id' => null,
            'product_id' => null,
            'supplier' => $supplier->name,
            'supplier_id' => $supplier->id,
            'sum' => -5586.00, // после *-1 в формуле accrued поднимется на 5586
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
