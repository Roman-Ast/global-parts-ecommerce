<?php

namespace App\Services;

use App\Console\Commands\Ozon\OzonCommissionRates;

/**
 * По образцу KaspiPriceCalculator — та же прогрессивная наценка на
 * себестоимость (закуп в KZT), но вместо тарифов Kaspi Доставки —
 * реальная комиссия Ozon FBS.
 *
 * ПЕРЕПИСАНО 2026-09-16 под договор ОМК (ТОО «Озон Маркетплейс
 * Казахстан») — до этого дня работали по старому/российскому договору
 * (ООО «Интернет Решения», 47% комиссия, цены в RUB). После переноса
 * карточек в новый кабинет живой запрос (/v3/product/info/list::
 * commissions[], реальная перенесённая карточка) показал: комиссия
 * **12% FBS**, валюта **нативно KZT** — конвертация по курсу KZT→RUB
 * (раньше EXCHANGE_RATE_KZT_TO_RUB) больше не нужна вообще, убрана.
 *
 * Логистика — раньше плоские 25 RUB (эта цифра относилась именно к
 * старому договору, не переносится на новый). По новому договору
 * логистика FBS — тарифная сетка в тенге по ЦЕНЕ+ОБЪЁМУ товара (страница
 * "FBS: расходы на доставку до покупателя" из базы знаний Ozon,
 * скриншот/текст от Романа 2026-09-16, действует с 2025-09-05) — см.
 * fbsLogisticsFeeKzt() ниже. Объём берём из тех же дефолтов, что уже
 * используются при создании карточки (OzonCreateCardCommand::
 * DEFAULT_WIDTH_MM/HEIGHT_MM/DEPTH_MM = 150×150×150мм = 3.375л) — у нас
 * по-прежнему 0% покрытия реальных габаритов в parts_catalog (та же
 * ситуация, что и на Halyk), другого источника объёма нет.
 */
class OzonPriceCalculator
{
    /**
     * Объём "среднего мелкого автозапчастья" по умолчанию, литры —
     * 150×150×150мм (см. OzonCreateCardCommand::DEFAULT_*_MM), тот же
     * дефолт, что уже используется при создании карточки, чтобы цена и
     * реально заявленные габариты не расходились.
     */
    const DEFAULT_VOLUME_LITERS = 3.375;

    /**
     * Тарифная сетка FBS-логистики Ozon в тенге, по цене товара (с учётом
     * скидок) и объёму — действует с 2025-09-05 (официальная база знаний
     * Ozon, страница "FBS: расходы на доставку до покупателя", прислано
     * Романом 2026-09-16). Три ценовых диапазона, внутри — по объёму.
     */
    private static function fbsLogisticsFeeKzt(float $priceKzt, float $volumeLiters): int
    {
        if ($priceKzt <= 5000) {
            return match (true) {
                $volumeLiters <= 0.4 => 212,
                $volumeLiters <= 1 => 246,
                $volumeLiters <= 2 => 299,
                $volumeLiters <= 5 => 418,
                $volumeLiters <= 10 => 730,
                default => 1335,
            };
        }

        if ($priceKzt <= 15000) {
            return 699;
        }

        return match (true) {
            $volumeLiters <= 1 => 750,
            $volumeLiters <= 5 => 800,
            $volumeLiters <= 50 => 1000,
            $volumeLiters <= 150 => 1700,
            default => 3050,
        };
    }

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

        $commissionFraction = OzonCommissionRates::DEFAULT_COMMISSION_PERCENT / 100;
        $feesDivisor = 1 - $commissionFraction;

        // Логистика зависит от итоговой цены (тарифная сетка по диапазону
        // цены) — а итоговая цена зависит от логистики. Даём один
        // предварительный проход без логистики, чтобы определить ценовой
        // диапазон, затем считаем итоговую цену уже с найденной сеткой.
        // Диапазоны широкие (5000/15000₸), сама логистика — не больше
        // нескольких процентов от цены на типовой запчасти, ошибка на
        // границе диапазона пренебрежимо мала.
        $roughPriceKzt = ($costKzt + $desiredProfitKzt) / $feesDivisor;
        $logisticsFeeKzt = self::fbsLogisticsFeeKzt($roughPriceKzt, self::DEFAULT_VOLUME_LITERS);

        $priceKzt = ($costKzt + $desiredProfitKzt + $logisticsFeeKzt) / $feesDivisor;

        return (int) ceil($priceKzt);
    }
}
