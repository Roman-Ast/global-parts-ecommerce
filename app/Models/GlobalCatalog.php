<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalCatalog extends Model
{
    protected $table = 'global_catalog';

    public function getPlaceholder()
    {
        $name = mb_strtolower($this->name); // Приводим к нижнему регистру для поиска

        // Массив соответствий: "слово в названии" => "название файла картинки"
        $map = [
            'фильтр' => 'filter.png',
            'амортизатор' => 'shock.jpeg',
            'стойка' => 'shock.jpeg',
            'колодк' => 'brake_pads.png',
            'диск тормоз' => 'brake_disk.jpeg',
            'свеч' => 'spark_plug.png',
            'ремень' => 'belt.png',
            'цепь' => 'chain.png',
            'помп' => 'water_pump.png',
            'радиатор' => 'radiator.png',
            'фара' => 'headlight.png',
            'фонарь' => 'taillight.png',
            'щетк' => 'wiper.png',
            'рычаг' => 'arm.png',
            'шаров' => 'ball_joint.png',
            'ступиц' => 'hub.png',
            'подшипник' => 'bearing.png',
            'шруз' => 'cv-joint.jpeg', // на случай опечаток
            'шрус' => 'cv-joint.jpeg',
            'гранат' => 'cv-joint.jpeg',
            'масло' => 'oil.png',
            'антифриз' => 'coolant.png',
            'стартер' => 'starter.png',
            'генератор' => 'alternator.png',
            'аккумулятор' => 'battery.png',
            'акб' => 'battery.png',
        ];

        foreach ($map as $key => $file) {
            if (str_contains($name, $key)) {
                return asset('images/placeholders/' . $file);
            }
        }

        // Если ничего не подошло — отдаем общую иконку шестеренки
        return asset('images/placeholders/generic_part.png');
    }
}
