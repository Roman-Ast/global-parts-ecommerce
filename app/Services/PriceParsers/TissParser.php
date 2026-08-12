<?php

namespace App\Services\PriceParsers;

class TissParser
{
    /**
     * Индексы колонок в исходном XLS TISS (0-indexed, после SimpleXLS::rows()).
     * Файл содержит служебную шапку (название, дата, валюта) и заголовки
     * категорий вперемешку с товарными строками — оба типа "мусорных" строк
     * отсекаются в parseRow() по отсутствию бренда/артикула/числовой цены,
     * так что явно резать по номеру строки (dataStartRow) не нужно.
     */
    private const COL_BRAND = 1;
    private const COL_TITLE = 2;
    private const COL_SKU = 3;
    private const COL_APPLICABILITY = 4;
    private const COL_PRICE = 8;   // "ОПТ"
    private const COL_STOCK = 10;  // "Кол-во всего" (совпадает с колонкой "Астана" — склад один)

    public function parseRow(array $row): ?array
    {
        $brand = trim((string) ($row[self::COL_BRAND] ?? ''));
        $sku = trim((string) ($row[self::COL_SKU] ?? ''));
        $title = trim((string) ($row[self::COL_TITLE] ?? ''));
        $priceRaw = $row[self::COL_PRICE] ?? null;
        $stockRaw = $row[self::COL_STOCK] ?? null;

        // Отсекаем: строки-заголовки категорий (нет бренда/артикула),
        // служебную шапку файла и саму строку с названиями колонок
        // (там в "цене" будет текст "ОПТ", не число — это и ловим).
        if ($brand === '' || $sku === '' || $title === '') {
            return null;
        }

        if (!is_numeric($priceRaw)) {
            return null;
        }

        $stock = is_numeric($stockRaw) ? (int) $stockRaw : 0;

        return [
            'sku' => $sku,
            'title' => $title,
            'brand' => $brand,
            'price' => (float) $priceRaw,
            'stock' => $stock,
            'preorder_days' => 0,
        ];
    }
}