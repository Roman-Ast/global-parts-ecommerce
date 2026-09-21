<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-21 — Роман завёл ДДС "Оплата
 * поставщику" (3590₸, счёт "Рома Kaspi Gold", 21 сентября 2026), но не
 * выбрал поставщика (см. фикс формы в этом же дне — до него поле не было
 * ни required на клиенте, ни валидировалось на сервере). Расход списался
 * корректно, но т.к. supplier_id=NULL, платёж не вычитается из "paid" в
 * getSuppliersSettlements() именно для Армтека — из-за этого у Армтека
 * висит лишний долг на те же 3590₸, хотя по кассе всё уже прошло верно.
 *
 * Ищем поставщика/счёт по имени (не хардкодим id — на проде могут
 * отличаться от локальных). Обновляем ТОЛЬКО если находим ровно одну
 * подходящую строку — если критерии совпадут с чем-то ещё или строка уже
 * не NULL (повторный запуск), ничего не трогаем.
 */
return new class extends Migration
{
    public function up(): void
    {
        $supplier = DB::table('suppliers')->where('name', 'Армтек')->first();
        $account = DB::table('accounts')->where('name', 'Рома Kaspi Gold')->first();

        if (!$supplier || !$account) {
            return;
        }

        $matches = DB::table('cashflow_transactions')
            ->where('cashflow_category_id', 3) // Оплата поставщику
            ->where('direction', 'out')
            ->where('account_id', $account->id)
            ->whereNull('supplier_id')
            ->where('amount', 3590.00)
            ->whereDate('txn_at', '2026-09-21')
            ->get();

        if ($matches->count() !== 1) {
            return;
        }

        DB::table('cashflow_transactions')
            ->where('id', $matches->first()->id)
            ->update(['supplier_id' => $supplier->id]);
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
