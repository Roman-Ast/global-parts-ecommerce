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

    const DISABLED_SUPPLIERS = [
        'forumauto_lp', // прайс давно не обновлялся, временно исключён 2026-08-08
        'shatem', // временно отключён по просьбе Романа 2026-08-29
    ];

    // kaspi_sku карточек, которые нужно навсегда исключить из фида
    // (напр. по прямой просьбе Романа — проблемная позиция).
    const EXCLUDED_KASPI_SKUS = [
        '163373452', // Winkod W338713SA/W343418SA, 4 шт — исключено 2026-08-28
    ];

    /**
     * См. подробный комментарий в RepriceKaspiCommand — тормозные диски
     * АвтоТрейда идут по цене за пару, но это частность конкретного
     * поставщика, а не общее правило для всех продавцов дисков.
     */
    const SUPPLIER_FIXED_COST_KEYWORDS = [
        'autotrade_ast' => ['диск'],
        'autotrade_alm' => ['диск'],
    ];

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
     * Некоторые карточки Kaspi явно рекламируют СРАЗУ ДВА самостоятельных
     * кода через "/" в названии (например, "DEPO блок фара
     * 4411104LLDEH/4411104RLDEH" — левая и правая фара как парная
     * ссылка). Если у нас в прайсах в наличии только ОДИН из двух кодов,
     * простое совпадение по токену радостно матчит эту карточку и
     * репортит её как полностью доступную — хотя по факту в наличии лишь
     * половина того, что заявлено в названии.
     *
     * Регулярка ищет пару "код1/код2", где оба выглядят как настоящие
     * артикулы (4+ буквенно-цифровых символов). Это НЕ привязано к
     * конкретно L/R-суффиксам — сработает для любой карточки с таким
     * паттерном названия, независимо от того, почему там два кода
     * (сторона, цвет, комплектация — не важно, принцип защиты один и
     * тот же: не показывать доступным то, что фактически не полностью
     * в наличии).
     */
    /**
     * Некоторые карточки Kaspi явно рекламируют СРАЗУ ДВА самостоятельных
     * кода через "/" в названии — но это может означать ДВЕ РАЗНЫЕ вещи:
     *
     * 1) Настоящая парная деталь (левая/правая), где нужны ОБЕ половины —
     *    например "DEPO блок фара 4411104LLDEH/4411104RLDEH". Тут коды
     *    одинаковой длины и отличаются РОВНО в одной позиции — именно
     *    буквой L/R (лево/право).
     *
     * 2) Перекрёстная ссылка старый↔новый номер ОДНОЙ И ТОЙ ЖЕ детали —
     *    например "CTR рулевой наконечник CE0338L/CEKH-47L" (оба
     *    заканчиваются на "L" — это не "лево/право", а просто два разных
     *    формата номера одного и того же наконечника). Тут в наличии
     *    достаточно ОДНОГО кода, вторая половина не нужна.
     *
     * Различаем по строгому правилу: считаем "настоящей парой" (нужны
     * ОБЕ половины) только если коды ОДИНАКОВОЙ ДЛИНЫ и отличаются РОВНО
     * в одном символе, и это именно L/R или Л/П — либо если в названии
     * есть явный маркер количества ("2 шт", "комплект"). Во всех
     * остальных случаях (разный формат/длина кодов, как у старый/новый
     * номер или у перекрёстных OEM-ссылок) — это НЕ обязательная пара,
     * обычная логика применяется как есть.
     */
    const PAIRED_CODE_PATTERN = '/([A-ZА-Я0-9\-]{4,})\s*\/\s*([A-ZА-Я0-9\-.]{4,})/ui';
    // ВАЖНО: требуем явное ЧИСЛО ≥2 перед "шт" — "1 шт" означает ОБРАТНОЕ
    // (это один товар с альтернативным номером, а не пара из двух).
    // "комплект" убран как сигнал вообще — слишком шумное слово, естественно
    // встречается в названиях категорий ("ремкомплект", "комплект сцепления",
    // "комплект прокладок") без всякой связи с тем, нужны ли обе половины
    // конкретной пары кодов.
    const PAIR_QTY_MARKER_PATTERN = '/\b(\d+)\s*шт\b/ui';
    const LR_PAIR_CHARS = [
        ['L', 'R'],
        ['Л', 'П'],
    ];

    /**
     * Проверяет, содержит ли название карточки НАСТОЯЩУЮ парную ссылку
     * (см. правила выше), и если наш матч пришёл только по ОДНОЙ стороне
     * пары — ищет вторую сторону среди своих же прайсов. Возвращает
     * true, если пара НЕПОЛНАЯ (второй код не найден в наличии).
     */
    private function isIncompletePair(string $kaspiName, string $ourArticle): bool
    {
        if (!preg_match(self::PAIRED_CODE_PATTERN, $kaspiName, $m)) {
            return false;
        }

        $normalize = fn($s) => strtoupper(preg_replace('/[^A-ZА-Я0-9]/ui', '', $s));

        $codeA = $normalize($m[1]);
        $codeB = $normalize($m[2]);
        $ours  = $normalize($ourArticle);

        if ($ours !== $codeA && $ours !== $codeB) {
            return false;
        }

        $qtyMarkerMatches = preg_match(self::PAIR_QTY_MARKER_PATTERN, $kaspiName, $qtyMatch) === 1;
        $hasGenuineQtyMarker = $qtyMarkerMatches && (int) $qtyMatch[1] >= 2;

        $isGenuinePair = $this->isLrMirrorPair($codeA, $codeB) || $hasGenuineQtyMarker;

        if (!$isGenuinePair) {
            return false; // перекрёстная ссылка старый/новый номер — не пара
        }

        $companionCode = ($ours === $codeA) ? $codeB : $codeA;

        $hasCompanion = DB::table('kaspi_initial_products')
            ->where('stock', '>=', 2)
            ->whereRaw('UPPER(REPLACE(sku, "-", "")) = ?', [$companionCode])
            ->exists();

        return !$hasCompanion;
    }

    /**
     * true, если codeA и codeB — одинаковой длины и отличаются РОВНО в
     * одной позиции, причём различающиеся символы — это пара L/R или Л/П
     * (в любом порядке). Это отличает настоящую "лево/право" деталь от
     * произвольного однобуквенного отличия (как в 163500/163510, где
     * различается цифра, а не сторона).
     */
    private function isLrMirrorPair(string $codeA, string $codeB): bool
    {
        if (strlen($codeA) !== strlen($codeB)) {
            return false;
        }

        $diffPositions = [];
        for ($i = 0; $i < strlen($codeA); $i++) {
            if ($codeA[$i] !== $codeB[$i]) {
                $diffPositions[] = $i;
                if (count($diffPositions) > 1) {
                    return false; // больше одного отличия — не строгая L/R-пара
                }
            }
        }

        if (count($diffPositions) !== 1) {
            return false; // коды идентичны — не пара вообще
        }

        $pos = $diffPositions[0];
        $charA = $codeA[$pos];
        $charB = $codeB[$pos];

        foreach (self::LR_PAIR_CHARS as [$l, $r]) {
            if (($charA === $l && $charB === $r) || ($charA === $r && $charB === $l)) {
                return true;
            }
        }

        return false;
    }

    public function handle(): int
    {
        $supplier = $this->option('supplier');
        $dryRun   = $this->option('dry-run');

        // Принудительно деактивируем уже существующие в фиде исключённые SKU —
        // просто не матчить их заново недостаточно, старая активная строка
        // из прошлых прогонов иначе так и останется висеть в фиде навсегда.
        if (!$dryRun && !empty(self::EXCLUDED_KASPI_SKUS)) {
            $forceDeactivated = DB::table('kaspi_feed_items')
                ->whereIn('kaspi_sku', self::EXCLUDED_KASPI_SKUS)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            if ($forceDeactivated > 0) {
                $this->warn("Принудительно деактивировано исключённых SKU: {$forceDeactivated}");
            }
        }

        // То же самое для отключённых поставщиков (DISABLED_SUPPLIERS) —
        // исключение из запроса ниже останавливает только НОВЫЕ офферы от
        // этого поставщика, уже стоящие в фиде активные строки сами по
        // себе никуда не денутся, пока их явно не деактивировать.
        if (!$dryRun && !empty(self::DISABLED_SUPPLIERS)) {
            $forceDeactivatedSuppliers = DB::table('kaspi_feed_items')
                ->whereIn('supplier_name', self::DISABLED_SUPPLIERS)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            if ($forceDeactivatedSuppliers > 0) {
                $this->warn("Принудительно деактивировано позиций отключённых поставщиков: {$forceDeactivatedSuppliers}");
            }
        }

        $query = DB::table('kaspi_initial_products as kip')
            ->join('kaspi_sku_test as kst', 'kst.request_article', '=', 'kip.sku')
            ->where('kip.stock', '>=', 2)
            ->whereNotIn('kip.supplier_name', self::DISABLED_SUPPLIERS)   // ← добавить
            ->where('kst.sku', '!=', 'NOT_FOUND')
            ->whereNotIn('kst.sku', self::EXCLUDED_KASPI_SKUS)
            ->whereNotNull('kip.brand')
            ->where('kip.brand', '!=', '')
            ->whereRaw('LOWER(kst.name) LIKE CONCAT("%", LOWER(kip.brand), "%")')
            /**
             * ВАЖНО: это ПРОВЕРКА-СТРАХОВКА, а не основной источник истины.
             * request_article приходит из kaspi_sku_test напрямую от
             * kaspi:parse-sku и уже должен быть точным артикулом после
             * фикса нормализации в ParseKaspiSkuCommand::searchKaspi().
             * Но на случай, если в базе останутся старые/ручные записи
             * с "усечёнными" артикулами (как исторический баг BW0010 vs
             * BW0010-07-2), делаем ДОПОЛНИТЕЛЬНУЮ проверку здесь тоже —
             * по нормализованной (без дефисов/пробелов) строке, как
             * ЦЕЛЫЙ ТОКЕН, а не как случайную подстроку. Без этого
             * "BW0010" совпадал с "BW0010-07-2" просто потому что после
             * BW0010 в названии шёл дефис, который regex считал границей
             * токена.
             */
            /**
             * Убираем ТОЛЬКО дефисы. Пробелы намеренно НЕ трогаем —
             * они разделяют настоящие слова в названии карточки
             * ("Насос GMB GWT83AH"). Если убрать и пробелы тоже,
             * соседние слова склеятся с артикулом, и проверка границы
             * токена будет ложно считать совпадение отсутствующим
             * там, где оно есть на самом деле.
             */
            ->whereRaw("
                REPLACE(UPPER(kst.name), '-', '')
                REGEXP CONCAT(
                    '(^|[^A-Z0-9])',
                    REPLACE(UPPER(kst.request_article), '-', ''),
                    '([^A-Z0-9]|$)'
                )
            ")
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

        // Группируем по kaspi_sku — берём минимальную цену среди поставщиков.
        // При РАВНОЙ цене — предпочитаем поставщика с меньшим preorder_days
        // (быстрее доставка = выше конверсия на Kaspi). Без этого правила
        // выбор между офферами с одинаковой ценой был непредсказуем —
        // зависел от порядка строк в результате запроса (а он не
        // гарантирован, т.к. в query нет ORDER BY), и в фид мог случайно
        // уйти вариант с долгим предзаказом вместо того, что есть в наличии
        // сегодня у другого поставщика по той же цене.
        $grouped = [];
        foreach ($rows as $row) {
            $sku = $row->kaspi_sku;

            if (!isset($grouped[$sku])) {
                $grouped[$sku] = $row;
                continue;
            }

            $current = $grouped[$sku];

            if ($row->price < $current->price) {
                $grouped[$sku] = $row;
            } elseif (
                (float) $row->price === (float) $current->price
                && (int) $row->preorder_days < (int) $current->preorder_days
            ) {
                $grouped[$sku] = $row;
            }
        }

        $this->info("Уникальных SKU после группировки: " . count($grouped));

        // === ЗАЩИТА ОТ "ПАРНЫХ" КАРТОЧЕК С НЕПОЛНЫМ НАЛИЧИЕМ ===
        // См. комментарий у isIncompletePair() — если карточка Kaspi
        // рекламирует два кода через "/", а у нас в наличии только один —
        // исключаем позицию из выдачи целиком, а не показываем её как
        // полностью доступную на основе одной лишь половины.
        $incompletePairsCount = 0;
        $excludedPairs = [];
        foreach ($grouped as $sku => $row) {
            if ($this->isIncompletePair($row->kaspi_name, $row->article)) {
                $excludedPairs[] = ['article' => $row->article, 'kaspi_sku' => $row->kaspi_sku, 'kaspi_name' => $row->kaspi_name];
                unset($grouped[$sku]);
                $incompletePairsCount++;
            }
        }

        if ($incompletePairsCount > 0) {
            $this->warn("⚠️  Исключено как неполная пара (в наличии только одна половина парного кода): {$incompletePairsCount}");

            if ($dryRun) {
                $this->table(
                    ['our_article', 'kaspi_sku', 'kaspi_name'],
                    collect($excludedPairs)->map(fn($p) => [
                        $p['article'], $p['kaspi_sku'], mb_strimwidth($p['kaspi_name'], 0, 80, '…'),
                    ])->toArray()
                );
            }
        }

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

            $isFixedCost = $this->isFixedCost($row->kaspi_name) || $this->isSupplierFixedCost($row->supplier_name, $row->kaspi_name);
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

        $now = now();
        $rowsToUpsert = collect($grouped)->map(function ($row) use ($now) {
            return [
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
                'kaspi_qty'                  => $row->kaspi_qty,
                'qty_suspicious'             => $row->qty_suspicious,
                'is_active'                  => true,
                'last_synced_at'             => $now,
                'updated_at'                 => $now,
                'created_at'                 => $now,
            ];
        })->values();

        foreach ($rowsToUpsert->chunk(500) as $i => $chunk) {
            try {
                DB::table('kaspi_feed_items')->upsert(
                    $chunk->toArray(),
                    ['kaspi_sku'],
                    [
                        'our_article', 'kaspi_name', 'brand', 'purchase_price', 'price', 'stock',
                        'preorder_days', 'supplier_name',
                        'competitors_min_price', 'competitors_tomorrow_count',
                        'competitors_total', 'competitors_parsed_at',
                        'kaspi_qty', 'qty_suspicious',
                        'is_active', 'last_synced_at', 'updated_at',
                    ]
                );
                $upserted += $chunk->count();
                $this->info("Чанк " . ($i + 1) . " из " . $rowsToUpsert->chunk(500)->count() . " записан ({$upserted} всего)");
            } catch (\Exception $e) {
                $this->warn("Ошибка в чанке: " . $e->getMessage());
                Log::error('kaspi:match batch upsert error', ['error' => $e->getMessage()]);
                $skipped += $chunk->count();
            }
        }

        $this->info("Записано/обновлено: {$upserted}");
        if ($skipped > 0) {
            $this->warn("Пропущено с ошибкой: {$skipped} (смотри логи)");
        }

        $this->deactivateStaleFeedItems($grouped, $supplier);

        return 0;
    }

    /**
     * Деактивирует записи kaspi_feed_items, которые раньше были
     * подтверждены матчингом, а в ТЕКУЩЕМ прогоне больше не подбираются
     * запросом в handle() — например, потому что старая коллизия
     * артикулов была почищена и правильный матч ещё не переехал (или
     * артикул вообще больше не проходит фильтры). Без этого шага такие
     * "призраки" остаются в фиде и продолжают продаваться по старой,
     * возможно ошибочной цене/себестоимости бесконечно — именно так и
     * произошло с 187XY32 → kaspi_sku 157021060 (осталось от давнего
     * бага матчинга, хотя kaspi:match давно перестал его подтверждать).
     *
     * КРИТИЧЕСКИ ВАЖНАЯ ЗАЩИТА (добавлена после инцидента 2026-08-06):
     * если в kaspi_initial_products ещё остаётся значительный необработанный
     * остаток (kaspi_parsed=0) — это значит, что kaspi:parse-sku ещё НЕ
     * закончил массовый пересбор, и текущая выборка kaspi_sku_test
     * НЕПОЛНАЯ по определению. Запускать деактивацию "призраков" поверх
     * незавершённых данных смертельно опасно — огромный кусок каталога,
     * который просто ЕЩЁ НЕ УСПЕЛ дойти до переразбора, выглядит как
     * "больше не подтверждается" и массово гасится, хотя ничего не сломано.
     * Именно так один раз погасло ~17000 живых позиций разом. Поэтому —
     * если необработанный остаток превышает порог, деактивацию просто
     * пропускаем целиком и просим сначала дождаться завершения parse-sku.
     */
    const STALE_CLEANUP_UNPARSED_THRESHOLD = 500;

    private function deactivateStaleFeedItems(array $grouped, ?string $supplierFilter): void
    {
        $unparsedCount = DB::table('kaspi_initial_products')->where('kaspi_parsed', 0)->count();

        if ($unparsedCount > self::STALE_CLEANUP_UNPARSED_THRESHOLD) {
            $this->warn("⚠ Пропускаю очистку устаревших записей: в очереди ещё {$unparsedCount} необработанных артикулов (kaspi_parsed=0).");
            $this->warn("  Дождись завершения kaspi:parse-sku, иначе рискуем деактивировать позиции, которые просто ещё не дошли до переразбора.");
            return;
        }

        // Защита от случайного обнуления всего фида, если $grouped вдруг
        // оказался пустым — тогда просто выходим, ничего не трогаем.
        if (empty($grouped)) {
            $this->warn("⚠ Список подтверждённых matches пуст — деактивация устаревших записей пропущена (защита от полного обнуления фида).");
            return;
        }

        $activeKaspiSkus = array_keys($grouped);
        // Быстрый lookup для array_diff по большим массивам
        $activeKaspiSkusFlipped = array_flip($activeKaspiSkus);

        // Вместо NOT IN с десятками тысяч плейсхолдеров — тянем текущие
        // активные kaspi_sku отдельным простым запросом (без огромного
        // биндинга), а разницу считаем в PHP.
        $currentlyActiveQuery = DB::table('kaspi_feed_items')
            ->where('is_active', 1)
            ->when($supplierFilter, fn($q) => $q->where('supplier_name', $supplierFilter))
            ->pluck('kaspi_sku');

        $staleKaspiSkus = [];
        foreach ($currentlyActiveQuery as $sku) {
            if (!isset($activeKaspiSkusFlipped[$sku])) {
                $staleKaspiSkus[] = $sku;
            }
        }

        $staleCount = count($staleKaspiSkus);

        if ($staleCount === 0) {
            return;
        }

        foreach (array_chunk($staleKaspiSkus, 1000) as $chunk) {
            DB::table('kaspi_feed_items')
                ->where('is_active', 1)
                ->when($supplierFilter, fn($q) => $q->where('supplier_name', $supplierFilter))
                ->whereIn('kaspi_sku', $chunk)
                ->update([
                    'is_active'  => 0,
                    'stock'      => 0,
                    'updated_at' => now(),
                ]);
        }

        $this->warn("🧹 Деактивировано устаревших записей в kaspi_feed_items (больше не подтверждаются матчингом): {$staleCount}");
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