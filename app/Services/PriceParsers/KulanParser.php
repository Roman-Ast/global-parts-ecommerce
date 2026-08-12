<?php

namespace App\Services\PriceParsers;

class KulanParser
{
    // Индексы колонок в исходном файле (0-based, после снятия первой строки в readRows()):
    // 0 - Бренд
    // 1 - Код товара (внутренний, не используется)
    // 2 - Артикул
    // 3 - Наименование
    // 4 - Штрихкод
    // 5 - Применяемость
    // 6 - Цена, KZT
    // 7 - Мин. партия
    // 8 - Алматы Рахат РЦ(NEW)
    // 9 - Нур-Султан РЦ(NEW)  <-- используем как остаток (локальный склад)

    public function parseRow(array $row): ?array
    {
        // Пропускаем строки-заголовки/мусор в начале файла
        // (в исходнике первые ~9 строк — ссылка на кабинет, дата, пустые строки,
        // плюс сама строка с названиями колонок "Бренд", "Код товара" и т.д.)
        $brand = trim((string) ($row[0] ?? ''));

        if ($brand === '' || mb_strtolower($brand) === 'бренд') {
            return null;
        }

        $article = trim((string) ($row[2] ?? ''));
        $name    = trim((string) ($row[3] ?? ''));
        $price   = $row[6] ?? null;
        $stock   = $row[9] ?? null; // Нур-Султан РЦ(NEW)

        if ($article === '' || $name === '' || $price === null) {
            return null;
        }

        return [
            'sku'           => $article,
            'title'         => $name,
            'brand'         => $brand,
            'price'         => $price,
            'stock'         => $stock ?? 0,
            'preorder_days' => 0,
        ];
    }
}