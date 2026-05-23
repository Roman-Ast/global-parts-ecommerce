<?php

namespace App\Services;

class KaspiPriceCalculator
{
    /**
     * Высчитывает финальную цену для Kaspi с учетом твоей чистой маржи,
     * расходов на логистику (1700 тг), госналога (3%) и комиссии Kaspi (12%)
     */
    public static function calculate($price)
    {
        if ($price <= 0) {
            return 0;
        }

        // 1. Применяем НОВЫЕ пропорциональные правила чистой маржи (как для сайта)
        $marginPercent = 0;

        if ($price > 0 && $price <= 900) {
            $marginPercent = 2.50; // Наценка 250%
        } else if ($price > 900 && $price <= 3000) {
            $marginPercent = 1.50; // Наценка 150%
        } else if ($price > 3000 && $price <= 6000) {
            $marginPercent = 1.10; // Наценка 110%
        } else if ($price > 6000 && $price <= 10000) {
            $marginPercent = 0.75; // Наценка 75%
        } else if ($price > 10000 && $price <= 15000) {
            $marginPercent = 0.55; // Наценка 55%
        } else if ($price > 15000 && $price <= 20000) {
            $marginPercent = 0.45; // Наценка 45%
        } else if ($price > 20000 && $price <= 30000) {
            $marginPercent = 0.40; // Наценка 40%
        } else if ($price > 30000 && $price <= 40000) {
            $marginPercent = 0.38; // Наценка 38%
        // --- СИНХРОНИЗАЦИЯ С НОВОЙ ВЕРХНЕЙ СЕТКОЙ ---
        } else if ($price > 40000 && $price <= 50000) {
            $marginPercent = 0.37;
        } else if ($price > 50000 && $price <= 60000) {
            $marginPercent = 0.35;
        } else if ($price > 60000 && $price <= 70000) {
            $marginPercent = 0.34;
        } else if ($price > 70000 && $price <= 80000) {
            $marginPercent = 0.32;
        } else if ($price > 80000 && $price <= 90000) {
            $marginPercent = 0.31; // Сработает для радиатора
        } else if ($price > 90000 && $price <= 100000) {
            $marginPercent = 0.30;
        } else if ($price > 100000 && $price <= 120000) {
            $marginPercent = 0.295;
        } else if ($price > 120000) {
            $marginPercent = 0.29; // Фиксация 29% маржи для самых дорогих позиций
        }

        // Твоя чистая прибыль (то то, что идёт тебе в карман)
        $desiredProfit = $price * $marginPercent;

        // Расходы на логистику (1450 тенге доставка Каспи + 250 тенге коробка/упаковка)
        $logisticsCost = 1700;

        // Сумма, необходимая до вычета процентов Каспи и налогов
        $moneyNeededBeforeFees = $price + $desiredProfit + $logisticsCost;

        // Расчёт витринной цены Каспи с учётом 12% комиссии и 3% налога (Итого 15% удержаний, делим на 0.85)
        $kaspiPrice = $moneyNeededBeforeFees / 0.85;

        // Округляем всегда строго в большую сторону до целого тенге
        return ceil($kaspiPrice);
    }
}