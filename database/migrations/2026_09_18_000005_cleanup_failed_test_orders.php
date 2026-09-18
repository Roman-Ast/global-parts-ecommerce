<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая чистка прода 2026-09-18 (просьба Романа) — заказы #2035-#2041
 * появились не как тесты, а как реальные попытки оформить один и тот же
 * боевой заказ, которые падали одна за другой из-за дрейфа схемы
 * (account_id/paid_at на order_payments, id-сдвиг cashflow_categories —
 * см. три предыдущие миграции), каждая попытка успевала создать строку в
 * `orders` до падения на следующем шаге. Ни одна не дошла до
 * order_product/supplier_settlement/settlements (сбой происходил раньше
 * в manuallyMakeOrder()), но чистим и их тоже — на случай если какая-то
 * попытка всё же продвинулась дальше остальных.
 *
 * Жёстко зашитый список ID — сознательно, не диапазон/условие: это
 * разовая ручная чистка конкретных известных строк, не общая логика.
 * customers НЕ трогаем — телефон того же клиента понадобится для
 * настоящего заказа, который Роман создаст следующим шагом.
 */
return new class extends Migration
{
    private const ORDER_IDS = [2035, 2036, 2037, 2038, 2039, 2040, 2041];

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('settlements')->whereIn('order_id', self::ORDER_IDS)->delete();
        DB::table('supplier_settlement')->whereIn('order_id', self::ORDER_IDS)->delete();
        DB::table('order_product')->whereIn('order_id', self::ORDER_IDS)->delete();
        DB::table('cashflow_transactions')
            ->where('related_table', 'orders')
            ->whereIn('related_id', self::ORDER_IDS)
            ->delete();
        DB::table('order_payments')->whereIn('order_id', self::ORDER_IDS)->delete();
        DB::table('orders')->whereIn('id', self::ORDER_IDS)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Осознанно пусто — удалённые тестовые заказы восстанавливать не нужно.
    }
};
