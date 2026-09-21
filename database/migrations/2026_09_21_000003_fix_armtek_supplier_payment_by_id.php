<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Уточнение миграции 2026_09_21_000002 (та искала запись по совпадению
 * даты/суммы/счёта — Роман дал точный id прямо со прода: 84) — та же
 * "Оплата поставщику" 3590₸ 21 сентября 2026 без поставщика, из-за
 * которой у Армтека висел лишний долг. Целимся точно в id=84, чтобы не
 * зависеть от эвристики. Идемпотентно: если supplier_id уже проставлен
 * (например, той миграцией или вручную) — ничего не делает.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Армтек')->first();

        if (!$supplier) {
            return;
        }

        DB::table('cashflow_transactions')
            ->where('id', 84)
            ->whereNull('supplier_id')
            ->update(['supplier_id' => $supplier->id]);
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
