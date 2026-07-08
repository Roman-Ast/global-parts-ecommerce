<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\KaspiPriceCalculator;

class RepriceKaspiCommand extends Command
{
    protected $signature   = 'kaspi:reprice {--dry-run}';
    protected $description = 'Пересчитывает цены в kaspi_feed_items по новой стратегии (эталон + конкуренты с доставкой завтра)';

    const COMMISSION = 0.125; // 12.5%
    const TAX        = 0.04;  // 4%
    const LOGISTICS  = 1700;  // тенге

    // Порог отклонения новой цены от текущей для флага "требует проверки"
    const PRICE_ANOMALY_THRESHOLD = 0.30; // 30%

    // Прогрессивная шкала МИНИМАЛЬНОЙ маржи (% от себестоимости)
    const MIN_MARGIN_TIERS = [
        ['up_to' => 10000,        'min_pct' => 0.30],
        ['up_to' => 50000,        'min_pct' => 0.20],
        ['up_to' => 150000,       'min_pct' => 0.12],
        ['up_to' => PHP_INT_MAX,  'min_pct' => 0.08],
    ];

    /**
     * Категории товаров, где purchase_price у поставщика — это ВСЕГДА
     * полная себестоимость ОДНОЙ ЗАКУПКИ (одной коробки), и kaspi_qty
     * карточки на cost НИКАК НЕ ВЛИЯЕТ.
     *
     * Пример — тормозные колодки: у поставщика в накладной всегда
     * "кол-во: 1" и цена purchase_price за коробку (физически в коробке
     * 4 колодки, комплект на ось). На Kaspi разные продавцы трактуют
     * "комплектность" по-разному — кто-то пишет kaspi_qty=1 (имея в виду
     * упаковку), кто-то 2 (на 2 колеса), кто-то 4 (физическое кол-во
     * колодок). Но во всех случаях клиент получает ОДНУ коробку,
     * и закупка стоит ровно purchase_price — независимо от kaspi_qty.
     *
     * cost = purchase_price (kaspi_qty игнорируется для этих категорий).
     *
     * Значение — подстрока в названии товара (регистронезависимо).
     */
    const FIXED_COST_KEYWORDS = [
        'колодки', // тормозные колодки — себестоимость = purchase_price, независимо от kaspi_qty карточки
        'Колодок',
        'ремень',
        'лампы'
    ];

    const PAIR_KEYWORDS = [
        'пружин',
        'spring',
    ];

    const PAIR_ALREADY_BUNDLED = [
        'autotrade_ast' => ['sat', 'baikor'], // было 'baikal' — опечатка, в прайсе бренд BAIKOR
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->applyQtyOverrides();

        $items = DB::select("
            SELECT
                kfi.id,
                kfi.kaspi_sku,
                kfi.kaspi_name,
                kfi.our_article,
                kfi.price AS current_price,
                kfi.kaspi_qty,
                kfi.qty_override, 
                kfi.qty_suspicious,
                kfi.competitors_min_price,
                kfi.competitors_total,
                kfi.competitors_tomorrow_count,
                kip.purchase_price,
                kip.supplier_name,
                (
                    SELECT MIN(kc.price)
                    FROM kaspi_competitors kc
                    WHERE kc.kaspi_sku = kfi.kaspi_sku
                    AND kc.delivery_duration = 'TOMORROW'
                    AND kc.preorder_days = 0
                ) AS tomorrow_min_price
            FROM kaspi_feed_items kfi
            JOIN kaspi_initial_products kip ON kip.sku = kfi.our_article
            WHERE kfi.is_active = 1
            AND kip.purchase_price > 0
        ");

        if (empty($items)) {
            $this->info('Нет позиций для пересчёта.');
            return 0;
        }

        $this->info('Обрабатываем ' . count($items) . ' позиций...');

        $stats = [];
        $statRed = $statYellow = $statGreen = 0;
        $statAnomaly = 0;
        $anomalies = [];

        foreach ($items as $item) {
            $purchase = (float) $item->purchase_price;
            $qty      = (int)   ($item->qty_override ?? $item->kaspi_qty ?? 1);
            $qty      = max($qty, 1);

            if ($this->isFixedCost($item->kaspi_name)) {
                $cost = $purchase;
            } else {
                $cost = $purchase * $qty;
            }

            // Эталонная цена по нашему калькулятору
            $etalonPrice = (float) KaspiPriceCalculator::calculate($cost);

            // Минимально допустимая цена по прогрессивной шкале
            $minMarginPct = $this->getMinMarginPct($cost);
            $minNet       = $cost * $minMarginPct;
            $minPrice     = $this->calcSellPrice($cost, $minNet);

            $competitorsTotal       = (int) $item->competitors_total;
            $tomorrowCount          = (int) $item->competitors_tomorrow_count;
            $competitorMinAll       = $item->competitors_min_price ? (float) $item->competitors_min_price : null;
            $competitorMinTomorrow  = $item->tomorrow_min_price ? (float) $item->tomorrow_min_price : null;

            // === ЛОГИКА ВЫБОРА ЦЕНЫ ===
            // Для дешёвого входа (закуп < 10 000 тг) держим эталон ВСЕГДА,
            // не участвуем в демпинге под конкурента вообще — приоритет
            // максимальному охвату карточек и марже, а не битве за топ
            // выдачи в этом сегменте. Ревизия после первых недель по рынку.
            if ($purchase < 10000) {
                $ourPrice = $etalonPrice;
                $scenario = 'etalon_low_purchase_fixed';
 
            } elseif ($competitorsTotal === 0 || $competitorMinAll === null) {
                $ourPrice = $etalonPrice;
                $scenario = 'etalon_no_competitors';
 
            } elseif ($tomorrowCount === 0) {
                // Мы единственные с доставкой завтра — держим эталон
                $ourPrice = $etalonPrice;
                $scenario = 'etalon_alone_tomorrow';
 
            } else {
                if ($competitorMinTomorrow === null) {
                    $competitorMinTomorrow = $competitorMinAll;
                }
 
                if ($etalonPrice <= $competitorMinTomorrow) {
                    $ourPrice = $etalonPrice;
                    $scenario = 'etalon_competitive';
                } else {
                    $beatPrice = floor($competitorMinTomorrow * 0.995);
 
                    if ($beatPrice >= $minPrice) {
                        $ourPrice = $beatPrice;
                        $scenario = 'beat_tomorrow_competitor';
                    } else {
                        $ourPrice = $minPrice;
                        $scenario = 'min_margin_dumping';
                    }
                }
            }

            $ourPrice = round($ourPrice);

            // Маржа для статистики/флага
            $netMargin = $ourPrice
                - $cost
                - ($ourPrice * self::COMMISSION)
                - ($ourPrice * self::TAX)
                - self::LOGISTICS;

            $marginPct = $cost > 0 ? round(($netMargin / $cost) * 100, 1) : 0;

            if ($netMargin < 0 || $marginPct < ($minMarginPct * 100)) {
                $flag = 'risk';
                $statRed++;
            } elseif ($marginPct < ($minMarginPct * 100 * 1.5)) {
                $flag = 'low';
                $statYellow++;
            } else {
                $flag = 'ok';
                $statGreen++;
            }

            // === ПРОВЕРКА АНОМАЛИИ ===
            // Сравниваем новую рассчитанную цену со старой (текущей в фиде).
            // Если отклонение больше порога — не применяем новую цену,
            // оставляем старую и помечаем позицию на ручную проверку.
            // Исключение: товары с qty_override (пружины и др.) — их рост цены
            // намеренный (себестоимость пересчитана), аномалия не применяется.
            $currentPrice = (float) $item->current_price;
            $isAnomaly = false;
            $anomalyReason = null;

            $isPairItem = !is_null($item->qty_override) && $this->isPairItem($item->kaspi_name);

            if ($currentPrice > 0 && !$isPairItem) {
                $deviation = abs($ourPrice - $currentPrice) / $currentPrice;

                if ($deviation > self::PRICE_ANOMALY_THRESHOLD) {
                    $isAnomaly = true;
                    $direction = $ourPrice > $currentPrice ? '+' : '-';
                    $anomalyReason = sprintf(
                        'Цена изменилась на %s%.0f%% (%s → %s), сценарий: %s',
                        $direction,
                        $deviation * 100,
                        number_format($currentPrice, 0, '.', ' '),
                        number_format($ourPrice, 0, '.', ' '),
                        $scenario
                    );
                }
            }

            if ($isAnomaly) {
                $statAnomaly++;
                $anomalies[] = [
                    'article'  => $item->our_article,
                    'name'     => $item->kaspi_name,
                    'old'      => $currentPrice,
                    'new'      => $ourPrice,
                    'reason'   => $anomalyReason,
                ];
            }

            $stats[$scenario] = ($stats[$scenario] ?? 0) + 1;

            if ($dryRun) {
                $anomalyMark = $isAnomaly ? ' ⚠️ANOMALY' : '';
                $pairMark    = $isPairItem ? ' [×2]' : '';
                $this->line(sprintf(
                    "[%s|%s]%s%s %s | закуп: %s | эталон: %s | мин: %s | итог: %s | маржа: %.1f%% (%s) | конк: %d | завтра: %d",
                    $scenario,
                    $flag,
                    $anomalyMark,
                    $pairMark,
                    mb_strimwidth($item->kaspi_name, 0, 40, '…'),
                    number_format($cost, 0, '.', ' '),
                    number_format($etalonPrice, 0, '.', ' '),
                    number_format($minPrice, 0, '.', ' '),
                    number_format($ourPrice, 0, '.', ' '),
                    $marginPct,
                    number_format($netMargin, 0, '.', ' '),
                    $competitorsTotal,
                    $tomorrowCount,
                ));
                continue;
            }

            if ($isAnomaly) {
                // Не применяем новую цену — оставляем старую, помечаем на проверку
                DB::table('kaspi_feed_items')
                    ->where('id', $item->id)
                    ->update([
                        'price_review_needed'     => 1,
                        'price_review_reason'     => $anomalyReason,
                        'price_review_calculated' => $ourPrice,
                        'updated_at'              => now(),
                    ]);
                continue;
            }

            DB::table('kaspi_feed_items')
                ->where('id', $item->id)
                ->update([
                    'price'                   => $ourPrice,
                    'purchase_price'          => $cost,
                    'strategic_price'         => $etalonPrice,
                    'price_strategy'          => $scenario,
                    'price_review_needed'     => 0,
                    'price_review_reason'     => null,
                    'price_review_calculated' => null,
                    'updated_at'              => now(),
                ]);
        }

        $this->info('Готово.');
        $this->info("🔴 Риск: {$statRed}  🟡 Низкая: {$statYellow}  🟢 OK: {$statGreen}");

        if ($statAnomaly > 0) {
            $this->warn("⚠️  Аномалий (отклонение цены >" . (self::PRICE_ANOMALY_THRESHOLD * 100) . "%): {$statAnomaly} — цена НЕ применена, требуется ручная проверка");
            $this->logAnomalies($anomalies);
        }

        $this->info('Распределение по сценариям:');
        $this->table(
            ['Сценарий', 'Кол-во'],
            collect($stats)->map(fn($count, $scenario) => [$scenario, $count])->toArray()
        );

        if (!$dryRun) {
            $this->info('Запускаем генерацию XML...');
            \Illuminate\Support\Facades\Artisan::call('kaspi:generate-xml');
            $this->info('XML обновлён.');

            if ($statAnomaly > 0) {
                $this->info("После исправления причин аномалий: SELECT * FROM kaspi_feed_items WHERE price_review_needed = 1; — посмотреть список, поправить данные, затем повторно запустить kaspi:reprice.");
            }
        }

        return 0;
    }

    /**
     * Автоматически проставляет qty_override = 2 для товаров, которые
     * всегда продаются парами (пружины), но у которых kaspi_qty = 1 (дефолт).
     * Также сбрасывает price_review_needed чтобы репрайс не пропустил их.
     */
    private function applyQtyOverrides(): void
    {
        // Кандидаты на qty_override=2: пружины с kaspi_qty=1, вместе с брендом
        // и поставщиком — чтобы можно было отфильтровать исключения.
        $candidates = DB::table('kaspi_feed_items as kfi')
            ->join('kaspi_initial_products as kip', 'kip.sku', '=', 'kfi.our_article')
            ->where('kfi.kaspi_qty', 1)
            ->whereNull('kfi.qty_override')
            ->where('kfi.is_active', 1)
            ->where(function ($q) {
                foreach (self::PAIR_KEYWORDS as $kw) {
                    $q->orWhereRaw('LOWER(kfi.kaspi_name) LIKE ?', ['%' . mb_strtolower($kw) . '%']);
                }
            })
            ->select('kfi.id', 'kip.supplier_name', 'kip.brand')
            ->get();

        $idsToOverride  = [];
        $skippedBundled = 0;

        foreach ($candidates as $row) {
            $bundledBrands = self::PAIR_ALREADY_BUNDLED[$row->supplier_name] ?? [];

            if (in_array(mb_strtolower($row->brand), $bundledBrands, true)) {
                // Уже комплект по 2 шт. в прайсе поставщика — не удваиваем
                $skippedBundled++;
                continue;
            }

            $idsToOverride[] = $row->id;
        }

        if (!empty($idsToOverride)) {
            $updated = DB::table('kaspi_feed_items')
                ->whereIn('id', $idsToOverride)
                ->update(['qty_override' => 2]);

            $this->info("🔧 qty_override=2 проставлен для {$updated} пружин (kaspi_qty=1)");
        }

        if ($skippedBundled > 0) {
            $this->info("↷ Пропущено как уже-комплект (SAT/Baikal у АвтоТрейда и т.п.): {$skippedBundled}");
        }

        // Сброс блокировки репрайса для пружин — без изменений
        $unblocked = DB::table('kaspi_feed_items')
            ->where('price_review_needed', 1)
            ->where(function ($q) {
                foreach (self::PAIR_KEYWORDS as $kw) {
                    $q->orWhereRaw('LOWER(kaspi_name) LIKE ?', ['%' . mb_strtolower($kw) . '%']);
                }
            })
            ->update([
                'price_review_needed'     => 0,
                'price_review_reason'     => null,
                'price_review_calculated' => null,
            ]);

        if ($unblocked > 0) {
            $this->info("🔓 Снята блокировка price_review для {$unblocked} пружин");
        }
    }
    /**
     * Записывает список аномальных позиций в лог-файл для разбора.
     */
    private function logAnomalies(array $anomalies): void
    {
        $lines = [];
        $lines[] = '=== ' . now()->format('Y-m-d H:i:s') . ' — Аномалии цен (kaspi:reprice) ===';

        foreach ($anomalies as $a) {
            $lines[] = sprintf(
                "%s | %s | %s",
                $a['article'],
                mb_strimwidth($a['name'], 0, 60, '…'),
                $a['reason']
            );
        }

        $lines[] = '';

        $logPath = storage_path('logs/kaspi_price_anomalies.log');
        file_put_contents($logPath, implode("\n", $lines) . "\n", FILE_APPEND);

        $this->comment("Лог аномалий записан в: {$logPath}");
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

    /**
     * Проверяет, относится ли товар к категории с фиксированной
     * себестоимостью (cost = purchase_price, kaspi_qty игнорируется).
     */
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

    private function isPairItem(string $title): bool
    {
        $titleLower = mb_strtolower($title);
        foreach (self::PAIR_KEYWORDS as $kw) {
            if (str_contains($titleLower, mb_strtolower($kw))) {
                return true;
            }
        }
        return false;
    }
}
