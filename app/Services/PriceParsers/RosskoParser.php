<?php

namespace App\Services\PriceParsers;

class RosskoParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        $brand    = trim((string)($row[1] ?? ''));
        $article  = trim((string)($row[2] ?? ''));
        $title    = trim((string)($row[3] ?? ''));
        $rawPrice = $row[6] ?? '0';  // Цена, KZT (закупочная)
        $rawQty   = $row[8] ?? '0';  // Наличие

        if (empty($brand) || empty($article) || $brand === 'Бренд') {
            return null;
        }

        $qty   = (int) preg_replace('/[^0-9]/', '', (string)$rawQty);
        $price = (float) str_replace([' ', ','], ['', '.'], (string)$rawPrice);

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