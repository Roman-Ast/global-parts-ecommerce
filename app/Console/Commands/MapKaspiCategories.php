<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MapKaspiCategories extends Command
{
    protected $signature = 'kaspi:map-categories';
    protected $description = 'Автоматический маппинг популярных ключевых слов запчастей с категориями Каспи из JSON';

    public function handle()
    {
        $this->info('Старт автоматического маппинга категорий...');

        // 1. Проверяем и читаем JSON-файл Каспи
        if (!Storage::exists('kaspi_categories.json')) {
            $this->error('Файл storage/app/kaspi_categories.json не найден!');
            return 1;
        }

        $jsonContent = Storage::get('kaspi_categories.json');
        $kaspiData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Ошибка валидации JSON файла Каспи.');
            return 1;
        }

        // Вытаскиваем плоский список категорий Каспи (код => название)
        // Структура JSON Каспи обычно древовидная, адаптируем сбор под нее
        $kaspiCategories = $this->parseKaspiJson($kaspiData);
        $this->info('Загружено ' . count($kaspiCategories) . ' категорий из JSON Каспи.');

        // 2. Вытаскиваем ТОП-150 популярных первых слов из твоей же БД global_catalog
        $this->info('Собираем популярные ключевые слова из базы данных...');
        $popularWords = DB::table('global_catalog')
            ->select(DB::raw("SUBSTRING_INDEX(TRIM(name), ' ', 1) AS first_word"))
            ->whereNotNull('name')
            ->where('price', '>', 0)
            ->groupBy('first_word')
            ->having(DB::raw('COUNT(*)'), '>', 10) // Берем слова, у которых больше 10 товаров
            ->pluck('first_word')
            ->toArray();

        $mappedCount = 0;

        // 3. Главный цикл маппинга
        foreach ($popularWords as $word) {
            $word = trim($word);
            $wordLower = mb_strtolower($word);

            // Пропускаем мусорные слова, знаки препинания и бренды, чтобы не сломать логику
            if (strlen($word) < 3 || is_numeric($word) || in_array($wordLower, ['комплект', 'ремкомплект', 'деталь', 'для', 'shatem', 'febest', 'winkod', 'bosch'])) {
                continue;
            }

            // Отрезаем окончания для более гибкого поиска (например: "амортизатор" -> "амортиз")
            $searchKeyword = mb_substr($wordLower, 0, -2); 
            if (strlen($searchKeyword) < 3) {
                $searchKeyword = $wordLower; // Если слово было коротким
            }

            // Ищем совпадение в названиях категорий Каспи
            foreach ($kaspiCategories as $code => $title) {
                $titleLower = mb_strtolower($title);

                // Если в названии категории Каспи (на русском) есть наше ключевое слово
                if (str_contains($titleLower, $searchKeyword)) {
                    
                    // Красиво и аккуратно заливаем в таблицу kaspi_category_rules
                    // updateOrInsert защитит от создания дубликатов, если слово уже забито
                    DB::table('kaspi_category_rules')->updateOrInsert(
                        ['keyword' => $searchKeyword],
                        [
                            'kaspi_code' => $code,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );

                    $this->line("Замаплено: <info>{$searchKeyword}</info> => [{$code}] {$title}");
                    $mappedCount++;
                    break; // Нашли категорию для слова, переходим к следующему слову
                }
            }
        }

        $this->info("==================================================");
        $this->info("Успех! Создано/обновлено правил в таблице: {$mappedCount}");
        return 0;
    }

    /**
     * Рекурсивный обход JSON дерева Каспи для сбора конечных категорий
     */
    private function parseKaspiJson($data, &$result = [])
    {
        // В зависимости от того, как Каспи отдает JSON (списком или деревом),
        // этот метод собирает пары "Code" => "Title".
        // Ниже — стандартный вариант для древовидного классификатора:
        if (isset($data['code']) && isset($data['title'])) {
            // Если у категории нет подкатегорий (это конечный узел)
            if (empty($data['subCategories'] ?? $data['children'] ?? [])) {
                $result[$data['code']] = $data['title'];
            }
        }

        $children = $data['subCategories'] ?? $data['children'] ?? $data ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_array($child)) {
                    $this->parseKaspiJson($child, $result);
                }
            }
        }

        // Если JSON изначально плоский массив объектов:
        if (empty($result) && is_array($data)) {
            foreach ($data as $item) {
                if (isset($item['code']) && isset($item['title'])) {
                    $result[$item['code']] = $item['title'];
                }
            }
        }

        return $result;
    }
}