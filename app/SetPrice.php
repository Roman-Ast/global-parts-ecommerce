<?php

namespace App;

class SetPrice
{
    public static function setPriceForAdmin($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.638;
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.627;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 2.173;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.819;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.617;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.495;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.445;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.425;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.415;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.395;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.375;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.354;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.344;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.334;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.324;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.314;
        }

        return ceil($priceWithMargin);
    }
}