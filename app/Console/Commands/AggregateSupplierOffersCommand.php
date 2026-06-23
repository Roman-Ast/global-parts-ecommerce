<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\KaspiPriceCalculator;

class AggregateSupplierOffersCommand extends Command
{
    protected $signature = 'offers:aggregate';
    protected $description = 'Синхронизирует kaspi_initial_products с лучшими (минимальными по цене среди прошедших фильтр по остатку) предложениями из supplier_offers. Позиции avtozakup не трогаются.';

    const MIN_STOCK = 2;
    const MIN_PRICE = 10000;
    const PROTECTED_SUPPLIER = 'avtozakup';

    public function handle(): int
    {
        $this->info('Выбираем лучшие предложения по каждому SKU...');

        $bestOffers = DB::select("
            SELECT so.sku, so.supplier_name, so.title, so.brand, so.purchase_price, so.stock
            FROM supplier_offers so
            INNER JOIN (
                SELECT eligible.sku, MIN(eligible.id) AS pick_id
                FROM supplier_offers eligible
                INNER JOIN (
                    SELECT sku, MIN(purchase_price) AS min_price
                    FROM supplier_offers
                    WHERE stock >= ? AND purchase_price >= ? AND supplier_name != ?
                    GROUP BY sku
                ) best ON best.sku = eligible.sku AND best.min_price = eligible.purchase_price
                WHERE eligible.stock >= ? AND eligible.purchase_price >= ? AND eligible.supplier_name != ?
                GROUP BY eligible.sku
            ) pick ON pick.pick_id = so.id
        ", [self::MIN_STOCK, self::MIN_PRICE, self::PROTECTED_SUPPLIER, self::MIN_STOCK, self::MIN_PRICE, self::PROTECTED_SUPPLIER]);

        $this->info('Прошедших фильтр SKU: ' . count($bestOffers));

        $newCount          = 0;
        $updatedCount      = 0;
        $skippedProtected  = 0;
        $activeSkus        = [];

        foreach ($bestOffers as $offer) {
            $existProduct = DB::table('kaspi_initial_products')
                ->where('sku', $offer->sku)
                ->first();

            // avtozakup — защищённые заказные позиции, не трогаем вообще
            if ($existProduct && $existProduct->supplier_name === self::PROTECTED_SUPPLIER) {
                $activeSkus[] = $offer->sku;
                $skippedProtected++;
                continue;
            }

            $activeSkus[] = $offer->sku;

            $purchasePrice = (float) $offer->purchase_price;
            $qty           = (int) $offer->stock;
            $retailPrice   = (float) KaspiPriceCalculator::calculate($purchasePrice);

            if ($existProduct) {
                DB::table('kaspi_initial_products')
                    ->where('id', $existProduct->id)
                    ->update([
                        'title'          => $offer->title,
                        'brand'          => mb_strtolower($offer->brand),
                        'purchase_price' => $purchasePrice,
                        'price'          => $retailPrice,
                        'stock'          => $qty,
                        'supplier_name'  => $offer->supplier_name,
                        'preorder_days'  => 0,
                        'updated_at'     => now(),
                    ]);
                $updatedCount++;
            } else {
                DB::table('kaspi_initial_products')->insert([
                    'sku'            => $offer->sku,
                    'title'          => $offer->title,
                    'brand'          => mb_strtolower($offer->brand),
                    'category_code'  => null,
                    'purchase_price' => $purchasePrice,
                    'price'          => $retailPrice,
                    'stock'          => $qty,
                    'supplier_name'  => $offer->supplier_name,
                    'preorder_days'  => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $newCount++;
            }
        }

        $this->info("Добавлено:  {$newCount}");
        $this->info("Обновлено:  {$updatedCount}");
        $this->info("Защищено (avtozakup, не тронуто): {$skippedProtected}");

        $this->removeStaleProducts($activeSkus);

        $this->info('Готово.');
        return 0;
    }

    private function removeStaleProducts(array $activeSkus): void
    {
        if (empty($activeSkus)) {
            $this->warn('  ⚠ Нет ни одной активной позиции — удаление пропущено (защита от пустого результата).');
            return;
        }

        $staleSkus = DB::table('kaspi_initial_products')
            ->where('supplier_name', '!=', self::PROTECTED_SUPPLIER)
            ->whereNotIn('sku', $activeSkus)
            ->pluck('sku');

        if ($staleSkus->isEmpty()) {
            $this->comment('  Исчезнувших позиций: 0');
            return;
        }

        $this->comment('  Исчезнувших позиций: ' . $staleSkus->count());

        $deactivated = DB::table('kaspi_feed_items')
            ->whereIn('our_article', $staleSkus)
            ->update([
                'is_active'  => 0,
                'stock'      => 0,
                'updated_at' => now(),
            ]);

        if ($deactivated > 0) {
            $this->comment("  Деактивировано в kaspi_feed_items: {$deactivated}");
        }

        $deleted = DB::table('kaspi_initial_products')
            ->whereIn('sku', $staleSkus)
            ->delete();

        $this->comment("  Удалено из kaspi_initial_products: {$deleted}");
    }
}