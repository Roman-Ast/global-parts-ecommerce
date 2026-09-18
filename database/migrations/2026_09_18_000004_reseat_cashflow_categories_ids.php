<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Живой фикс дрейфа на проде 2026-09-18: `CashflowCategoriesSeeder`
 * когда-то отработал на НЕ пустой (или не сброшенной по auto_increment)
 * таблице `cashflow_categories` — 9 категорий вставились с правильными
 * code/названиями, но с id 8-16 вместо ожидаемых 1-9. Весь
 * `AdminPanelController` жёстко ссылается на категории по НОМЕРУ (не по
 * code) в нескольких местах — с таким сдвигом это бьёт мимо (напр. заказ
 * пытается записать `cashflow_category_id=1`, ожидая "Оплата по заказу",
 * а реально это несуществующий на тот момент id).
 *
 * Живой SQL-запрос от Романа подтвердил: только id=9 ('expense') имеет
 * реальные проводки (3 шт.) — остальные 8 категорий пустые, двигать
 * безопасно. Через phpMyAdmin `SET FOREIGN_KEY_CHECKS=0` не удержался
 * между операторами (видимо, особенность конкретной сборки/сессии на
 * этом хостинге) — здесь то же самое, но внутри ОДНОГО постоянного
 * соединения на весь метод up(), что гарантированно работает (та же
 * причина, почему до этого все остальные догоняющие фиксы схемы шли
 * через миграции, а не ручной SQL).
 *
 * Порядок UPDATE — по возрастанию исходного id (8,9,...,16) — на каждом
 * шаге целевой id (1..9) уже гарантированно свободен, т.к. либо никогда
 * не был занят, либо был освобождён ПРЕДЫДУЩИМ шагом этого же скрипта.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Локально (и на любом окружении, где категории уже сидят на 1-9)
        // это должно быть безоперационным — сверяем и по id, И по code,
        // чтобы не задеть уже правильно сидящую таблицу: сдвигаем ТОЛЬКО
        // если под $fromId реально лежит ожидаемая категория, а под $toId
        // её ещё нет.
        $shifts = [
            8  => ['to' => 1, 'code' => 'order_payment'],
            9  => ['to' => 2, 'code' => 'expense'],
            10 => ['to' => 3, 'code' => 'supplier_payment'],
            11 => ['to' => 4, 'code' => 'supplier_refund'],
            12 => ['to' => 5, 'code' => 'other_income'],
            13 => ['to' => 6, 'code' => 'transfer'],
            14 => ['to' => 7, 'code' => 'customer_refund'],
            15 => ['to' => 8, 'code' => 'owner_withdrawal'],
            16 => ['to' => 9, 'code' => 'opening_balance'],
        ];

        $needsShift = collect($shifts)->contains(
            fn ($s, $fromId) => DB::table('cashflow_categories')->where(['id' => $fromId, 'code' => $s['code']])->exists()
        );

        if (!$needsShift) {
            return; // окружение уже в правильном состоянии (напр. локалка)
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Сначала переносим ссылки в дочерней таблице на БУДУЩИЙ id 'expense' (2)
        DB::table('cashflow_transactions')->where('cashflow_category_id', 9)->update(['cashflow_category_id' => 2]);

        foreach ($shifts as $fromId => $s) {
            DB::table('cashflow_categories')
                ->where(['id' => $fromId, 'code' => $s['code']])
                ->update(['id' => $s['to']]);
        }

        DB::statement('ALTER TABLE cashflow_categories AUTO_INCREMENT = 10');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Осознанно пусто — разовый догоняющий фикс дрейфа, откатывать некуда.
    }
};
