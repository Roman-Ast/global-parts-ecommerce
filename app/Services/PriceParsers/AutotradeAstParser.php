<?php

namespace App\Services\PriceParsers;

/**
 * Парсер прайса АвтоТрейд Астана.
 *
 * Файл: XLSX, лист "Прайс лист для клиентов", шапка "Артикул | ТМЦ | Бренд | Цена (KZT) | <склад>".
 * Колонки (после SimpleXLSX::parse(), включая строку заголовка — фильтруем её ниже сами):
 *   0 Артикул
 *   1 ТМЦ (наименование)
 *   2 Бренд
 *   3 Цена (KZT)
 *   4 Остаток (число ИЛИ текст вида "более 5")
 *
 * Отдельный парсер под конкретный город/склад — файл выбирается вручную
 * и приходит с отдельной почты. Позже появится AutotradeAlmatyParser
 * с ключом поставщика autotrade_almaty по тому же формату.
 */
class AutotradeAstParser implements ParserInterface
{
    public function parseRow(array $row): ?array
    {
        // Пропускаем шапку и пустые/битые строки
        if (empty($row[0]) || empty($row[3]) || $row[0] === 'Артикул') {
            return null;
        }

        $rawArticle = trim((string)$row[0]);
        $rawName    = trim((string)($row[1] ?? ''));
        $brand      = trim((string)($row[2] ?? ''));
        $rawPrice   = $row[3] ?? '0';
        $rawQty     = $row[4] ?? '0';

        // "более 5" -> 5 через ту же логику, что и остальные парсеры (preg_replace на цифры)
        $qty   = (int) preg_replace('/[^0-9]/', '', (string)$rawQty);
        $price = (float) str_replace([' ', ','], ['', '.'], (string)$rawPrice);

        $cleanSku = preg_replace('/[^A-Za-z0-9]/', '', $rawArticle);
        if (empty($cleanSku)) {
            $cleanSku = $rawArticle;
        }

        return [
            'sku'               => $cleanSku,
            'brand'             => $brand,
            'title'             => $rawName,
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
