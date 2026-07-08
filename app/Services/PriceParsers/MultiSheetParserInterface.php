<?php

namespace App\Services\PriceParsers;

/**
 * Опциональный интерфейс. Обычные однолистовые парсеры (Rossko, Shatem и т.д.)
 * его не реализуют — раннер для них работает как раньше, без изменений.
 * Реализуют только те парсеры, где один файл = несколько разных "поставщиков"
 * (разные supplier_name на разные листы одной книги).
 */
interface MultiSheetParserInterface
{
    /**
     * Список листов Excel, которые нужно прочитать (в заданном порядке).
     * Остальные листы книги раннер должен полностью проигнорировать.
     */
    public function getAllowedSheets(): array;

    /**
     * supplier_name для конкретного листа — раннер должен использовать
     * этот supplier_name вместо того, что пришёл по умолчанию (из письма/конфига).
     */
    public function resolveSupplierName(string $sheetName): string;

    /**
     * Номер строки (0-индексация), с которой начинаются данные на листе
     * (шапка у Interkom не с первой строки, в отличие от однолистовых прайсов).
     */
    public function getDataStartRow(): int;
}