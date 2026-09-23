<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая чистка прода 2026-09-23 (просьба Романа) — заказ #2060 оказался
 * дублем: Роман по невнимательности оформил один и тот же заказ дважды
 * (Kaspi, LYNXauto G12822LR, амортизатор, Росско) и на ОДНОМ из дублей
 * нажал "выдано", на #2060 — нет (статус на момент удаления —
 * "ожидание оплаты"). Раз #2060 никогда не переходил в "выдано" —
 * AUTO_PAYOUT_MARKETPLACES (AdminPanelController::changeStatus()) по
 * нему не срабатывал, ни одного авто-платежа в cashflow_transactions с
 * этим заказом не создавалось — удаление финансово безопасно, кредиторка
 * Росско просто перестанет включать задвоенную позицию.
 *
 * Тот же набор таблиц и тот же приём (жёстко зашитый ID, не диапазон),
 * что и в 2026_09_18_000005_cleanup_failed_test_orders.php — второй
 * (реальный, оставленный) дубль НЕ трогаем.
 */
return new class extends Migration
{
    private const ORDER_ID = 2060;

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('settlements')->where('order_id', self::ORDER_ID)->delete();
        DB::table('supplier_settlement')->where('order_id', self::ORDER_ID)->delete();
        DB::table('order_product')->where('order_id', self::ORDER_ID)->delete();
        DB::table('cashflow_transactions')
            ->where('related_table', 'orders')
            ->where('related_id', self::ORDER_ID)
            ->delete();
        DB::table('order_payments')->where('order_id', self::ORDER_ID)->delete();
        DB::table('customer_returns')->where('order_id', self::ORDER_ID)->delete();
        DB::table('orders')->where('id', self::ORDER_ID)->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Осознанно пусто — удалённый задвоенный заказ восстанавливать не нужно.
    }
};
