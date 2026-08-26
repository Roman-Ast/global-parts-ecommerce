<?php

namespace App;

class SetPrice
{
    // Коэффициенты подняты на +4% равномерно (2026-08-26, по просьбе
    // Романа) — цель: сдвинуть чистую маржу по не-Kaspi каналам (сайт/
    // 2GIS/повторные/сарафан) примерно с 38,5% к ~40%, чтобы вместе с
    // унификацией ролей (см. SparePartController::setPrice() и
    // SparePartControllerTest::setPrice() — теперь везде эта же шкала,
    // без скидки -7% для opt) блендовая маржа по всем каналам поднялась
    // с 35,5% к целевым 37,5-38%. Kaspi считается отдельно
    // (KaspiPriceCalculator) — сознательно НЕ трогали в этом же заходе,
    // Роман попросил заняться им отдельно вместе с репрайсингом.
    public static function setPriceForAdmin($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.784;
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.732;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 2.260;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.892;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.682;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.555;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.503;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.482;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.472;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.451;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.430;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.408;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.398;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.387;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.377;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.367;
        }

        return ceil($priceWithMargin);
    }
}