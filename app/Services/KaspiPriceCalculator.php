<?php

namespace App\Services;

class KaspiPriceCalculator
{
    // --- Комиссия/налог синхронизированы с константами RepriceKaspiCommand ---
    const COMMISSION = 0.125; // 12.5%
    const TAX        = 0.04;  // 4% (обновлено с 3%)
    const FEES_DIVISOR = 1 - self::COMMISSION - self::TAX; // 0.835

    const VAT_RATE = 0.12; // НДС на Тарифы Kaspi Доставки (п.4 договора — тарифы указаны без НДС)

    /**
     * Тарифы Kaspi Доставки (без НДС) для заказов ДЕШЕВЛЕ 10 000 тг —
     * здесь тариф зависит только от суммы заказа, вес не важен.
     * Колонка "По Казахстану" — из "Информация о стоимости услуг..." 2026.
     */
    const DELIVERY_TARIFFS_BY_ORDER_AMOUNT = [
        ['up_to' => 1000,  'tariff' => 49.14],
        ['up_to' => 3000,  'tariff' => 149.14],
        ['up_to' => 5000,  'tariff' => 199.14],
        ['up_to' => 10000, 'tariff' => 799.14],
    ];

    /**
     * Для заказов ОТ 10 000 тг тариф уже зависит от ВЕСА товара.
     * Веса в БД пока нет ни у одной позиции (нет поля weight ни в
     * kaspi_initial_products, ни в supplier_offers), поэтому временно
     * держим плоскую логистику — как было раньше.
     *
     * TODO: когда появится поле веса, заменить на выбор по
     * DELIVERY_TARIFFS_BY_WEIGHT (заготовка ниже, колонка "По Казахстану"):
     *   до 5кг    -> 1299.14
     *   5-15кг    -> 1699.14
     *   15-30кг   -> 3599.14
     *   30-60кг   -> 5649.14
     *   60-100кг  -> 8549.14
     *   свыше     -> 11999.14
     */
    const FALLBACK_LOGISTICS_FOR_10K_PLUS = 1700;

    public static function calculate($price)
    {
        if ($price <= 0) {
            return 0;
        }

        $marginPercent = self::getMarginPercent($price);
        $desiredProfit = $price * $marginPercent;

        // Тариф логистики зависит от ИТОГОВОЙ цены на Kaspi, а итоговая
        // цена зависит от тарифа логистики — считаем в несколько проходов,
        // пока тариф не перестанет меняться (обычно сходится за 1-2 шага,
        // т.к. границы тарифных вилок довольно широкие).
        $logisticsCost = self::FALLBACK_LOGISTICS_FOR_10K_PLUS; // стартовая прикидка
        $kaspiPrice = 0;

        for ($i = 0; $i < 3; $i++) {
            $moneyNeededBeforeFees = $price + $desiredProfit + $logisticsCost;
            $kaspiPrice = $moneyNeededBeforeFees / self::FEES_DIVISOR;

            $newLogisticsCost = self::getLogisticsCost($kaspiPrice);

            if (abs($newLogisticsCost - $logisticsCost) < 0.01) {
                break; // тариф стабилен, дальше пересчитывать бессмысленно
            }

            $logisticsCost = $newLogisticsCost;
        }

        return (int) ceil($kaspiPrice);
    }

    /**
     * Логистика (тенге, С НДС) по итоговой цене заказа на Kaspi.
     */
    private static function getLogisticsCost(float $orderPrice): float
    {
        if ($orderPrice >= 10000) {
            // Вес неизвестен — временная плоская заглушка (см. TODO выше).
            return self::FALLBACK_LOGISTICS_FOR_10K_PLUS;
        }

        foreach (self::DELIVERY_TARIFFS_BY_ORDER_AMOUNT as $tier) {
            if ($orderPrice <= $tier['up_to']) {
                return round($tier['tariff'] * (1 + self::VAT_RATE), 2);
            }
        }

        // Не должно случиться (последний tier — 10000), защитный фолбэк:
        return self::FALLBACK_LOGISTICS_FOR_10K_PLUS;
    }

    /**
     * Наценка от себестоимости (закупки). Прогрессивная шкала без изменений
     * по сути, вынесена в отдельный метод для читаемости.
     */
    private static function getMarginPercent(float $price): float
    {
        if ($price > 0 && $price <= 900) {
            return 2.50;
        } elseif ($price <= 3000) {
            return 1.50;
        } elseif ($price <= 6000) {
            return 1.10;
        } elseif ($price <= 10000) {
            return 0.75;
        } elseif ($price <= 15000) {
            return 0.55;
        } elseif ($price <= 20000) {
            return 0.45;
        } elseif ($price <= 30000) {
            return 0.40;
        } elseif ($price <= 40000) {
            return 0.38;
        } elseif ($price <= 50000) {
            return 0.37;
        } elseif ($price <= 60000) {
            return 0.35;
        } elseif ($price <= 70000) {
            return 0.34;
        } elseif ($price <= 80000) {
            return 0.32;
        } elseif ($price <= 90000) {
            return 0.31;
        } elseif ($price <= 100000) {
            return 0.30;
        } elseif ($price <= 120000) {
            return 0.295;
        }

        return 0.29;
    }
}
