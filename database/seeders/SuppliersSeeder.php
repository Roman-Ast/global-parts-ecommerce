<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuppliersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Формат:
        // code => [name, lead_days, grace_days]
        // lead_days  = средний срок поставки (дней)
        // grace_days = отсрочка после срока поставки (дней)

        $suppliers = [
            'shtm' => ['Шатэ-М', null, 0],
            'rssk' => ['Росско', null, 0],
            'trd' => ['Автотрейд', null, 0],
            'tss' => ['Тисс', null, 0],
            'rmtk' => ['Армтек', null, 0],
            'phtn' => ['Фаэтон', null, 0],

            // Ключевые:
            // Автопитер: 7–10 дней, отсрочка 5 дней -> ставим среднее lead_days=9, grace_days=5
            'atptr' => ['Автопитер', 9, 5],

            // Автозакуп: 7–10 дней, отсрочки нет -> lead_days=9, grace_days=0
            'avtozakup' => ['Автозакуп', 9, 0],

            'emex' => ['emex', null, 0],
            'rlm' => ['Рулим', null, 0],
            'radle' => ['Radle', null, 0],
            'fbst' => ['Фебест', null, 0],
            'Krn_tnt' => ['Корея Танат', null, 0],
            'kln' => ['Кулан', null, 0],
            'frmt' => ['Форумавто', null, 0],
            'china_ata' => ['Китайцы Алматы', null, 0],
            'china_igor' => ['Китай Игорь', null, 0],
            'voltag_ast' => ['Вольтаж Астана', null, 0],
            'kz_starter' => ['КЗ стартер', null, 0],
            'cc_motors_talgat' => ['СС моторс Талгат', null, 0],
            'gerat_ast' => ['Герат Астана', null, 0],
            'kainar_razbor_tima' => ['Кайнар Тима', null, 0],
            'zakaz_auto' => ['заказ авто', null, 0],
            'kap' => ['Кореан Автопартс', null, 0],
            'alem_auto' => ['Алемавто', null, 0],
            'erdos_avtomart' => ['Ердос Автомарт', null, 0],
            'thr' => ['Сторонние', null, 0],
        ];

        foreach ($suppliers as $code => $data) {
            [$name, $leadDays, $graceDays] = $data;

            // updateOrInsert: можно запускать повторно без дублей
            DB::table('suppliers')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'lead_days' => $leadDays,
                    'grace_days' => $graceDays,
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

- Заполняет таблицу suppliers справочником поставщиков:
  code = твой код (legacy), name = человекочитаемое имя.
- Сроки для расчета графика оплат:
  * lead_days = средний срок поставки
  * grace_days = отсрочка
- Сейчас заполнено:
  * Автопитер (atptr): lead_days=9, grace_days=5 (7–10 дней + 5 дней отсрочка)
  * Автозакуп (avtozakup): lead_days=9, grace_days=0 (7–10 дней, без отсрочки)
  Остальные: grace_days=0, lead_days=null (платишь сразу, срок не важен для кредиторки).
*/
