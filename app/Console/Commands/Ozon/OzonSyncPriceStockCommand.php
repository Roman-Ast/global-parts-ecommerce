<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\OzonPriceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ежедневный пайплайн Ozon (просьба Романа 2026-10-04) — по образцу
 * Kaspi-пайплайна (prices:fetch → offers:aggregate → ... → kaspi:reprice):
 * после скачивания свежих прайсов поставщиков эта команда пересчитывает
 * цену/остаток КАЖДОЙ активной Ozon-карточки строго от Rossko/Shatem (та
 * же причина, что и у всей чистки каталога 2026-10-04 — только эти два
 * поставщика физически в Астане и обновляются каждый день).
 *
 * НЕ создаёт новых карточек и НЕ трогает контент мёртвых слотов — это
 * отдельные одноразовые инструменты (ozon:fix-to-rossko-shatem,
 * ozon:replace-dead-cards). Если у активной карточки позиция совсем
 * пропала у Rossko/Shatem (раньше была,今 нет) — ничего не меняем
 * автоматически на Ozon (ни цену, ни остаток), просто печатаем её в
 * консоль, чтобы Роман вручную решил, чем заменить слот (той же командой
 * ozon:replace-dead-cards --article=... при необходимости, см. докблок).
 *
 * Запускать так же, как kaspi:reprice — после prices:fetch, параллельно
 * с остальным Kaspi-пайплайном, не вместо него.
 */
class OzonSyncPriceStockCommand extends Command
{
    protected $signature = 'ozon:sync-price-stock {--limit=5000} {--dry-run}';

    protected $description = 'Ежедневный синк цены/остатка активных Ozon-карточек строго от Rossko/Shatem';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    /** Та же защита, что и в ozon:fix-to-rossko-shatem — у Rossko встречаются нереальные значения stock (см. докблок там). */
    const MAX_STOCK_TO_PUSH = 20;

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('ozon_created_cards')
            ->whereIn('status', ['imported', 'replaced'])
            ->whereNotNull('parts_catalog_id')
            ->whereNotNull('offer_id')
            ->limit($limit)
            ->get(['id', 'article', 'brand', 'offer_id', 'parts_catalog_id']);

        if ($rows->isEmpty()) {
            $this->info('Нет активных карточек для синка.');
            return 0;
        }

        $catalogIds = $rows->pluck('parts_catalog_id')->unique();
        $cards = PartsCatalog::whereIn('id', $catalogIds)->get()->keyBy('id');

        $this->info("Синкаем {$rows->count()} карточек...");

        $priceUpdates = [];
        $stockUpdates = [];
        $vanished = [];

        foreach ($rows as $row) {
            $card = $cards->get($row->parts_catalog_id);
            if (!$card) {
                continue;
            }

            $offer = DB::table('supplier_offers')
                ->whereIn('supplier_name', self::ALLOWED_SUPPLIERS)
                ->where('sku_normalized', $card->article_normalized)
                ->where('brand_normalized', $card->brand_normalized)
                ->where('stock', '>', 0)
                ->orderBy('purchase_price')
                ->first();

            if (!$offer) {
                $vanished[] = "{$row->offer_id} ({$row->brand} {$row->article})";
                continue;
            }

            $qty = preg_match('/\b(\d+)\s*шт\b/ui', $card->name ?? '', $m) && (int) $m[1] >= 2 ? (int) $m[1] : 1;
            $cost = (float) $offer->purchase_price * $qty;
            $price = OzonPriceCalculator::calculate($cost);
            $stock = min((int) $offer->stock, self::MAX_STOCK_TO_PUSH);

            $priceUpdates[] = ['offer_id' => $row->offer_id, 'price' => $price];
            $stockUpdates[] = ['offer_id' => $row->offer_id, 'stock' => $stock];
        }

        if ($dryRun) {
            $this->info('--dry-run: собрано к обновлению ' . count($priceUpdates) . ' цен/остатков, ни одного реального вызова Ozon не сделано.');
            if (!empty($vanished)) {
                $this->warn('Пропавших у Rossko/Shatem (' . count($vanished) . '):');
                foreach ($vanished as $v) {
                    $this->line("  ⚠ {$v}");
                }
            }
            return 0;
        }

        // Цены — пачкой (до 1000 за раз, см. OzonClient::updatePrices).
        $priceOk = 0;
        foreach (array_chunk($priceUpdates, 1000) as $chunk) {
            try {
                $result = $client->updatePrices($chunk);
                $priceOk += $result['updated'];
            } catch (\Throwable $e) {
                $this->error('  ⨯ пачка цен упала: ' . $e->getMessage());
            }
        }

        // Остаток — только по одному (API Ozon не берёт пачку для /v2/products/stocks так же, как для цен).
        $stockOk = 0;
        $stockFailed = 0;
        foreach ($stockUpdates as $u) {
            try {
                $result = $client->updateStock($u['offer_id'], $u['stock']);
                if ($result['updated']) {
                    $stockOk++;
                } else {
                    $stockFailed++;
                }
            } catch (\Throwable $e) {
                $stockFailed++;
            }
        }

        $this->info("Готово: цен обновлено={$priceOk}/" . count($priceUpdates) . ", остатков обновлено={$stockOk}, ошибок остатка={$stockFailed}");

        if (!empty($vanished)) {
            $this->newLine();
            $this->warn('Позиции, которые ПОЛНОСТЬЮ пропали у Rossko/Shatem (' . count($vanished) . ') — цена/остаток НЕ тронуты, реши вручную:');
            foreach ($vanished as $v) {
                $this->line("  ⚠ {$v}");
            }
        }

        return 0;
    }
}
