<?php

namespace App\Services\PriceParsers;

class VoltazhParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Конвертируем кодировку
        $row = array_map(fn($cell) => mb_convert_encoding(
            trim((string)$cell), 'UTF-8', 'Windows-1251'
        ), $row);

        // Колонки: 0=num, 1=article(поставщика), 2=number(артикул детали), 3=brand, 4=name, 5=qty, 6=price
        $article = $row[1] ?? '';  // артикул поставщика (игнорируем)
        $number  = $row[2] ?? '';  // оригинальный номер детали → sku
        $brand   = $row[3] ?? '';
        $title   = $row[4] ?? '';
        $rawQty  = $row[5] ?? '0';
        $rawPrice = $row[6] ?? '0';

        if (empty($number) || empty($brand) || $number === 'Номер') {
            return null;
        }

        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $number);
        if (empty($cleanSku)) {
            $cleanSku = $number;
        }

        $qty   = (int) preg_replace('/[^0-9]/', '', $rawQty);
        // Цена приходит как "19 835,00" — убираем nbsp и пробелы, заменяем запятую
        $price = (float) str_replace(["\xc2\xa0", "\xa0", ' ', ','], ['', '', '', '.'], $rawPrice);

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
            'raw_cross_numbers' => $article, // артикул поставщика сохраняем сюда
        ];
    }
}