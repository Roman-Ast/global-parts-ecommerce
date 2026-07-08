<?php

namespace App\Services\PriceParsers;

class InterkomParser implements ParserInterface, MultiSheetParserInterface
{
    private const ALLOWED_SHEETS = ['LADA', 'GAZ', 'LargusRenault', 'Chevrolet'];
    private const DATA_START_ROW = 10;
    private const MIN_PRICE = 3000;
    private const FIXED_QTY = 2;

    private const PREORDER_DAYS_MAP = [
        '+'   => 1,
        '~~~' => 2,
    ];

    private const SUPPLIER_NAME_MAP = [
        'LADA'          => 'interkom_lada',
        'GAZ'           => 'interkom_gaz',
        'LargusRenault' => 'interkom_largusrenault',
        'Chevrolet'     => 'interkom_chevrolet',
    ];

    public function getAllowedSheets(): array
    {
        return self::ALLOWED_SHEETS;
    }

    public function resolveSupplierName(string $sheetName): string
    {
        return self::SUPPLIER_NAME_MAP[$sheetName] ?? 'interkom_unknown';
    }

    public function getDataStartRow(): int
    {
        return self::DATA_START_ROW;
    }

    /**
     * $row ожидается как обычный индексированный массив колонок строки
     * (как у RosskoParser), без привязки к листу — раннер сам знает,
     * с какого листа строка пришла, и подставит правильный supplier_name
     * через resolveSupplierName() уже после parseRow().
     */
    public function parseRow(array $row): ?array
    {
        $article     = trim((string)($row[2] ?? ''));  // C -> Артикул
        $supplierArt = trim((string)($row[3] ?? ''));  // D -> Арт. поставщика
        $name        = trim((string)($row[4] ?? ''));  // E -> Товар
        $brand       = trim((string)($row[5] ?? ''));  // F -> Бренд
        $stockMark   = trim((string)($row[7] ?? ''));  // H -> Остаток
        $priceRaw    = trim((string)($row[8] ?? ''));  // I -> Цена

        if ($article === '' && $supplierArt === '') {
            return null;
        }

        $finalArticle = $article !== '' ? $article : $supplierArt;

        if (!array_key_exists($stockMark, self::PREORDER_DAYS_MAP)) {
            return null;
        }

        $price = (float) str_replace([' ', "\xC2\xA0"], '', $priceRaw);

        if ($price < self::MIN_PRICE) {
            return null;
        }

        return [
            'sku'               => $finalArticle,
            'brand'             => $brand,
            'title'             => mb_substr($name, 0, 255),
            'price'             => $price,
            'stock'             => self::FIXED_QTY,
            'preorder_days'     => self::PREORDER_DAYS_MAP[$stockMark],
            'category_code'     => null,
            'description'       => null,
            'images'            => null,
            'attributes'        => null,
            'raw_cross_numbers' => null,
        ];
    }
}