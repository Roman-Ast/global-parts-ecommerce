<?php

// 1. Подключаем ядро Laravel для работы с БД
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Очищаем старые кривые правила из таблицы...\n";
DB::table('kaspi_category_rules')->truncate(); // Полностью очищаем таблицу перед чистым запуском

echo "Читаем файл категорий Каспи...\n";
$filePath = storage_path('app/kaspi_categories.json');
if (!file_exists($filePath)) {
    die("🚨 Ошибка: Файл {$filePath} не найден!\n");
}

$raw = file_get_contents($filePath);
$cleanText = preg_replace('#\x1b\[[0-9;]*[a-zA-Z]#', '', $raw);

// Парсим категории Каспи (учитываем стрелочки и двоеточия)
$kaspiCategories = [];
$lines = explode("\n", $cleanText);
$currentCode = null;

foreach ($lines as $line) {
    if (preg_match('/["\']?code["\']?\\s*(=>|:)\\s*["\']([^"\']+)["\']/', $line, $matches)) {
        $currentCode = trim($matches[2]);
    }
    if (preg_match('/["\']?title["\']?\\s*(=>|:)\\s*["\']([^"\']+)["\']/', $line, $matches) && $currentCode) {
        $kaspiCategories[] = [
            'code' => $currentCode,
            'title' => trim($matches[2])
        ];
        $currentCode = null;
    }
}

echo "Извлечено категорий Каспи: " . count($kaspiCategories) . " шт.\n";

// Собираем ТОП первых слов из твоей базы
$popularWords = DB::table('global_catalog')
    ->select(DB::raw("SUBSTRING_INDEX(TRIM(name), ' ', 1) AS first_word"))
    ->whereNotNull('name')
    ->where('price', '>', 0)
    ->groupBy('first_word')
    ->having(DB::raw('COUNT(*)'), '>', 10)
    ->pluck('first_word')
    ->toArray();

echo "Собрано популярных слов со склада: " . count($popularWords) . " шт.\n";

$mappedCount = 0;

// Черный список слов, которые НЕ должны становиться категориями
$blackList = [
    'комплект', 'ремкомплект', 'деталь', 'для', 'shatem', 'febest', 'winkod', 
    'bosch', 'адаптер', 'набор', 'элемент', 'штука', 'упаковка', 'оригинал'
];

foreach ($popularWords as $word) {
    $wordLower = mb_strtolower(trim($word));
    
    // Пропускаем мусор, цифры и черный список
    if (mb_strlen($wordLower) < 3 || is_numeric($wordLower) || in_array($wordLower, $blackList)) {
        continue;
    }

    // УМНОЕ ОТСРЕЗАНИЕ ОКОНЧАНИЙ:
    // Если слово длинное (больше 5 букв), отрезаем 2 буквы для гибкости (амортизатор -> амортизат)
    // Если слово короткое (5 букв или меньше, типа "цепь", "фара", "тяга"), оставляем его ЦЕЛИКОМ!
    if (mb_strlen($wordLower) > 5) {
        $searchKeyword = mb_substr($wordLower, 0, -2);
    } else {
        $searchKeyword = $wordLower;
    }

    // Жесткая страховка: корень не должен быть короче 4 букв! (убьет "пр", "це", "шт")
    if (mb_strlen($searchKeyword) < 4) {
        $searchKeyword = $wordLower; // Возвращаем полное слово, если обрубок стал слишком маленьким
    }

    foreach ($kaspiCategories as $item) {
        $titleLower = mb_strtolower($item['title']);
        
        // Ищем строгое совпадение по корню слова
        if (str_contains($titleLower, $searchKeyword)) {
            
            // Дополнительная защита: если корень "цепь", не даем ему улететь в "прицепы"
            if ($searchKeyword === 'цепь' && str_contains($titleLower, 'прицеп')) {
                continue; 
            }

            DB::table('kaspi_category_rules')->updateOrInsert(
                ['keyword' => $searchKeyword],
                [
                    'kaspi_code' => $item['code'], 
                    'created_at' => now(), 
                    'updated_at' => now()
                ]
            );
            $mappedCount++;
            echo "Идеальное совпадение: [{$searchKeyword}] => {$item['code']} ({$item['title']})\n";
            break;
        }
    }
}

echo "=== ГОТОВО! Залито чистых, проверенных правил: $mappedCount ===\n";
