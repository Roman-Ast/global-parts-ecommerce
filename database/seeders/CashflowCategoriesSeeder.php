<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ПЕРЕПИСАН 2026-09-17 — старая версия была одновременно неполной (не было
 * 'customer_refund'/'opening_balance') и с другими кодами/названиями
 * ('sale' вместо 'order_payment', 'owner_withdraw' вместо
 * 'owner_withdrawal', без rus_name вовсе). AdminPanelController во
 * ВСЕХ местах ссылается на категорию по НОМЕРУ (жёстко: 1/3/4/6/7/9 —
 * оплата по заказу/поставщику, возврат от поставщика, перевод между
 * счетами, возврат клиенту, входящий остаток), не по code — то есть
 * порядок вставки здесь ДОЛЖЕН точно совпадать с id 1..9 в реальной базе,
 * иначе автоматика молча запишет операцию не в ту категорию (или упадёт
 * по foreign key, как и случилось на проде при первой попытке ввести
 * начальные остатки — id=9 там просто не существовал).
 *
 * Список — 1:1 экспорт из локальной БД на 2026-09-17, порядок = id.
 */
class CashflowCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'order_payment',    'name' => 'Оплата по заказу',          'rus_name' => 'Оплата по заказу',          'default_direction' => 'in'],
            ['code' => 'expense',          'name' => 'Расход',                    'rus_name' => 'Расход',                    'default_direction' => 'out'],
            ['code' => 'supplier_payment', 'name' => 'Оплата поставщику',         'rus_name' => 'Оплата поставщику',         'default_direction' => 'out'],
            ['code' => 'supplier_refund',  'name' => 'Возврат от поставщика',     'rus_name' => 'Возврат от поставщика',     'default_direction' => 'in'],
            ['code' => 'other_income',     'name' => 'Прочий доход',              'rus_name' => 'Прочий доход',              'default_direction' => 'in'],
            ['code' => 'transfer',         'name' => 'Перевод между счетами',     'rus_name' => 'Перевод между счетами',     'default_direction' => 'out'],
            ['code' => 'customer_refund',  'name' => 'Возврат клиенту',           'rus_name' => 'Возврат клиенту',           'default_direction' => 'out'],
            ['code' => 'owner_withdrawal', 'name' => 'Личное изъятие',            'rus_name' => 'Личное изъятие',            'default_direction' => 'out'],
            ['code' => 'opening_balance',  'name' => 'Входящий остаток',          'rus_name' => 'Входящий остаток',          'default_direction' => 'in'],
        ];

        foreach ($categories as $cat) {
            DB::table('cashflow_categories')->updateOrInsert(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'rus_name' => $cat['rus_name'],
                    'default_direction' => $cat['default_direction'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}

/*
Что делает этот сидер:

- Заполняет таблицу cashflow_categories реальными категориями (сверено
  с локальной БД 2026-09-17), в ТОМ ЖЕ порядке — критично, потому что
  код ссылается на них по числовому id, не по коду.
- updateOrInsert по code — безопасно перезапускать. НО если таблица на
  проде УЖЕ содержит другие строки под этими id (не пустая с самого
  начала) — id всё равно назначит auto-increment по порядку вставки,
  так что запускать нужно на ПУСТОЙ таблице cashflow_categories для
  гарантии совпадения id 1..9.
- php artisan db:seed --class=CashflowCategoriesSeeder --force
*/