<?php

namespace App\Services\PriceParsers;

interface ParserInterface
{
    /**
     * Обрабатывает сырую строку из Excel и приводит её к единому стандарту.
     * Если строка невалидная (нет артикула и т.д.), возвращает null.
     */
    public function parseRow(array $row): ?array;
}