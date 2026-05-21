<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Str;

$fullPath = storage_path('app/tmp/manual_offers.xlsx');

if (!file_exists($fullPath)) {
    echo "\033[31mОшибка: Файл manual_offers.xlsx не найден в storage/app/tmp/\033[0m\n";
    exit(1);
}

echo "\033[32mОчищаем таблицу перед повторным, чистым тестом...\033[0m\n";
DB::table('kaspi_initial_products')->truncate();

echo "\033[32mСтарт исправленного импорта твоих позиций...\033[0m\n";

if ($xlsx = SimpleXLSX::parse($fullPath)) {
    $rows = $xlsx->rows();
    unset($rows[0]); // Удаляем шапку таблицы

    $count = 0;
    foreach ($rows as $row) {
        // ИСПРАВЛЕННЫЙ ПОРЯДОК КОЛОНОК НА ОСНОВЕ ТВОЕЙ ТАБЛИЦЫ:
        $sku   = trim($row[0] ?? '');
        $model = trim($row[1] ?? ''); // Длинная строка с описанием машины
        $brand = trim($row[2] ?? ''); // Чистый короткий бренд (Motodor, Alfeco)
        $price = $row[3] ?? 0;

        if (empty($sku)) continue;

        // Чистим цену от пробелов и запятых
        $price = str_replace([' ', ','], ['', '.'], $price);
        
        // Округляем цену до целого числа в большую сторону (без копеек и точек для Каспи)
        $finalPrice = ceil((float)$price);

        DB::table('kaspi_initial_products')->insert([
            'sku'           => $sku,
            'title'         => Str::limit($model, 255, ''), // Длинную строку пишем в Название (для XML <model>)
            'brand'         => mb_strtolower($brand),       // Чистый бренд в нижний регистр для базы
            'price'         => $finalPrice,                 // Целая цена без хвостиков
            'stock'         => 5,                           
            'preorder_days' => 10,                          // Предзаказ 10 дней
            'category_code' => null,
            'description'   => $model,                      // Сюда тоже дублируем полное описание, чтобы не было NULL
            'images'        => null,
            'attributes'    => null,
            'created_at'    => now(),
            'updated_at'    => now()
        ]);
        $count++;
    }
    
    echo "\033[32m=================================================\033[0m\n";
    echo "\033[32mУСПЕХ! В базу правильно залито товаров: {$count} шт.\033[0m\n";
} else {
    echo "\033[31mОшибка парсинга Excel: " . SimpleXLSX::parseError() . "\033[0m\n";
}
