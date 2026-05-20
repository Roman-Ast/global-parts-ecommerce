<?php

namespace App\Services\PriceParsers;

class PhaetonParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Проверяем шапку или пустые строки. 
        // Если в артикуле [0] написано слово "Артикул" или там пусто — пропускаем строку.
        if (empty($row[0]) || empty($row[1]) || $row[0] === 'Артикул') {
            return null;
        }

        $rawArticle = trim((string)$row[0]);
        $brand = trim((string)$row[1]);
        $rawName = trim((string)$row[2]);

        // Извлекаем цену из индекса [7] и количество в Астане из индекса [8]
        $price = isset($row[7]) ? (float)$row[7] : 0.0;
        $stock = isset($row[8]) ? (int)$row[8] : 0;

        // Фильтр: если товара в Астане нет (0 или пусто), не тащим его в первичку Kaspi
        if ($stock <= 0) {
            return null;
        }

        // Очищаем артикул для Kaspi SKU (SA-1631R -> SA1631R)
        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $rawArticle);

        // Собираем красивое название для модераторов Kaspi
        $prettyTitle = $this->generatePrettyTitle($rawName, $brand, $rawArticle);

        return [
            'sku'               => $cleanSku,
            'brand'             => $brand,
            'title'             => $prettyTitle,
            'price'             => $price,
            'stock'             => $stock,
            'category_code'     => null, 
            'description'       => null,
            'images'            => null, // Наполним картинками позже автоматикой
            'attributes'        => null, 
            'raw_cross_numbers' => null, // У Фаэтона в этом прайсе кроссов нет, оставим null
        ];
    }

    /**
     * Делает из "555 Рычаг" нормальное продающее название
     */
    private function generatePrettyTitle(string $rawName, string $brand, string $article): string
    {
        // Убираем дублирование бренда из названия, если Фаэтон написал "555 Рычаг"
        $cleanName = str_ireplace($brand, '', $rawName);
        
        // Убираем лишние пробелы и делаем первую букву заглавной
        $cleanName = trim(mb_ucfirst($cleanName)); 

        // Итог: "Рычаг подвески 555 (арт. SA-1631R)"
        return "{$cleanName} {$brand} (арт. {$article})";
    }
}

// Вспомогательная функция для заглавной буквы в UTF-8
if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst($string) {
        return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
    }
}