<?php

namespace App\Services\PriceParsers;

class ZakazAutoKostanayParser
{
    // Колонки файла (0-based, после снятия строки заголовка в readRows()):
    // 0 - Номенклатура (название)
    // 1 - № по кат. (артикул, иногда с мусорными символами на конце, например "¶")
    // 2 - Производитель (бренд)
    // 3 - Цена
    // 4 - Количество (в ед. хранения)

    // У этого поставщика нет колонки со сроком поставки в файле —
    // фиксированный срок предзаказа по договорённости.
    const PREORDER_DAYS = 7;

    public function parseRow(array $row): ?array
    {
        $name = trim((string) ($row[0] ?? ''));

        if ($name === '' || mb_strtolower($name) === 'номенклатура') {
            return null;
        }

        // Убираем мусорные непечатаемые символы (например "¶") и лишние пробелы/кавычки из артикула
        $article = (string) ($row[1] ?? '');
        $article = preg_replace('/[^\p{L}\p{N}\-\.\/]+/u', '', $article);
        $article = trim($article, " \"'");

        $brand = trim((string) ($row[2] ?? ''));
        $price = $row[3] ?? null;
        $stock = $row[4] ?? null;

        if ($article === '' || $brand === '' || $price === null) {
            return null;
        }

        return [
            'sku'           => $article,
            'title'         => $name,
            'brand'         => $brand,
            'price'         => $price,
            'stock'         => $stock ?? 0,
            'preorder_days' => self::PREORDER_DAYS,
        ];
    }
}