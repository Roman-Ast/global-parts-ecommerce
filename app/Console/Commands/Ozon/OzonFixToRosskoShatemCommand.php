<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\OzonPriceCalculator;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Разовая чистка Ozon-каталога (просьба Романа 2026-10-04) — живой случай:
 * заказ бампера ушёл с Автотрейд-Алматы (нет на складе физически у них,
 * клиент ждал бы неизвестно сколько), хотя на карточке победил именно их
 * оффер просто по цене (SupplierOfferPricer — глобальный "кто дешевле",
 * без учёта надёжности/региона). Решение — ограничить Ozon строго двумя
 * поставщиками: Rossko и Shatem, они обновляют прайсы каждый день и физически
 * в Астане (та же причина, по которой им уже доверяет Kaspi-пайплайн).
 *
 * Эта команда — ТОЛЬКО "дешёвый" кусок чистки: карточки, у которых прямо
 * сейчас выигрывает другой поставщик, но у Rossko/Shatem ЕСТЬ та же позиция
 * (просто раньше проигрывала по цене) — переключаем цену/остаток на них,
 * сама карточка (название/фото/категория) не трогается, слот не тратится.
 * Карточки, для которых у Rossko/Shatem вообще нет такой позиции — другая,
 * более тяжёлая задача (замена контента карточки целиком), см. соседнюю
 * ozon:replace-dead-cards.
 */
class OzonFixToRosskoShatemCommand extends Command
{
    protected $signature = 'ozon:fix-to-rossko-shatem {--limit=500} {--dry-run}';

    protected $description = 'Переключает цену/остаток у уже созданных карточек Ozon на Rossko/Shatem там, где они есть, но раньше проигрывали по цене';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    // Живая находка 2026-10-04: у Rossko встречаются откровенно нереальные
    // значения stock (напр. 10100 шт на наконечник рулевой тяги) — похоже на
    // артефакт их фида ("условно неограниченно"), не настоящий физический
    // остаток. Для Kaspi это не проблема (там есть anomaly detection в
    // RepriceKaspiCommand), но здесь отдельный путь — просто не передаём
    // Ozon ничего крупнее разумного потолка.
    const MAX_STOCK_TO_PUSH = 20;

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->whereNotNull('parts_catalog_id')
            ->get(['id', 'parts_catalog_id', 'article', 'brand', 'offer_id']);

        $catalogIds = $rows->pluck('parts_catalog_id')->unique();
        $cards = PartsCatalog::whereIn('id', $catalogIds)->get()->keyBy('id');

        // Текущий победитель (глобально, по всем поставщикам) — чтобы
        // пропустить карточки, которые УЖЕ и так держатся на rossko/shatem.
        $cardsWithGlobalOffer = (new SupplierOfferPricer())->attach(clone $cards->values());
        $alreadyGoodIds = $cardsWithGlobalOffer
            ->filter(fn ($c) => $c->offer && in_array($c->offer['supplier_name'], self::ALLOWED_SUPPLIERS, true))
            ->pluck('id')->all();

        $candidates = $rows->filter(fn ($r) => !in_array($r->parts_catalog_id, $alreadyGoodIds, true));

        $this->info("Карточек не на rossko/shatem: {$candidates->count()}");

        $fixed = 0;
        $noRsOffer = 0;
        $failed = 0;
        $processed = 0;

        foreach ($candidates as $row) {
            if ($processed >= $limit) {
                break;
            }

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
                $noRsOffer++;
                continue;
            }

            $processed++;

            $qty = preg_match('/\b(\d+)\s*шт\b/ui', $card->name ?? '', $m) && (int) $m[1] >= 2 ? (int) $m[1] : 1;
            $cost = (float) $offer->purchase_price * $qty;
            $price = OzonPriceCalculator::calculate($cost);
            // offer_id — явная колонка (миграция 2026_10_04_000001), не
            // вычисляем заново из article/brand: после возможной замены
            // контента слота (ozon:replace-dead-cards) article/brand этой
            // строки могут отличаться от того, что реально зарезервировано
            // под этим offer_id на Ozon.
            $offerId = $row->offer_id;
            $pushStock = min((int) $offer->stock, self::MAX_STOCK_TO_PUSH);

            if ($dryRun) {
                $this->line("  [dry-run] {$offerId}: {$offer->supplier_name}, закуп {$cost}, цена {$price}, остаток {$pushStock}" . ($offer->stock > self::MAX_STOCK_TO_PUSH ? " (реальный {$offer->stock}, срезано потолком)" : ''));
                $fixed++;
                continue;
            }

            try {
                $priceResult = $client->updatePrices([['offer_id' => $offerId, 'price' => $price]]);
                $stockResult = $client->updateStock($offerId, $pushStock);

                if ($priceResult['updated'] > 0 && $stockResult['updated']) {
                    $fixed++;
                    $this->line("  ✓ {$offerId}: {$offer->supplier_name}, цена {$price}, остаток {$pushStock}");
                } else {
                    $failed++;
                    $this->line("  ⨯ {$offerId}: price=" . json_encode($priceResult['errors']) . ' stock=' . json_encode($stockResult['errors']));
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ⨯ {$offerId}: {$e->getMessage()}");
            }
        }

        $this->info("Готово: исправлено={$fixed}, нет позиции у rossko/shatem={$noRsOffer}, ошибка={$failed}");

        return 0;
    }
}
