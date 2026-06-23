<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\KaspiPriceCalculator;

class SyncKaspiFeedCommand extends Command
{
    protected $signature   = 'kaspi:sync-feed {--dry-run : Показать изменения без сохранения}';
    protected $description = 'Понижает цены в kaspi_feed_items, если у дополнительного источника (напр. Фаэтон) '
                            . 'нашлась более выгодная цена — но никогда не ниже минимально допустимой маржи. '
                            . 'НЕ заменяет kaspi:reprice — запускать строго ПОСЛЕ него.';

    // Те же константы и пороги, что в RepriceKaspiCommand — чтобы расчёт
    // минимально допустимой цены был идентичен и не расходился между командами.
    const COMMISSION = 0.125; // 12.5%
    const TAX        = 0.04;  // 4%
    const LOGISTICS  = 1700;  // тенге

    const PRICE_DROP_ANOMALY_THRESHOLD = 0.30; // если новая цена ниже текущей более чем на 30% — не применять автоматически, флагать на проверку

    const MIN_MARGIN_TIERS = [
        ['up_to' => 10000,        'min_pct' => 0.30],
        ['up_to' => 50000,        'min_pct' => 0.20],
        ['up_to' => 150000,       'min_pct' => 0.12],
        ['up_to' => PHP_INT_MAX,  'min_pct' => 0.08],
    ];

    /**
     * Категории, где purchase_price у поставщика — это цена ЗА ВСЮ ЗАКУПКУ
     * (за упаковку/комплект), а не за единицу, и kaspi_qty карточки на
     * себестоимость не влияет. См. подробный комментарий в RepriceKaspiCommand.
     */
    const FIXED_COST_KEYWORDS = [
        'колодки',
        'Колодок',
        'ремень',
    ];

    // Если kaspi_qty карточки >= этого значения — не доверяем ему слепо,
    // это похоже на мусорные данные (см. инцидент с шпильками/реле/ремкомплектами).
    const SUSPICIOUS_QTY_THRESHOLD = 5;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Берём все активные привязанные позиции фида.
        // ВАЖНО: больше не фильтруем и не трогаем multipack-позиции отдельно —
        // они обрабатываются той же qty-aware формулой себестоимости, что и всё остальное.
        $feedItems = DB::table('kaspi_feed_items')
            ->where('bound', 1)
            ->where('is_active', 1)
            ->get();

        $this->info("Позиций в фиде для синка: {$feedItems->count()}");

        $lowered      = 0;
        $skippedHigher = 0; // источник дороже либо равен текущей цене — не трогаем
        $skippedBelowMin = 0; // источник дешевле минимальной маржи — не трогаем, флагаем
        $suspiciousQty = 0;
        $noSource     = 0;

        foreach ($feedItems as $item) {

            $qty = (int) ($item->kaspi_qty ?? 1);
            $qty = max($qty, 1);

            if ($qty >= self::SUSPICIOUS_QTY_THRESHOLD && !$this->isFixedCost($item->kaspi_name)) {
                $this->warn("⚠️  Подозрительный kaspi_qty={$qty} для {$item->our_article} ({$item->kaspi_name}) — пропускаю синк, проверь вручную");
                $suspiciousQty++;
                continue;
            }

            // Ищем самую дешёвую закупку у поставщиков (например Фаэтон), которая есть в наличии.
            $source = DB::table('kaspi_initial_products')
                ->where('sku', $item->our_article)
                ->where('stock', '>=', $qty)
                ->orderBy('purchase_price', 'asc')
                ->first();

            if (!$source) {
                $noSource++;
                continue; // нет альтернативного источника — ничего не трогаем (наличие/деактивацию решает match/reprice, не эта команда)
            }

            $purchase = (float) $source->purchase_price;

            if ($this->isFixedCost($item->kaspi_name)) {
                $cost = $purchase;
            } else {
                $cost = $purchase * $qty;
            }

            $minMarginPct = $this->getMinMarginPct($cost);
            $minNet       = $cost * $minMarginPct;
            $minPrice     = $this->calcSellPrice($cost, $minNet);

            $currentPrice  = (float) $item->price;
            $candidatePrice = round($minPrice) > round($this->etalonOrSourcePrice($source, $cost))
                ? round($minPrice)
                : round($this->etalonOrSourcePrice($source, $cost));

            // Применяем ТОЛЬКО если кандидат дешевле текущей цены — иначе нет смысла трогать
            // (это не репрайсер, а доп. источник для случаев "нашли дешевле — отдадим клиенту дешевле").
            if ($candidatePrice >= $currentPrice) {
                $skippedHigher++;
                continue;
            }

            // Не уходим ниже минимально допустимой маржи — это и есть защита от бага "qty=2 без умножения",
            // потому что minPrice здесь всегда считается от cost = purchase_price * qty (с поправкой FIXED_COST).
            if ($candidatePrice < round($minPrice)) {
                $candidatePrice = round($minPrice);
            }

            // Если падение слишком резкое относительно текущей цены — не применяем втихую,
            // помечаем на ручную проверку (та же логика анти-аномалии, что в RepriceKaspiCommand).
            $drop = $currentPrice > 0 ? ($currentPrice - $candidatePrice) / $currentPrice : 0;

            if ($drop > self::PRICE_DROP_ANOMALY_THRESHOLD) {
                if ($dryRun) {
                    $this->warn(sprintf(
                        "  [ANOMALY] %s | артикул: %s | текущая: %s → кандидат: %s (-%.0f%%) — НЕ применено, нужна проверка",
                        mb_strimwidth($item->kaspi_name, 0, 50, '…'),
                        $item->our_article,
                        number_format($currentPrice, 0, '.', ' '),
                        number_format($candidatePrice, 0, '.', ' '),
                        $drop * 100
                    ));
                } else {
                    DB::table('kaspi_feed_items')
                        ->where('id', $item->id)
                        ->update([
                            'price_review_needed'     => 1,
                            'price_review_reason'     => sprintf('sync-feed: резкое падение цены -%.0f%% (источник: %s)', $drop * 100, $source->supplier_name),
                            'price_review_calculated' => $candidatePrice,
                            'updated_at'              => now(),
                        ]);
                }
                $skippedBelowMin++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    "  [LOWER] %s | артикул: %s | закуп: %s (qty=%d) | мин.цена: %s | текущая: %s → новая: %s",
                    mb_strimwidth($item->kaspi_name, 0, 50, '…'),
                    $item->our_article,
                    number_format($purchase, 0, '.', ' '),
                    $qty,
                    number_format($minPrice, 0, '.', ' '),
                    number_format($currentPrice, 0, '.', ' '),
                    number_format($candidatePrice, 0, '.', ' ')
                ));
            } else {
                DB::table('kaspi_feed_items')
                    ->where('id', $item->id)
                    ->update([
                        'price'          => $candidatePrice,
                        'purchase_price' => $cost,
                        'supplier_name'  => $source->supplier_name,
                        'last_synced_at' => now(),
                        'updated_at'     => now(),
                    ]);
            }

            $lowered++;
        }

        $this->info("Понижено:                          {$lowered}");
        $this->warn("Пропущено (источник дороже/равен): {$skippedHigher}");
        $this->warn("Аномальное падение (на проверку):  {$skippedBelowMin}");
        $this->warn("Подозрительный kaspi_qty:           {$suspiciousQty}");
        $this->warn("Нет альтернативного источника:      {$noSource}");

        if (!$dryRun && $lowered > 0) {
            $this->info('Запускаем генерацию XML...');
            \Illuminate\Support\Facades\Artisan::call('kaspi:generate-xml');
            $this->info('XML перегенерирован.');
        }

        return 0;
    }

    /**
     * Если у источника (например Фаэтон) есть собственная рекомендованная цена продажи (price),
     * и она выше нашей минимальной маржи — используем её как кандидата (она уже учитывает
     * их наценку и может быть конкурентнее). Если её нет или она ниже cost — считаем от cost.
     */
    private function etalonOrSourcePrice(object $source, float $cost): float
    {
        $sourcePrice = isset($source->price) ? (float) $source->price : 0;

        if ($sourcePrice > $cost) {
            return $sourcePrice;
        }

        return (float) KaspiPriceCalculator::calculate($cost);
    }

    private function getMinMarginPct(float $cost): float
    {
        foreach (self::MIN_MARGIN_TIERS as $tier) {
            if ($cost <= $tier['up_to']) {
                return $tier['min_pct'];
            }
        }
        return 0.08;
    }

    private function calcSellPrice(float $cost, float $targetNet): float
    {
        return ($cost + self::LOGISTICS + $targetNet) / (1 - self::COMMISSION - self::TAX);
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