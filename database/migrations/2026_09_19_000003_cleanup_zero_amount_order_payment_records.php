<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая чистка прода 2026-09-19 — Роман заметил в кэшфлоу строку
 * "Оплата по заказу №2052" на 0.00₸ и спросил, почему такое вообще
 * попадает в БД. Причина — `AdminPanelController::manuallyMakeOrder()`
 * безусловно создавал `order_payments`/`cashflow_transactions` на сумму
 * из формы, а JS-форма (`admin.js`) намеренно подставляет 0 для
 * Kaspi-заказов с отложенной оплатой (реальные деньги приходят позже,
 * автоплатежом при выдаче — см. `changeStatus()`). Код исправлен в том же
 * коммите (обе записи теперь создаются только при amount > 0) — это
 * только зачистка уже накопившегося мусора.
 *
 * Сами строки на 0₸ ни на что не влияют арифметически (0 в любой SUM —
 * без разницы, добавлена строка или нет), это чисто засорение ленты
 * кэшфлоу для глаз — безопасно удалить, не пересчитывая ничего другого.
 * Guard по amount=0 достаточен: платёж/возврат ровно на 0 тенге не имеет
 * экономического смысла ни при каких обстоятельствах, не только в рамках
 * этого бага.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_payments')->where('amount', 0)->delete();

        DB::table('cashflow_transactions')
            ->where('amount', 0)
            ->where('cashflow_category_id', 1)
            ->where('related_table', 'orders')
            ->whereIn('subcategory', ['Оплата по заказу', 'Возврат по заказу'])
            ->delete();
    }

    public function down(): void
    {
        // Осознанно пусто — разовая чистка мусора, откатывать нечем/незачем.
    }
};
