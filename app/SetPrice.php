<?php

namespace App;

class SetPrice
{
    public static function setPriceForAdmin($price)
    {
        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.4;
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.35;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 2.0;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.64;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.49;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.45;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.39;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.40;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.37;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.35;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.30;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.275;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.255;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.24;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.235;
        }

        return ceil($priceWithMargin);
    }
}