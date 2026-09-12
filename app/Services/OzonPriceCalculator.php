<?php

namespace App\Services;

use App\Console\Commands\Ozon\OzonCommissionRates;

/**
 * По образцу KaspiPriceCalculator — та же прогрессивная наценка на
 * себестоимость (закуп в KZT), но вместо тарифов Kaspi Доставки —
 * реальная комиссия Ozon FBS, подтверждённая живым запросом
 * /v3/product/info/list::commissions[] 2026-09-12 на 5 разных карточках
 * (амортизаторы, ступицы, тормозные детали) — везде percent=47,
 * delivery_amount=25 RUB одинаково. Роман сомневался в 47% как в
 * "нереальной" цифре ещё 2026-09-03 (см. докблок OzonCommissionRates) —
 * этим прогоном цифра подтверждена не как случайная заглушка на одной
 * карточке, а как стабильная ставка по категории.
 *
 * Курс KZT→RUB (OzonCommissionRates::EXCHANGE_RATE_KZT_TO_RUB) остаётся
 * заглушкой — не привязан к живому курсу ЦБ/биржи, при отклонении курса
 * маржа будет плавать; не критично для первого запуска, но стоит сверять
 * периодически.
 */
class OzonPriceCalculator
{
    /** RUB, за единицу отправления FBS — подтверждено живьём 2026-09-12, стабильно на всех проверенных категориях. */
    const FBS_DELIVERY_RUB = 25;

    /**
     * Наценка от себестоимости — 1:1 копия шкалы KaspiPriceCalculator
     * (по прямому решению Романа 2026-09-12: считать маржу для Ozon так
     * же, как для Kaspi, не дожидаясь ERP). Шкала работает от цены В
     * ТЕНГЕ (себестоимость), не от рублёвой цены.
     */
    private static function getMarginPercent(float $costKzt): float
    {
        if ($costKzt > 0 && $costKzt <= 900) {
            return 2.50;
        } elseif ($costKzt <= 3000) {
            return 1.50;
        } elseif ($costKzt <= 6000) {
            return 1.10;
        } elseif ($costKzt <= 10000) {
            return 0.75;
        } elseif ($costKzt <= 15000) {
            return 0.55;
        } elseif ($costKzt <= 20000) {
            return 0.45;
        } elseif ($costKzt <= 30000) {
            return 0.40;
        } elseif ($costKzt <= 40000) {
            return 0.38;
        } elseif ($costKzt <= 50000) {
            return 0.37;
        } elseif ($costKzt <= 120000) {
            return 0.35;
        }

        return 0.34;
    }

    /**
     * @param float $costKzt себестоимость (закуп) в тенге — НЕ обычная
     * розничная цена сайта (та уже несёт свою маржу под прямую продажу
     * без комиссии маркетплейса, использовать её базой для Ozon значило
     * бы наращивать маржу на маржу).
     */
    public static function calculate(float $costKzt): int
    {
        if ($costKzt <= 0) {
            return 0;
        }

        $marginPercent = self::getMarginPercent($costKzt);
        $desiredProfitKzt = $costKzt * $marginPercent;

        $rate = OzonCommissionRates::EXCHANGE_RATE_KZT_TO_RUB;
        $costRub = $costKzt * $rate;
        $profitRub = $desiredProfitKzt * $rate;

        $commissionFraction = OzonCommissionRates::DEFAULT_COMMISSION_PERCENT / 100;
        $feesDivisor = 1 - $commissionFraction;

        $moneyNeededBeforeFees = $costRub + $profitRub + self::FBS_DELIVERY_RUB;
        $priceRub = $moneyNeededBeforeFees / $feesDivisor;

        return (int) ceil($priceRub);
    }
}
