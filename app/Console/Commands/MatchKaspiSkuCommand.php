<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\KaspiPriceCalculator;

class MatchKaspiSkuCommand extends Command
{
    protected $signature   = 'kaspi:match {--supplier=} {--dry-run}';
    protected $description = 'Сопоставляет прайсы поставщиков с SKU каспи и пишет в kaspi_feed_items';

    /**
     * Категории, где purchase_price у поставщика — это цена за ВСЮ закупку
     * (упаковку/коробку), а kaspi_qty карточки на себестоимость не влияет.
     * Должно быть синхронизировано со списком в RepriceKaspiCommand /
     * SyncKaspiFeedCommand — если меняешь список там, поменяй и здесь.
     */
    const FIXED_COST_KEYWORDS = [
        'колодки',
        'Колодок',
        'ремень',
    ];

    public function handle(): int
    {
        $supplier = $this->option('supplier');
        $dryRun   = $this->option('dry-run');

        $query = DB::table('kaspi_initial_products as kip')
            ->join('kaspi_sku_test as kst', 'kst.request_article', '=', 'kip.sku')
            ->where('kip.stock', '>=', 2)
            ->where('kst.sku', '!=', 'NOT_FOUND')
            ->whereNotNull('kip.brand')
            ->where('kip.brand', '!=', '')
            ->whereRaw('LOWER(kst.name) LIKE CONCAT("%", LOWER(kip.brand), "%")')
            ->whereRaw("kst.name REGEXP CONCAT('(^|[^a-zA-Z0-9])', kst.request_article, '([^a-zA-Z0-9]|$)')")
            ->select([
                'kip.sku as article',
                'kip.brand',
                'kip.supplier_name',
                'kip.price',
                'kip.purchase_price',
                'kip.stock',
                'kip.preorder_days',
                'kst.sku as kaspi_sku',
                'kst.name as kaspi_name',
                'kst.competitors_min_price',
                'kst.competitors_tomorrow_count',
                'kst.competitors_total',
                'kst.competitors_parsed_at',
                'kst.kaspi_qty',
                'kst.qty_suspicious',
            ]);

        if ($supplier) {
            $query->where('kip.supplier_name', $supplier);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Нет данных для матчинга.');
            return 0;
        }

        $this->info("Найдено совпадений: {$rows->count()}");

        // Группируем по kaspi_sku — берём минимальную цену среди поставщиков
        $grouped = [];
        foreach ($rows as $row) {
            $sku = $row->kaspi_sku;

            if (!isset($grouped[$sku])) {
                $grouped[$sku] = $row;
                continue;
            }

            // Если у текущего поставщика цена ниже — заменяем
            if ($row->price < $grouped[$sku]->price) {
                $grouped[$sku] = $row;
            }
        }

        $this->info("Уникальных SKU после группировки: " . count($grouped));

        // === QTY-AWARE ПЕРЕСЧЁТ СТАРТОВОЙ ЦЕНЫ ===
        // kip.price считался в AggregateSupplierOffersCommand БЕЗ учёта kaspi_qty
        // (там kaspi_qty ещё не существует — это атрибут карточки Kaspi, появляется
        // только здесь, после сопоставления с kaspi_sku_test). Если оставить как есть,
        // новые позиции с kaspi_qty >= 2 заходят в фид с заниженной (single-unit) ценой,
        // а kaspi:reprice при первом пересчёте увидит "скачок" >30% и не применит верную
        // цену — позиция зависнет в price_review_needed и будет продаваться в минус,
        // пока кто-то не разберёт очередь руками (это и стало причиной инцидента 2026-06-20).
        $requalified = 0;
        foreach ($grouped as $sku => $row) {
            $qty = (int) ($row->kaspi_qty ?? 1);
            $qty = max($qty, 1);

            if ($qty === 1) {
                continue; // ничего не меняется, кост как был
            }

            $isFixedCost = $this->isFixedCost($row->kaspi_name);
            $cost        = $isFixedCost ? (float) $row->purchase_price : (float) $row->purchase_price * $qty;

            $newPrice = (float) KaspiPriceCalculator::calculate($cost);

            if (round($newPrice) !== round((float) $row->price)) {
                $grouped[$sku]->price = $newPrice;
                $requalified++;
            }
        }

        if ($requalified > 0) {
            $this->info("Пересчитана стартовая цена с учётом kaspi_qty: {$requalified} позиций");
        }

        if ($dryRun) {
            $this->table(
                ['article', 'brand', 'supplier', 'kaspi_sku', 'kaspi_name', 'price', 'stock', 'qty'],
                collect($grouped)->map(fn($r) => [
                    $r->article, $r->brand, $r->supplier_name,
                    $r->kaspi_sku, mb_strimwidth($r->kaspi_name, 0, 50, '…'),
                    $r->price, $r->stock, $r->kaspi_qty,
                ])->toArray()
            );
            $this->info('Dry-run — ничего не записано.');
            return 0;
        }

        $upserted = 0;
        $skipped  = 0;

        foreach ($grouped as $row) {
            try {
                DB::table('kaspi_feed_items')->upsert(
                    [
                        'kaspi_sku'                  => $row->kaspi_sku,
                        'our_article'                => $row->article,
                        'kaspi_name'                 => $row->kaspi_name,
                        'brand'                      => $row->brand,
                        'price'                      => $row->price,
                        'purchase_price'             => $row->purchase_price,
                        'stock'                      => $row->stock,
                        'preorder_days'              => $row->preorder_days,
                        'supplier_name'              => $row->supplier_name,
                        'competitors_min_price'      => $row->competitors_min_price,
                        'competitors_tomorrow_count' => $row->competitors_tomorrow_count,
                        'competitors_total'          => $row->competitors_total,
                        'competitors_parsed_at'      => $row->competitors_parsed_at,
                        'kaspi_qty'                   => $row->kaspi_qty,
                        'qty_suspicious'              => $row->qty_suspicious,
                        'is_active'                  => true,
                        'last_synced_at'             => now(),
                        'updated_at'                 => now(),
                        'created_at'                 => now(),
                    ],
                    ['kaspi_sku'],  // уникальный ключ — один SKU один раз
                    [
                        'our_article', 'kaspi_name', 'brand', 'purchase_price', 'price', 'stock',
                        'preorder_days', 'supplier_name',
                        'competitors_min_price', 'competitors_tomorrow_count',
                        'competitors_total', 'competitors_parsed_at',
                        'kaspi_qty', 'qty_suspicious',
                        'is_active', 'last_synced_at', 'updated_at',
                    ]
                );
                $upserted++;
            } catch (\Exception $e) {
                $this->warn("Ошибка: " . $e->getMessage() . " | SKU: " . ($row->kaspi_sku ?? 'unknown'));
                Log::error('kaspi:match upsert error', [
                    'kaspi_sku' => $row->kaspi_sku ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info("Записано/обновлено: {$upserted}");
        if ($skipped > 0) {
            $this->warn("Пропущено с ошибкой: {$skipped} (смотри логи)");
        }

        return 0;
    }

    private function isFixedCost(string $title): bool
    {
        $titleLower = mb_strtolower($title);
        foreach (self::FIXED_COST_KEYWORDS as $keyword) {
            if (str_contains($titleLower, mb_strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }
}