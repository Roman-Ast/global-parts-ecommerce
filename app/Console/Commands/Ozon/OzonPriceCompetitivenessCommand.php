<?php

namespace App\Console\Commands\Ozon;

use App\Console\Commands\Ozon\OzonCommissionRates;
use App\Services\OzonPriceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Эксперимент (по просьбе Романа 2026-09-15, после разбора "Индекс цен:
 * 93% Невыгодный" в кабинете Ozon): для выборки уже созданных карточек
 * тянем реальную минимальную цену конкурента прямо по нашему SKU
 * (`/v3/product/info/list::price_indexes.ozon_index_data.minimal_price` —
 * это тот же источник, что рисует колонку "Цены конкурентов" в кабинете,
 * сверено вживую на LYNXauto-CD1246: 14267₽ в обоих местах совпадает) и
 * считаем: если бы мы встали на уровень этой цены, остались бы в плюсе
 * после комиссии Ozon (47%, см. OzonCommissionRates) при нашей реальной
 * себестоимости — или это была бы продажа в убыток.
 *
 * ТОЛЬКО ЧТЕНИЕ — ничего не пишет ни в БД, ни на Ozon. Задача — понять
 * масштаб проблемы, прежде чем что-то менять в стратегии цен.
 */
class OzonPriceCompetitivenessCommand extends Command
{
    protected $signature = 'ozon:price-competitiveness {--limit=300}';

    protected $description = 'Эксперимент: сравнивает нашу цену на Ozon с ценой конкурента и считает, останемся ли в плюсе, если встанем вровень';

    const BATCH_SIZE = 100;

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $cards = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->limit($limit)
            ->get(['article', 'brand']);

        if ($cards->isEmpty()) {
            $this->info('Нет карточек в статусе imported.');
            return 0;
        }

        // Себестоимость берём напрямую из supplier_offers (текущая, не на
        // момент создания карточки) — тот же источник, что и
        // OzonPriceCalculator.
        $costs = DB::table('supplier_offers')
            ->whereIn('sku_normalized', $cards->pluck('article'))
            ->get(['sku_normalized', 'brand_normalized', 'purchase_price'])
            ->keyBy(fn ($r) => $r->sku_normalized . '|' . $r->brand_normalized);

        $rate = OzonCommissionRates::EXCHANGE_RATE_KZT_TO_RUB;
        $commissionFraction = OzonCommissionRates::DEFAULT_COMMISSION_PERCENT / 100;

        $results = [];

        foreach ($cards->chunk(self::BATCH_SIZE) as $chunk) {
            $offerIds = $chunk->map(fn ($c) => mb_substr("{$c->brand}-{$c->article}", 0, 50))->all();

            $response = Http::timeout(20)->withHeaders([
                'Client-Id' => env('OZON_SELLER_ID'),
                'Api-Key' => env('OZON_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api-seller.ozon.ru/v3/product/info/list', ['offer_id' => $offerIds]);

            if (!$response->successful()) {
                $this->error('HTTP ' . $response->status() . ' — ' . $response->body());
                continue;
            }

            foreach ($response->json('items') ?? [] as $item) {
                $offerId = $item['offer_id'] ?? '';
                [$brand, $article] = array_pad(explode('-', $offerId, 2), 2, null);
                if (!$article) {
                    continue;
                }

                $costRow = $costs->get($article . '|' . mb_strtoupper($brand));
                $costKzt = $costRow->purchase_price ?? null;

                $competitorPriceRub = $item['price_indexes']['ozon_index_data']['minimal_price'] ?? null;
                $ourPriceRub = $item['price'] ?? null;

                if (!$costKzt || !$competitorPriceRub || (float) $competitorPriceRub <= 0) {
                    continue;
                }

                $costRub = $costKzt * $rate;
                // Сколько денег реально попадёт нам, если продадим по цене
                // конкурента: цена минус комиссию минус доставку FBS.
                $netAfterFeesRub = ((float) $competitorPriceRub) * (1 - $commissionFraction) - OzonPriceCalculator::FBS_DELIVERY_RUB;
                $profitRub = $netAfterFeesRub - $costRub;
                $marginPercent = $costRub > 0 ? ($profitRub / $costRub) * 100 : 0;

                $results[] = [
                    'offer_id' => $offerId,
                    'our_price' => $ourPriceRub,
                    'competitor_price' => (float) $competitorPriceRub,
                    'cost_rub' => round($costRub, 2),
                    'profit_if_matched' => round($profitRub, 2),
                    'margin_if_matched_pct' => round($marginPercent, 1),
                ];
            }
        }

        if (empty($results)) {
            $this->warn('Не удалось собрать ни одной позиции с ценой конкурента — проверь offer_id/ответ API.');
            return 0;
        }

        $profitable = array_filter($results, fn ($r) => $r['profit_if_matched'] > 0);
        $loss = array_filter($results, fn ($r) => $r['profit_if_matched'] <= 0);

        $this->info('=== ИТОГ (' . count($results) . ' позиций с данными по конкуренту) ===');
        $this->info('Можем встать вровень с конкурентом и остаться в плюсе: ' . count($profitable) . ' (' . round(count($profitable) / count($results) * 100, 1) . '%)');
        $this->info('Вровень с конкурентом — только в убыток: ' . count($loss) . ' (' . round(count($loss) / count($results) * 100, 1) . '%)');

        usort($results, fn ($a, $b) => $b['margin_if_matched_pct'] <=> $a['margin_if_matched_pct']);

        $this->line('');
        $this->info('--- Топ-15 позиций, где конкурентная цена ВСЁ РАВНО выгодна ---');
        foreach (array_slice($results, 0, 15) as $r) {
            $this->line("  {$r['offer_id']}: конкурент {$r['competitor_price']}₽ vs наша {$r['our_price']}₽ | себест. {$r['cost_rub']}₽ | маржа при цене конкурента: {$r['margin_if_matched_pct']}%");
        }

        $this->line('');
        $this->info('--- Топ-15 позиций, где конкурентная цена — самый серьёзный убыток ---');
        foreach (array_slice(array_reverse($results), 0, 15) as $r) {
            $this->line("  {$r['offer_id']}: конкурент {$r['competitor_price']}₽ vs наша {$r['our_price']}₽ | себест. {$r['cost_rub']}₽ | маржа при цене конкурента: {$r['margin_if_matched_pct']}%");
        }

        return 0;
    }
}
