<?php

namespace App;

class SetPrice
{
    public static function setPriceForAdmin($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.6;
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.6;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 2.15;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.8;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.6;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.48;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.43;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.41;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.40;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.38;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.36;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.34;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.32;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.31;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.30;
        }

        return ceil($priceWithMargin);
    }
}