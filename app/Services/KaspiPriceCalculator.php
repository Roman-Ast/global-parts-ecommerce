<?php

namespace App\Services;

class KaspiPriceCalculator
{
    /**
     * Высчитывает финальную цену для Kaspi с учетом твоей маржи,
     * средней логистики (1700 тг) и комиссии Kaspi 12.5%
     */
    public static function calculate($price)
    {
        if ($price <= 0) {
            return 0;
        }

        // 1. Определяем ТВОЙ чистый процент наценки на закуп (исходя из твоих админ-цен)
        $marginPercent = 0;

        if ($price > 0 && $price <= 900) {
            $marginPercent = 2.50; // Наценка 250%
        } else if ($price > 900 && $price <= 3000) {
            $marginPercent = 1.50; // Наценка 150%
        } else if ($price > 3000 && $price <= 6000) {
            $marginPercent = 1.10; // Наценка 110%
        } else if ($price > 6000 && $price <= 10000) {
            $marginPercent = 0.75; // Наценка 75% (как раз при 10к дает 7500 прибыли)
        } else if ($price > 10000 && $price <= 15000) {
            $marginPercent = 0.55; // Наценка 55%
        } else if ($price > 15000 && $price <= 20000) {
            $marginPercent = 0.45; // Наценка 45%
        } else if ($price > 20000 && $price <= 30000) {
            $marginPercent = 0.40; // Наценка 40%
        } else if ($price > 30000 && $price <= 40000) {
            $marginPercent = 0.38; // Наценка 38%
        } else if ($price > 40000 && $price <= 50000) {
            $marginPercent = 0.36; // Наценка 36%
        } else if ($price > 50000 && $price <= 60000) {
            $marginPercent = 0.34; // Наценка 34%
        } else if ($price > 60000 && $price <= 70000) {
            $marginPercent = 0.32; // Наценка 32%
        } else if ($price > 70000) {
            $marginPercent = 0.30; // Наценка 30%
        }

        // Твоя чистая прибыль, которую ты закладываешь
        $desiredProfit = $price * $marginPercent;

        // Средние расходы на логистику (1450 доставка + 250 упаковка)
        $logisticsCost = 1700;

        // Сумма, которая должна остаться после вычета комиссии Kaspi (87.5% от цены на витрине)
        $moneyNeededBeforeKaspi = $price + $desiredProfit + $logisticsCost;

        // Финальная цена на Kaspi с защитой от ухода комиссии в минус (деление на 0.875)
        $kaspiPrice = $moneyNeededBeforeKaspi / 0.875;

        // Округляем до целых тенге, чтобы цена была красивой для покупателя
        return ceil($kaspiPrice);
    }
}