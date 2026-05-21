<?php

// Подключаем ядро Laravel, чтобы работала база данных DB::
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Старт скрипта маппинга...\n";

$filePath = storage_path('app/kaspi_categories.json');
if (!file_exists($filePath)) {
    die("🚨 Ошибка: Файл {$filePath} не найден!\n");
}

$raw = file_get_contents($filePath);
$cleanText = preg_replace('#\x1b\[[0-9;]*[a-zA-Z]#', '', $raw);

$kaspiCategories = [];
$lines = explode("\n", $cleanText);
$currentCode = null;

foreach ($lines as $line) {
    if (preg_match('/["\']?code["\']?\s*(=>|:)\s*["\']([^"\']+)["\']/', $line, $matches)) {
        $currentCode = trim($matches[2]);
    }
    if (preg_match('/["\']?title["\']?\s*(=>|:)\s*["\']([^"\']+)["\']/', $line, $matches) && $currentCode) {
        $kaspiCategories[] = [
            'code' => $currentCode,
            'title' => trim($matches[2])
        ];
        $currentCode = null;
    }
}

echo "Извлечено категорий Каспи: " . count($kaspiCategories) . " шт.\n";

if (count($kaspiCategories) === 0) {
    die("🚨 Ошибка: Не удалось распарсить категории. Проверь содержимое файла.\n");
}

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

foreach ($popularWords as $word) {
    $wordLower = mb_strtolower(trim($word));
    if (strlen($wordLower) < 3 || is_numeric($wordLower) || in_array($wordLower, ['комплект', 'ремкомплект', 'деталь', 'для', 'shatem', 'febest', 'winkod', 'bosch'])) {
        continue;
    }

    $searchKeyword = mb_substr($wordLower, 0, -2);
    if (strlen($searchKeyword) < 3) $searchKeyword = $wordLower;

    foreach ($kaspiCategories as $item) {
        $titleLower = mb_strtolower($item['title']);
        if (str_contains($titleLower, $searchKeyword)) {
            DB::table('kaspi_category_rules')->updateOrInsert(
                ['keyword' => $searchKeyword],
                ['kaspi_code' => $item['code'], 'created_at' => now(), 'updated_at' => now()]
            );
            $mappedCount++;
            echo "Связано: {$searchKeyword} => {$item['code']}\n";
            break;
        }
    }
}

echo "=== ФИНАЛ! Залито правил в базу: $mappedCount ===\n";
