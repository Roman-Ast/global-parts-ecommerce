<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-19 — Роман по ошибке проставил
 * заказу №2046 (пружина задней подвески C4H44912H, поставщик Тисс,
 * ожидаемая поставка 2026-09-21) статус "выдано", хотя товар физически
 * ещё не поступил (поставка ожидается только через пару дней). Строка
 * `order_product` возвращается в "ожидание оплаты"
 * (`payment_waiting`, выбор Романа — по прямому подтверждению).
 *
 * `changeStatus()` (AdminPanelController) при переходе в `issued` для
 * kaspi-заказов автоматически создаёт `order_payments`+
 * `cashflow_transactions` ("Автоматическое поступление от Kaspi по факту
 * выдачи заказа" / "...по заказу №2046") — реального поступления не
 * было, обе строки убираем. Реверса такого рода в самом коде нет (см.
 * находку при разборе changeStatus() — переход СО статуса issued не
 * восстанавливает ничего автоматически), поэтому здесь ручная
 * корректировка, тем же приёмом, что и другие разовые фиксы в этом
 * файле (guard по точным значениям — order_id/amount/comment — чтобы
 * миграция была безопасно перезапускаемой и не трогала ничего похожего
 * на других окружениях).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_product')
            ->where('order_id', 2046)
            ->where('status', 'issued')
            ->update(['status' => 'payment_waiting']);

        DB::table('order_payments')
            ->where('order_id', 2046)
            ->where('amount', 54894.00)
            ->where('comment', 'Автоматическое поступление от Kaspi по факту выдачи заказа')
            ->delete();

        DB::table('cashflow_transactions')
            ->where('related_table', 'orders')
            ->where('related_id', 2046)
            ->where('amount', 54894.00)
            ->where('subcategory', 'Оплата по заказу (Kaspi, авто)')
            ->delete();
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
