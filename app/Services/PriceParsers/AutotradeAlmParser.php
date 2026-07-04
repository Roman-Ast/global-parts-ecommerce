<?php

namespace App\Services\PriceParsers;

/**
 * Парсер прайса АвтоТрейд Алматы.
 *
 * Файл: XLSX, лист "Прайс лист для клиентов", шапка "Артикул | ТМЦ | Бренд | Цена (KZT) | Осн Алматы".
 * Формат идентичен AutotradeAstParser (тот же поставщик, другой склад/город):
 *   0 Артикул
 *   1 ТМЦ (наименование)
 *   2 Бренд
 *   3 Цена (KZT)
 *   4 Остаток (число ИЛИ текст вида "Более 5")
 *
 * preorder_days = 10 — товар едет из Алматы в Астану не мгновенно,
 * в отличие от autotrade_ast (0 дней, локальный склад).
 */
class AutotradeAlmParser implements ParserInterface
{
    private const PREORDER_DAYS = 10;

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

        // "Более 5" -> 5 через ту же логику, что и остальные парсеры (preg_replace на цифры)
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
            'preorder_days'     => self::PREORDER_DAYS,
            'category_code'     => null,
            'description'       => null,
            'images'            => null,
            'attributes'        => null,
            'raw_cross_numbers' => null,
        ];
    }
}
