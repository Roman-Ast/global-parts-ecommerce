<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('accounts')->insert([
            [
                'name' => 'Kaspi Gold (Roman)',
                'currency' => 'KZT',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kaspi Gold (Igor)',
                'currency' => 'KZT',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kaspi Pay',
                'currency' => 'KZT',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Halyk (Roman)',
                'currency' => 'KZT',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cash',
                'currency' => 'KZT',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

/*
Что делает этот сидер:

- Заполняет таблицу accounts твоими счетами.
- Можно запускать на локалке и на хостинге.
- Если нужно будет добавить новые счета — просто добавляешь строку сюда.
*/
