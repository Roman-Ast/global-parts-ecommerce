<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ОБНОВЛЕНО 2026-09-17 — старая версия (Kaspi Gold (Roman)/Kaspi Pay/
 * Halyk (Roman) и т.д.) разошлась с реальными именами счетов в базе:
 * локально таблица `accounts` давно пересоздана под другие названия
 * (видимо, вручную через tinker/SQL, не через этот файл), а код в
 * AdminPanelController ищет конкретные счета по ИМЕНИ строкой —
 * `Accounts::where('name', 'Рома Kaspi Pay')` (автовыплата Kaspi после
 * выдачи заказа) и `Accounts::where('name', 'Рома Kaspi Gold')`
 * (предоплаченные заказы). Со старыми именами сидера эти автоматизации
 * молча не находили бы нужный счёт на проде. Синхронизировано с тем,
 * что реально есть в локальной БД сейчас.
 */
class AccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            'Игорь Kaspi Gold',
            'Рома Kaspi Gold',
            'Рома Kaspi Pay',
            'Наличные',
            'Рома Халык',
            'Kaspi Pay безнал',
        ];

        foreach ($accounts as $name) {
            DB::table('accounts')->updateOrInsert(
                ['name' => $name],
                [
                    'currency' => 'KZT',
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

- Заполняет таблицу accounts реальными счетами (сверено с локальной БД
  2026-09-17). updateOrInsert по имени — безопасно запускать повторно,
  не создаст дублей, если часть счетов уже есть.
- Можно запускать на локалке и на хостинге:
  php artisan db:seed --class=AccountsSeeder
- Если нужно будет добавить новый счёт — просто добавляешь имя в массив выше.
*/
