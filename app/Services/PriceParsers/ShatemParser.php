<?php

namespace App\Services\PriceParsers;

class ShatemParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Зависит от структуры Шатэма: 
        // 0 - Внутренний код/SKU, 1 - Артикул, 2 - Бренд, 3 - Название, 4 - Цена закупа, 5 - Количество
        $sku     = trim((string)($row[0] ?? ''));
        $article = trim((string)($row[1] ?? ''));
        $brand   = trim((string)($row[2] ?? ''));
        $title   = trim((string)($row[3] ?? ''));
        
        // Временные сырые данные для цены и остатка
        $rawPrice = $row[4] ?? 0;
        $rawQty   = $row[5] ?? 0;

        // Если базовые поля пустые или это шапка таблицы — пропускаем строку
        if (empty($sku) || empty($article) || empty($brand) || $sku === 'Код' || $article === 'Артикул') {
            return null;
        }

        // Очищаем артикул для Kaspi SKU (убираем спецсимволы)
        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $article);
        // Если cleanSku получился пустым, используем оригинальный SKU поставщика
        if (empty($cleanSku)) {
            $cleanSku = $sku;
        }

        // Возвращаем стандартизированный массив сырых данных
        return [
            'sku'               => $cleanSku, // используем чистый артикул как SKU для Каспи
            'brand'             => $brand,
            'title'             => $title,
            'price'             => $rawPrice,
            'stock'             => $rawQty,
            'category_code'     => null,
            'description'       => null,
            'images'            => null,
            'attributes'        => null,
            'raw_cross_numbers' => null,
        ];
    }
}