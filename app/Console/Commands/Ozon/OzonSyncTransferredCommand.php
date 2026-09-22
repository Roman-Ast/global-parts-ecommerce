<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\OzonPriceCalculator;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Пересчитывает цену и пушит остаток для ВСЕХ карточек, перенесённых
 * 16.09.2026 с договора ООО «Интернет Решения» на договор ОМК (см.
 * CLAUDE.md, "Ozon — план экспансии"). Эти 1900 карточек НЕ в нашей
 * таблице `ozon_created_cards` — перенеслись через сам интерфейс Ozon,
 * не через наш пайплайн `ozon:create-card`. Источник списка — напрямую
 * `/v3/product/list` (постранично через last_id), не наша БД.
 *
 * У всех перенесённых карточек цена осталась старой — посчитанной ещё по
 * 47%-комиссии старого договора. Пересчитываем по OzonPriceCalculator
 * (сейчас — 12% FBS, нативно в тенге) и заодно пушим реальный остаток
 * (после переноса он у всех 0 — карточки "Готов к продаже / Нет на
 * складе", см. CLAUDE.md).
 */
class OzonSyncTransferredCommand extends Command
{
    protected $signature = 'ozon:sync-transferred {--limit=2000} {--dry-run}';

    protected $description = 'Пересчитывает цены и пушит остаток на карточки, перенесённые на договор ОМК';

    const BATCH_SIZE = 100;

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $warehouseId = (int) env('OZON_WAREHOUSE_ID');

        if (!$warehouseId) {
            $this->error('OZON_WAREHOUSE_ID не задан в .env');
            return 1;
        }

        $offerIds = $this->fetchAllOfferIds($limit);
        if (empty($offerIds)) {
            $this->info('Список товаров пуст.');
            return 0;
        }

        $this->info('Найдено ' . count($offerIds) . ' карточек на Ozon.');

        // offer_id = "{brand}-{article}" (см. resolveOfferId() в
        // OzonCreateCardCommand) — бренды в нашей базе без дефисов,
        // делим по ПЕРВОМУ дефису, остальное — артикул (артикулы
        // нередко сами содержат дефисы).
        $parsed = [];
        foreach ($offerIds as $offerId) {
            $pos = strpos($offerId, '-');
            if ($pos === false) {
                continue;
            }
            $parsed[$offerId] = [
                'brand'   => substr($offerId, 0, $pos),
                'article' => substr($offerId, $pos + 1),
            ];
        }

        $articles = array_values(array_unique(array_column($parsed, 'article')));
        $cards = PartsCatalog::query()->whereIn('article', $articles)->get();
        $cards = (new SupplierOfferPricer())->attach($cards);
        $byArticleBrand = $cards->keyBy(fn ($c) => $c->article . '|' . $c->brand);

        $priceUpdates = [];
        $stockUpdates = [];
        $noOffer = 0;

        foreach ($parsed as $offerId => $ids) {
            $card = $byArticleBrand->get($ids['article'] . '|' . $ids['brand']);
            $cost = $card->offer['purchase_price'] ?? null;
            $stock = $card->offer['stock'] ?? 0;

            if (!$cost || $stock <= 0) {
                $noOffer++;
                continue;
            }

            $price = OzonPriceCalculator::calculate((float) $cost);
            if ($price <= 0) {
                $noOffer++;
                continue;
            }

            $priceUpdates[] = ['offer_id' => $offerId, 'price' => $price];
            $stockUpdates[] = ['offer_id' => $offerId, 'stock' => (int) $stock];
        }

        $this->info(count($priceUpdates) . ' карточек с живым остатком у поставщика, ' . $noOffer . ' пропущено (нет остатка/цены).');

        if ($dryRun) {
            foreach (array_slice($priceUpdates, 0, 10) as $i => $p) {
                $this->line("  · {$p['offer_id']}: цена={$p['price']}, остаток={$stockUpdates[$i]['stock']}");
            }
            $this->info('dry-run — ничего не отправлено.');
            return 0;
        }

        $priceOk = 0;
        $priceFail = 0;
        foreach (array_chunk($priceUpdates, self::BATCH_SIZE) as $chunk) {
            try {
                $result = $client->updatePrices($chunk);
                $priceOk += $result['updated'];
                $priceFail += count($chunk) - $result['updated'];
                if (!empty($result['errors'])) {
                    foreach (array_slice($result['errors'], 0, 3) as $e) {
                        $this->line('  ⨯ цена ' . $e['offer_id'] . ': ' . json_encode($e['errors'], JSON_UNESCAPED_UNICODE));
                    }
                }
            } catch (\Throwable $e) {
                $priceFail += count($chunk);
                $this->error('  ⨯ пачка цен: ' . $e->getMessage());
            }
            $this->line("  цены: {$priceOk} ок, {$priceFail} ошибок...");
        }

        // Живьём 2026-09-17: первый прогон без паузы дал 219 ок / 1581
        // ошибок из 1800 — похоже на рейт-лимит (та же карточка,
        // проверенная СРАЗУ ПОСЛЕ прогона по отдельности, проходит без
        // проблем). usleep + логирование реальной причины ошибки (раньше
        // просто считали $stockFail++ без единого слова, почему).
        $stockOk = 0;
        $stockFail = 0;
        $errorSamples = [];
        foreach ($stockUpdates as $s) {
            try {
                $result = $client->updateStock($s['offer_id'], $s['stock'], $warehouseId);
                if ($result['updated']) {
                    $stockOk++;
                } else {
                    $stockFail++;
                    $code = $result['errors'][0]['code'] ?? 'unknown';
                    $errorSamples[$code] = ($errorSamples[$code] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $stockFail++;
                $errorSamples['exception'] = ($errorSamples['exception'] ?? 0) + 1;
            }
            usleep(300_000);
        }

        $this->info("Готово. Цены: {$priceOk} ок / {$priceFail} ошибок. Остатки: {$stockOk} ок / {$stockFail} ошибок.");
        if (!empty($errorSamples)) {
            $this->info('Причины ошибок остатков: ' . json_encode($errorSamples, JSON_UNESCAPED_UNICODE));
        }

        return 0;
    }

    /** @return array<int, string> offer_id */
    private function fetchAllOfferIds(int $limit): array
    {
        $offerIds = [];
        $lastId = '';

        while (count($offerIds) < $limit) {
            $response = Http::timeout(20)->withHeaders([
                'Client-Id'    => env('OZON_SELLER_ID'),
                'Api-Key'      => env('OZON_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api-seller.ozon.ru/v3/product/list', [
                'filter'  => (object) [],
                'limit'   => min(1000, $limit - count($offerIds)),
                'last_id' => $lastId,
            ]);

            if (!$response->successful()) {
                $this->error('product/list HTTP ' . $response->status());
                break;
            }

            $items = $response->json('result.items') ?? [];
            foreach ($items as $item) {
                $offerIds[] = $item['offer_id'];
            }

            $lastId = $response->json('result.last_id') ?? '';
            if (empty($items) || empty($lastId)) {
                break;
            }
        }

        return $offerIds;
    }
}
