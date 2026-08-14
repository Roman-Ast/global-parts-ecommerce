<?php

namespace App\Services;

use App\Http\Controllers\SparePartController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Живая цена/наличие для карточек parts_catalog — джойн на supplier_offers
 * по (article_normalized, brand_normalized), наценка через
 * SparePartController::setPrice() (тот же расчёт, что и в обычном поиске
 * сайта, учитывает роль пользователя common/opt/admin). Вынесено из
 * CatalogController, т.к. GlobalProductController теперь использует ту же
 * логику для /product/{brand}/{article} — дублировать 40-строчный матчинг
 * в двух контроллерах смысла нет.
 */
class SupplierOfferPricer
{
    /**
     * Мутирует каждую карточку на месте: добавляет ->offer — null, если
     * совпадений у поставщиков нет вовсе, иначе массив с retail_price,
     * stock, supplier_name, preorder_days.
     */
    public function attach(Collection $cards): Collection
    {
        if ($cards->isEmpty()) {
            return $cards;
        }

        $pricer = new SparePartController();

        $articles = $cards->pluck('article_normalized')->unique()->values();
        $brands = $cards->pluck('brand_normalized')->unique()->values();

        $offersByKey = DB::table('supplier_offers')
            ->select('sku_normalized', 'brand_normalized', 'purchase_price', 'stock', 'supplier_name', 'preorder_days')
            ->whereIn('sku_normalized', $articles)
            ->whereIn('brand_normalized', $brands)
            ->get()
            ->groupBy(fn ($offer) => $offer->sku_normalized . '|' . $offer->brand_normalized);

        foreach ($cards as $card) {
            $matches = $offersByKey->get($card->article_normalized . '|' . $card->brand_normalized);

            if (!$matches) {
                $card->offer = null;
                continue;
            }

            $inStock = $matches->where('stock', '>', 0)->sortBy('purchase_price')->first();
            $best = $inStock ?? $matches->sortBy('purchase_price')->first();

            $card->offer = [
                'purchase_price' => (float) $best->purchase_price,
                'retail_price' => (int) ceil($pricer->setPrice((float) $best->purchase_price)),
                'stock' => (int) $best->stock,
                'supplier_name' => $best->supplier_name,
                'preorder_days' => (int) $best->preorder_days,
            ];
        }

        return $cards;
    }
}
