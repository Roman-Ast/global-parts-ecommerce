<?php
namespace App\Services\PriceParsers;

class ZakazAutoParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Если артикул [1], бренд [2] или название [0] пустые — скипаем строку.
        if (empty($row[1]) || empty($row[2]) || $row[1] === '№ по кат.') {
            return null;
        }

        $rawArticle = trim((string)$row[1]);
        $brand = trim((string)$row[2]);
        $rawName = trim((string)$row[0]);

        // Очищаем цену
        $rawPrice = str_replace(',', '', (string)$row[3]);
        $price = (float)$rawPrice;
        
        $stock = isset($row[4]) ? (int)$row[4] : 0;

        if ($stock <= 0) {
            return null;
        }

        // Чистый SKU для Kaspi
        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $rawArticle);

        // Собираем красивое название по нашему шаблону
        $prettyTitle = $this->generatePrettyTitle($rawName, $brand, $rawArticle);

        // ВОЗВРАЩАЕМ СТАНДАРТИЗИРОВАННЫЙ МАССИВ
        return [
            'sku'               => $cleanSku,
            'brand'             => $brand,
            'title'             => $prettyTitle,
            'price'             => $price,
            'stock'             => $stock,
            'category_code'     => null,
            'description'       => null,
            'images'            => null,
            'attributes'        => null,
            'raw_cross_numbers' => null,
            // ЖЕСТКО ЗАВЯЗЫВАЕМ СТАТУС ПРЕДЗАКАЗА ДЛЯ КОСТАНАЯ — 7 ДНЕЙ!
            'preorder_days'     => 7, 
        ];
    }

    /**
     * Делает красивое название, убирая кавычки на концах и дубли брендов
     */
    private function generatePrettyTitle(string $rawName, string $brand, string $article): string
    {
        // Убираем лишние кавычки, если ЗаказАвто прислал '"Губка автомобильная'
        $cleanName = trim($rawName, '"\' ');
        
        // Убираем бренд из названия, если он там уже продублирован
        $cleanName = str_ireplace($brand, '', $cleanName);
        $cleanName = trim(mb_ucfirst($cleanName));

        return "{$cleanName} {$brand} (арт. {$article})";
    }
}

// Вспомогательная функция для UTF-8 заглавной буквы (если её нет)
if (!function_exists('mb_ucfirst')) {
    function mb_ucfirst($string) {
        return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
    }
}