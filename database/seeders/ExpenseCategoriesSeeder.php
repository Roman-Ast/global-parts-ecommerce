<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ПЕРЕПИСАН 2026-09-17 — старая версия была на английском и с совсем
 * другим набором категорий (fuel/rent/tax/salary/credit_2gis/
 * credit_apartment/credit_grandma/google_ads/olx/food), не совпадающим
 * с тем, чем реально пользуется дашборд сейчас. В отличие от
 * cashflow_categories, здесь ID жёстко нигде не зашит (выбор в форме —
 * по факту из БД), так что порядок не критичен, но состав должен быть
 * реальным. Список — 1:1 экспорт из локальной БД на 2026-09-17.
 */
class ExpenseCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'rent',              'name' => 'Аренда',                                   'rus_name' => 'Аренда'],
            ['code' => 'salary',            'name' => 'Зарплата',                                 'rus_name' => 'Зарплата'],
            ['code' => 'advertising',       'name' => 'Реклама',                                  'rus_name' => 'Реклама'],
            ['code' => 'logistics',         'name' => 'Логистика/доставка',                       'rus_name' => 'Логистика/доставка'],
            ['code' => 'taxes',             'name' => 'Налоги',                                   'rus_name' => 'Налоги'],
            ['code' => 'bank_fees',         'name' => 'Комиссии банка/эквайринга',                'rus_name' => 'Комиссии банка/эквайринга'],
            ['code' => 'marketplace_fees',  'name' => 'Комиссии маркетплейсов (Kaspi/Satu/Ozon)', 'rus_name' => 'Комиссии маркетплейсов (Kaspi/Satu/Ozon)'],
            ['code' => 'office_supplies',   'name' => 'Хозрасходы/канцелярия',                    'rus_name' => 'Хозрасходы/канцелярия'],
            ['code' => 'communication',     'name' => 'Связь и интернет',                         'rus_name' => 'Связь и интернет'],
            ['code' => 'software',          'name' => 'Софт/подписки/хостинг',                    'rus_name' => 'Софт/подписки/хостинг'],
            ['code' => 'repairs',           'name' => 'Ремонт/обслуживание',                      'rus_name' => 'Ремонт/обслуживание'],
            ['code' => 'other',             'name' => 'Прочее',                                   'rus_name' => 'Прочее'],
            ['code' => 'fuel',              'name' => 'ГСМ',                                      'rus_name' => 'ГСМ'],
        ];

        foreach ($categories as $cat) {
            DB::table('expense_categories')->updateOrInsert(
                ['code' => $cat['code']],
                [
                    'name' => $cat['name'],
                    'rus_name' => $cat['rus_name'],
                    'is_active' => 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}

/*
php artisan db:seed --class=ExpenseCategoriesSeeder --force
*/