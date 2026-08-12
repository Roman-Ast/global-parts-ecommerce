<?php

namespace App\Services\PriceParsers;

class TstarterParser
{
    /**
     * Ожидаемые колонки (после удаления строки заголовка в readRows()):
     * 0 Склад | 1 Наименование товара | 2 № товара | 3 Производитель | 4 К-во | 5 Цена
     *
     * Примечание: в заголовке файла у поставщика написано "Цена, руб",
     * но по факту это тенге (KZT) — они просто ошибочно используют
     * старый шаблон названия колонки. Конвертация не нужна.
     */
    public function parseRow(array $row): ?array
    {
        if (count($row) < 6) {
            return null;
        }

        $sku   = trim((string) $row[2]);
        $title = trim((string) $row[1]);
        $brand = trim((string) $row[3]);
        $stock = $row[4];
        $price = $row[5];

        if ($sku === '' || $title === '' || $brand === '') {
            return null;
        }

        return [
            'sku'           => $sku,
            'title'         => $title,
            'brand'         => $brand,
            'price'         => $price,
            'stock'         => $stock,
            'preorder_days' => 0,
        ];
    }
}