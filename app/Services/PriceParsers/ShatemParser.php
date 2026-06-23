<?php

namespace App\Services\PriceParsers;

class ShatemParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Файл приходит в KOI8-R, конвертируем каждую ячейку
        $row = array_map(fn($cell) => mb_convert_encoding(
            trim((string)$cell), 'UTF-8', 'Windows-1251'
        ), $row);

        $brand    = $row[0] ?? '';
        $article  = $row[1] ?? '';
        $title    = $row[2] ?? '';
        $rawQty   = $row[3] ?? '0'; // было [4]
        $rawPrice = $row[6] ?? '0'; // это было правильно

        if (empty($brand) || empty($article) || $brand === 'Бренд') {
            return null;
        }

        // Остаток может быть "10>" — убираем нецифровые символы
        $qty   = (int)preg_replace('/[^0-9]/', '', $rawQty);
        $price = (float)str_replace([' ', ','], ['', '.'], $rawPrice);

        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $article);
        if (empty($cleanSku)) {
            $cleanSku = $article;
        }

        return [
            'sku'               => $cleanSku,
            'brand'             => $brand,
            'title'             => $title,
            'price'             => $price,
            'stock'             => $qty,
            'category_code'     => null,
            'description'       => null,
            'images'            => null,
            'attributes'        => null,
            'raw_cross_numbers' => null,
        ];
    }
}