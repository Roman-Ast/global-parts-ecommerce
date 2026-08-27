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

    // Надбавка сверх эталонной цены, когда конкуренции по факту нет —
    // либо конкурентов нет вообще (etalon_no_competitors), либо есть, но
    // ни один не везёт "завтра" (etalon_alone_tomorrow), то есть мы
    // де-факто единственный быстрый вариант. Раньше оба сценария брали
    // тот же $etalonPrice, что и при живой конкуренции — отсутствие
    // конкуренции никак не использовалось. Добавлено 2026-08-26 по
    // просьбе Романа: пример на закупе 15000 — цена 29881 (чистая маржа
    // 27,6% от цены) становится 34363 (маржа ~34,9%).
    const NO_COMPETITION_PREMIUM = 1.15;

    // Порог отклонения новой цены от текущей для флага "требует проверки"
    const PRICE_ANOMALY_THRESHOLD = 0.30; // 30%

    // Прогрессивная шкала МИНИМАЛЬНОЙ маржи (% от себестоимости).
    // Поднято на +3 п.п. по каждому диапазону (2026-08-27, было
    // 30/20/12/8) — разбор реальных продаж Kaspi за 2,5 месяца (165 строк)
    // показал медианную цену продажи на 86% пути от пола к эталону, то
    // есть рынок и так стабильно платит заметно выше пола — небольшой
    // подъём почти не рискует, только подтягивает подстраховку ближе к
    // тому, что уже происходит по факту.
    const MIN_MARGIN_TIERS = [
        ['up_to' => 10000,        'min_pct' => 0.33],
        ['up_to' => 50000,        'min_pct' => 0.23],
        ['up_to' => 150000,       'min_pct' => 0.15],
        ['up_to' => PHP_INT_MAX,  'min_pct' => 0.11],
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
        'лампы',
    ];

    /**
     * Аналог FIXED_COST_KEYWORDS, но привязанный к КОНКРЕТНОМУ поставщику,
     * а не действующий для всех разом. Пример: АвтоТрейд (Астана и Алматы)
     * указывает цену на тормозные диски ЗА ПАРУ (комплект из 2), что
     * подтверждено и самим прайсом, и карточкой Kaspi ("Количество в
     * комплекте: 2"). Но это может быть частностью именно этого поставщика —
     * другие поставщики тех же тормозных дисков вполне могут честно
     * указывать цену за ОДНУ штуку, и для них умножение на kaspi_qty
     * должно оставаться как есть. Поэтому это правило проверяется только
     * для перечисленных ниже supplier_name, а не глобально по всем.
     */
    const SUPPLIER_FIXED_COST_KEYWORDS = [
        'autotrade_ast' => ['диск'],
        'autotrade_alm' => ['диск'],
    ];

    /**
     * Проверяет, относится ли товар к категории с фиксированной
     * себестоимостью для КОНКРЕТНОГО поставщика (см. SUPPLIER_FIXED_COST_KEYWORDS).
     */
    private function isSupplierFixedCost(?string $supplierName, string $title): bool
    {
        if ($supplierName === null || !isset(self::SUPPLIER_FIXED_COST_KEYWORDS[$supplierName])) {
            return false;
        }

        $titleLower = mb_strtolower($title);
        foreach (self::SUPPLIER_FIXED_COST_KEYWORDS[$supplierName] as $keyword) {
            if (str_contains($titleLower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Категории товаров, где Kaspi-карточка обычно показывает "1 шт",
     * но фактическая продажа/себестоимость считается за КОМПЛЕКТ из
     * нескольких штук — множитель свой для каждой категории.
     *
     * Ключ — подстрока в названии товара (регистронезависимо),
     * значение — на сколько штук реально умножать себестоimость.
     *
     * Пример: поршневые кольца физически продаются комплектом на
     * 4 цилиндра, но у поставщика/на Kaspi цена и qty часто указаны
     * как "за 1 шт" — тогда себестоимость нужно умножать на 4, а не
     * на 1, иначе цена уходит в минус аналогично истории с занижением
     * себестоимости.
     */
    const QTY_OVERRIDE_KEYWORDS = [
        'пружин' => 2,
        'spring' => 2,
    ];

    /**
     * Точечные override по КОНКРЕТНОМУ артикулу (наш our_article), а не
     * по ключевому слову в названии — используется, когда категория
     * товара НЕОДНОРОДНА: часть карточек уже честно указывает цену за
     * весь комплект (например, "кольца поршневые на двигатель, комплект"),
     * а часть — только за 1 деталь, хотя физически поставляется тоже
     * комплектом. Ключевое слово тут ловило бы ОБА случая одинаково и
     * задвоило бы себестоимость там, где она уже верна — поэтому для
     * таких смешанных категорий множитель прописывается вручную по
     * каждому проверенному артикулу отдельно.
     */
    const QTY_OVERRIDE_ARTICLES = [
        '800017712050' => 4, // KOLBENSCHMIDT кольца поршневые — цена в прайсе за 1 шт, реально комплект на 4 цилиндра
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
        $statAutoApplied = 0;
        $anomalies = [];

        $normalUpdates  = [];
        $anomalyUpdates = [];

        foreach ($items as $item) {
            $purchase = (float) $item->purchase_price;
            $qty      = (int)   ($item->qty_override ?? $item->kaspi_qty ?? 1);
            $qty      = max($qty, 1);

            if ($this->isFixedCost($item->kaspi_name) || $this->isSupplierFixedCost($item->supplier_name, $item->kaspi_name)) {
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
            if ($purchase < 10000) {
                $ourPrice = $etalonPrice;
                $scenario = 'etalon_low_purchase_fixed';

            } elseif ($competitorsTotal === 0 || $competitorMinAll === null) {
                $ourPrice = $etalonPrice * self::NO_COMPETITION_PREMIUM;
                $scenario = 'etalon_no_competitors';

            } elseif ($tomorrowCount === 0) {
                $ourPrice = $etalonPrice * self::NO_COMPETITION_PREMIUM;
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
            // ВАЖНО: блокируем не по НАПРАВЛЕНИЮ изменения цены, а по
            // РЕАЛЬНОМУ РИСКУ маржи ($flag === 'risk', посчитан чуть выше).
            // Раньше блокировка была по направлению (любое падение >30%
            // блокируется) — но на практике оказалось, что подавляющее
            // большинство таких "падений" это здоровая корректировка цены
            // вниз с маржой 55-110% (цена просто была задрана раньше по
            // старой логике/цене поставщика), а вовсе не риск продажи в
            // убыток. Прогрессивная шкала минимальной маржи (minPrice)
            // и так не даёт цене физически уйти ниже безопасного порога
            // ни в одном штатном сценарии — так что настоящий риск
            // (flag === 'risk') должен быть редким исключением, и именно
            // он единственный оправдывает блокировку и ручную проверку.
            $currentPrice = (float) $item->current_price;
            $isAnomaly = false;
            $isRiskyAnomaly = false; // блокирующая аномалия — только реальный риск маржи
            $anomalyReason = null;

            $isPairItem = !is_null($item->qty_override) && $this->isPairItem($item->kaspi_name);

            if ($currentPrice > 0 && !$isPairItem) {
                $deviation = abs($ourPrice - $currentPrice) / $currentPrice;

                if ($deviation > self::PRICE_ANOMALY_THRESHOLD) {
                    $isAnomaly = true;
                    $isRiskyAnomaly = ($flag === 'risk'); // блокируем только реальный риск, не любое падение
                    $direction = $ourPrice > $currentPrice ? '+' : '-';
                    $anomalyReason = sprintf(
                        'Цена изменилась на %s%.0f%% (%s → %s), сценарий: %s, маржа: %.1f%%',
                        $direction,
                        $deviation * 100,
                        number_format($currentPrice, 0, '.', ' '),
                        number_format($ourPrice, 0, '.', ' '),
                        $scenario,
                        $marginPct
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
                    'blocked'  => $isRiskyAnomaly,
                ];
            }

            $stats[$scenario] = ($stats[$scenario] ?? 0) + 1;

            if ($dryRun) {
                $anomalyMark = $isAnomaly ? ($isRiskyAnomaly ? ' ⚠️BLOCKED(risk)' : ' ⚠️AUTO-APPLIED') : '';
                $pairMark = $item->qty_override ? " [×{$item->qty_override}]" : '';
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

            if ($isRiskyAnomaly) {
                // Падение цены >30% — НЕ применяем, оставляем старую, помечаем на проверку
                $anomalyUpdates[$item->id] = [
                    'price_review_needed'     => 1,
                    'price_review_reason'     => $anomalyReason,
                    'price_review_calculated' => $ourPrice,
                ];
                continue;
            }

            if ($isAnomaly) {
                // Рост цены >30% — применяем автоматически, но фиксируем
                // в статистике/логе, что применение было "нештатным" по
                // размеру скачка (для последующего аудита, не для блокировки).
                $statAutoApplied++;
            }

            $normalUpdates[$item->id] = [
                'price'                   => $ourPrice,
                'purchase_price'          => $cost,
                'strategic_price'         => $etalonPrice,
                'price_strategy'          => $scenario,
                'price_review_needed'     => 0,
                'price_review_reason'     => null,
                'price_review_calculated' => null,
            ];
        }

        if (!$dryRun) {
            $this->batchUpdate('kaspi_feed_items', $normalUpdates);
            $this->batchUpdate('kaspi_feed_items', $anomalyUpdates);
        }

        $this->info('Готово.');
        $this->info("🔴 Риск: {$statRed}  🟡 Низкая: {$statYellow}  🟢 OK: {$statGreen}");

        if ($statAnomaly > 0) {
            $blockedCount = $statAnomaly - $statAutoApplied;
            $this->warn("⚠️  Аномалий (отклонение цены >" . (self::PRICE_ANOMALY_THRESHOLD * 100) . "%): {$statAnomaly}");
            $this->warn("   → из них рост цены применён автоматически: {$statAutoApplied}");
            $this->warn("   → из них падение цены ЗАБЛОКИРОВАНО, требует проверки: {$blockedCount}");
            $this->logAnomalies($anomalies);
        }

        $this->info('Распределение по сценариям:');
        $this->table(
            ['Сценарий', 'Кол-во'],
            collect($stats)->map(fn($count, $scenario) => [$scenario, $count])->toArray()
        );

        if (!$dryRun) {
            /*$this->info('Запускаем генерацию XML...');
            \Illuminate\Support\Facades\Artisan::call('kaspi:generate-xml');
            $this->info('XML обновлён.');*/

            $blockedCount = $statAnomaly - $statAutoApplied;
            if ($blockedCount > 0) {
                $this->info("После исправления причин аномалий: SELECT * FROM kaspi_feed_items WHERE price_review_needed = 1; — посмотреть список, поправить данные, затем повторно запустить kaspi:reprice.");
            }
        }

        return 0;
    }

    /**
     * Батчевый UPDATE через CASE WHEN — вместо отдельного запроса на каждую
     * строку. Разные строки могут иметь разный набор полей для обновления.
     */
    private function batchUpdate(string $table, array $updatesById, int $chunkSize = 500): void
    {
        if (empty($updatesById)) {
            return;
        }

        $allFields = [];
        foreach ($updatesById as $fields) {
            $allFields = array_merge($allFields, array_keys($fields));
        }
        $allFields = array_unique($allFields);

        foreach (array_chunk($updatesById, $chunkSize, true) as $chunk) {
            $ids = array_keys($chunk);
            $cases = [];
            $bindings = [];

            foreach ($allFields as $field) {
                $sql = "`{$field}` = CASE `id` ";
                foreach ($chunk as $id => $fields) {
                    if (array_key_exists($field, $fields)) {
                        $sql .= "WHEN ? THEN ? ";
                        $bindings[] = $id;
                        $bindings[] = $fields[$field];
                    }
                }
                $sql .= "ELSE `{$field}` END";
                $cases[] = $sql;
            }

            $cases[] = "`updated_at` = ?";
            $bindings[] = now();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $bindings = array_merge($bindings, $ids);

            $sql = "UPDATE `{$table}` SET " . implode(', ', $cases) . " WHERE `id` IN ({$placeholders})";

            DB::update($sql, $bindings);
        }
    }

    /**
     * Автоматически проставляет qty_override для товаров, которые
     * реально продаются комплектом (пружины ×2, поршневые кольца ×4
     * и т.п. — см. QTY_OVERRIDE_KEYWORDS), но у которых kaspi_qty = 1
     * (Kaspi-карточка показывает "за 1 шт"). Также сбрасывает
     * price_review_needed, чтобы репрайс не пропустил их.
     */
    private function applyQtyOverrides(): void
    {
        // Кандидаты: любой товар с kaspi_qty=1, без override, чьё название
        // содержит хотя бы одно ключевое слово из QTY_OVERRIDE_KEYWORDS.
        $candidates = DB::table('kaspi_feed_items as kfi')
            ->join('kaspi_initial_products as kip', 'kip.sku', '=', 'kfi.our_article')
            ->where('kfi.kaspi_qty', 1)
            ->whereNull('kfi.qty_override')
            ->where('kfi.is_active', 1)
            ->where(function ($q) {
                foreach (array_keys(self::QTY_OVERRIDE_KEYWORDS) as $kw) {
                    $q->orWhereRaw('LOWER(kfi.kaspi_name) LIKE ?', ['%' . mb_strtolower($kw) . '%']);
                }
            })
            ->select('kfi.id', 'kfi.kaspi_name', 'kip.supplier_name', 'kip.brand')
            ->get();

        $idsByMultiplier = []; // multiplier => [id, id, ...]
        $skippedBundled = 0;

        foreach ($candidates as $row) {
            $bundledBrands = self::PAIR_ALREADY_BUNDLED[$row->supplier_name] ?? [];

            if (in_array(mb_strtolower($row->brand), $bundledBrands, true)) {
                // Уже комплект в прайсе поставщика — не удваиваем/не умножаем повторно
                $skippedBundled++;
                continue;
            }

            $multiplier = $this->matchQtyOverrideMultiplier($row->kaspi_name);
            if ($multiplier === null) {
                continue; // подстраховка, не должно случиться раз кандидат уже прошёл фильтр по keyword
            }

            $idsByMultiplier[$multiplier][] = $row->id;
        }

        $totalUpdated = 0;
        foreach ($idsByMultiplier as $multiplier => $ids) {
            $updated = DB::table('kaspi_feed_items')
                ->whereIn('id', $ids)
                ->update(['qty_override' => $multiplier]);

            $totalUpdated += $updated;
            $this->info("🔧 qty_override={$multiplier} проставлен для {$updated} позиций (kaspi_qty=1)");
        }

        if ($skippedBundled > 0) {
            $this->info("↷ Пропущено как уже-комплект (SAT/Baikal у АвтоТрейда и т.п.): {$skippedBundled}");
        }

        // === Точечные override по конкретным артикулам (QTY_OVERRIDE_ARTICLES) ===
        // Отдельно от keyword-логики выше — тут множитель применяется только
        // к явно перечисленным our_article, независимо от текста названия,
        // чтобы не задвоить себестоимость у уже корректно оценённых карточек
        // той же товарной категории (см. комментарий у константы).
        if (!empty(self::QTY_OVERRIDE_ARTICLES)) {
            // ВАЖНО: array_keys() на массиве с числовыми строковыми ключами
            // ("800017712050" => 4) отдаёт их как int, а не string — это
            // стандартное поведение PHP для числовых строк-ключей массива.
            // Если такой int уйдёт как есть в whereIn(), Laravel сгенерирует
            // SQL без кавычек вокруг значения, и MySQL при сравнении VARCHAR-
            // колонки с числом попытается привести К ЧИСЛУ ВСЕ строки в
            // таблице для сравнения — и упадёт на первом же нечисловом
            // артикуле (например 'PE21370') с ошибкой "Truncated incorrect
            // DOUBLE value". Поэтому явно приводим ключи обратно к строке.
            $overrideArticles = array_map('strval', array_keys(self::QTY_OVERRIDE_ARTICLES));

            $articleCandidates = DB::table('kaspi_feed_items as kfi')
                ->whereIn('kfi.our_article', $overrideArticles)
                ->where('kfi.kaspi_qty', 1)
                ->whereNull('kfi.qty_override')
                ->where('kfi.is_active', 1)
                ->select('kfi.id', 'kfi.our_article')
                ->get();

            $articleIdsByMultiplier = [];
            foreach ($articleCandidates as $row) {
                $multiplier = self::QTY_OVERRIDE_ARTICLES[$row->our_article];
                $articleIdsByMultiplier[$multiplier][] = $row->id;
            }

            foreach ($articleIdsByMultiplier as $multiplier => $ids) {
                $updated = DB::table('kaspi_feed_items')
                    ->whereIn('id', $ids)
                    ->update(['qty_override' => $multiplier]);

                $this->info("🔧 [точечный override] qty_override={$multiplier} проставлен для {$updated} позиций по списку QTY_OVERRIDE_ARTICLES");
            }

            // Снимаем блокировку репрайса именно для этих артикулов, если она была
            $unblockedArticles = DB::table('kaspi_feed_items')
                ->whereIn('our_article', $overrideArticles)
                ->where('price_review_needed', 1)
                ->update([
                    'price_review_needed'     => 0,
                    'price_review_reason'     => null,
                    'price_review_calculated' => null,
                ]);

            if ($unblockedArticles > 0) {
                $this->info("🔓 Снята блокировка price_review для {$unblockedArticles} позиций из QTY_OVERRIDE_ARTICLES");
            }
        }

        // Сброс блокировки репрайса для всех категорий с qty override — без изменений
        $unblocked = DB::table('kaspi_feed_items')
            ->where('price_review_needed', 1)
            ->where(function ($q) {
                foreach (array_keys(self::QTY_OVERRIDE_KEYWORDS) as $kw) {
                    $q->orWhereRaw('LOWER(kaspi_name) LIKE ?', ['%' . mb_strtolower($kw) . '%']);
                }
            })
            ->update([
                'price_review_needed'     => 0,
                'price_review_reason'     => null,
                'price_review_calculated' => null,
            ]);

        if ($unblocked > 0) {
            $this->info("🔓 Снята блокировка price_review для {$unblocked} позиций с qty override");
        }
    }

    /**
     * Возвращает множитель для первого совпавшего ключевого слова из
     * QTY_OVERRIDE_KEYWORDS, или null если ни одно не совпало.
     */
    private function matchQtyOverrideMultiplier(string $title): ?int
    {
        $titleLower = mb_strtolower($title);
        foreach (self::QTY_OVERRIDE_KEYWORDS as $kw => $multiplier) {
            if (str_contains($titleLower, mb_strtolower($kw))) {
                return $multiplier;
            }
        }
        return null;
    }
    /**
     * Записывает список аномальных позиций в лог-файл для разбора.
     */
    private function logAnomalies(array $anomalies): void
    {
        $lines = [];
        $lines[] = '=== ' . now()->format('Y-m-d H:i:s') . ' — Аномалии цен (kaspi:reprice) ===';

        foreach ($anomalies as $a) {
            $status = $a['blocked'] ? 'ЗАБЛОКИРОВАНО (риск маржи)' : 'ПРИМЕНЕНО (маржа безопасна)';
            $lines[] = sprintf(
                "[%s] %s | %s | %s",
                $status,
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
        return $this->matchQtyOverrideMultiplier($title) !== null;
    }
}