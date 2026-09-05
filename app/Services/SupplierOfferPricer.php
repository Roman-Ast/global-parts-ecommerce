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
     * Заголовок карточки со скрейпинга Kaspi иногда сам говорит "нужно
     * НЕСКОЛЬКО штук на 1 применение" (напр. "Комплект тормозных дисков
     * (передние 2 шт)") — а supplier_offers.purchase_price при этом почти
     * всегда цена за ОДНУ штуку. Без этой поправки розница считалась от
     * цены за 1 шт, хотя реально нужно закупить 2 — живой случай
     * 2026-09-05: заказ №2018, F136W, диск Gerat реально 36400 за штуку,
     * а в заказ попало ~18928 (по сути цена за половину комплекта).
     *
     * НЕ путать с SUPPLIER_FIXED_COST_KEYWORDS/PAIR_ALREADY_BUNDLED в
     * Kaspi-пайплайне (RepriceKaspiCommand и т.д.) — те решают другую
     * задачу (не доверять полю kaspi_qty у конкретных категорий/брендов),
     * не про явный текстовый маркер "N шт" в названии. Роман подтвердил
     * 2026-09-05: у АвтоТрейда парами продаются только пружины, все
     * остальные категории (включая диски) считаются как обычно — поэтому
     * тут сознательно НЕТ поставщик-специфичных исключений, маркер "N шт"
     * в названии умножает себестоимость одинаково для всех поставщиков.
     */
    const QTY_MARKER_PATTERN = '/\b(\d+)\s*шт\b/ui';

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

            $qty = $this->detectKitQty($card->name ?? '');
            $unitCost = (float) $best->purchase_price;
            $totalCost = $unitCost * $qty;

            $card->offer = [
                'purchase_price' => $totalCost,
                'retail_price' => (int) ceil($pricer->setPrice($totalCost)),
                'stock' => (int) $best->stock,
                'supplier_name' => $best->supplier_name,
                'preorder_days' => (int) $best->preorder_days,
                'kit_qty' => $qty,
            ];
        }

        return $cards;
    }

    /**
     * Возвращает реальное число единиц, которое нужно закупить под эту
     * карточку — 1, если явного маркера количества нет в названии.
     */
    private function detectKitQty(string $name): int
    {
        if (!preg_match(self::QTY_MARKER_PATTERN, $name, $m)) {
            return 1;
        }

        $qty = (int) $m[1];

        return $qty >= 2 ? $qty : 1;
    }
}
