<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KaspiFeedCleanupCommand extends Command
{
    protected $signature = 'kaspi:feed-cleanup {--dry-run}';

    protected $description = 'Чистит kaspi_feed_items против актуального kaspi_initial_products: '
        . 'деактивирует осиротевшие позиции (поставщик больше не присылает артикул) '
        . 'и обновляет разошедшиеся данные (brand/preorder_days/stock/purchase_price). '
        . 'Не путать с kaspi:sync-feed (та ищет более дешёвые источники и понижает цены).';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // ── 1. Осиротевшие: our_article+supplier_name больше нет в kaspi_initial_products ──
        // Такие позиции деактивируем — доверять их цене/остатку/preorder больше нельзя,
        // поставщик мог их снять с продажи или просто не прислал в последнем прайсе.
        $orphaned = DB::table('kaspi_feed_items as kfi')
            ->leftJoin('kaspi_initial_products as kip', function ($join) {
                $join->on('kip.sku', '=', 'kfi.our_article')
                     ->on('kip.supplier_name', '=', 'kfi.supplier_name');
            })
            ->whereNull('kip.sku')
            ->where('kfi.is_active', 1);

        $orphanedCount = $orphaned->count();

        if ($dryRun) {
            $this->warn("[dry-run] Будет деактивировано осиротевших позиций: {$orphanedCount}");
        } else {
            $deactivated = DB::table('kaspi_feed_items as kfi')
                ->leftJoin('kaspi_initial_products as kip', function ($join) {
                    $join->on('kip.sku', '=', 'kfi.our_article')
                         ->on('kip.supplier_name', '=', 'kfi.supplier_name');
                })
                ->whereNull('kip.sku')
                ->where('kfi.is_active', 1)
                ->update(['kfi.is_active' => 0]);
            $this->info("Деактивировано осиротевших позиций: {$deactivated}");
        }

        // ── 2. Разошедшиеся данные: артикул у поставщика жив, но brand/preorder/stock/цена отличаются ──
        $drifted = DB::table('kaspi_feed_items as kfi')
            ->join('kaspi_initial_products as kip', function ($join) {
                $join->on('kip.sku', '=', 'kfi.our_article')
                     ->on('kip.supplier_name', '=', 'kfi.supplier_name');
            })
            ->where(function ($q) {
                $q->whereColumn('kfi.brand', '!=', 'kip.brand')
                  ->orWhereColumn('kfi.preorder_days', '!=', 'kip.preorder_days')
                  ->orWhereColumn('kfi.stock', '!=', 'kip.stock')
                  ->orWhereColumn('kfi.purchase_price', '!=', 'kip.purchase_price');
            });

        $driftedCount = (clone $drifted)->count();

        if ($dryRun) {
            $this->warn("[dry-run] Будет синхронизировано разошедшихся позиций: {$driftedCount}");
        } else {
            $synced = DB::table('kaspi_feed_items as kfi')
                ->join('kaspi_initial_products as kip', function ($join) {
                    $join->on('kip.sku', '=', 'kfi.our_article')
                         ->on('kip.supplier_name', '=', 'kfi.supplier_name');
                })
                ->where(function ($q) {
                    $q->whereColumn('kfi.brand', '!=', 'kip.brand')
                      ->orWhereColumn('kfi.preorder_days', '!=', 'kip.preorder_days')
                      ->orWhereColumn('kfi.stock', '!=', 'kip.stock')
                      ->orWhereColumn('kfi.purchase_price', '!=', 'kip.purchase_price');
                })
                ->update([
                    'kfi.brand' => DB::raw('kip.brand'),
                    'kfi.preorder_days' => DB::raw('kip.preorder_days'),
                    'kfi.stock' => DB::raw('kip.stock'),
                    'kfi.purchase_price' => DB::raw('kip.purchase_price'),
                    'kfi.updated_at' => now(),
                ]);
            $this->info("Синхронизировано разошедшихся позиций: {$synced}");
        }

        return self::SUCCESS;
    }
}