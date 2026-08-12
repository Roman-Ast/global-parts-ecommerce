<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\KaspiPriceCalculator;

class AggregateSupplierOffersCommand extends Command
{
    protected $signature = 'offers:aggregate';
    protected $description = 'Синхронизирует kaspi_initial_products с лучшими (минимальными по цене среди прошедших фильтр по остатку) предложениями из supplier_offers. Позиции avtozakup не трогаются.';

    const MIN_STOCK = 2;
    const MIN_PRICE = 5000;
    const PROTECTED_SUPPLIER = 'avtozakup';

    // SKU, которые нужно навсегда исключить из выдачи Kaspi
    // (мусорные/тестовые/неверно определённые позиции)
    const EXCLUDED_SKUS = [
        'stsdr10000r',
        '55499413',
        '21101-1139009-00',
        '25196115',
        '2123-1139009-2',
        '2121-1803020',
        '4401301RUEDR',
        'AFU-M-P510',
    ];

    /**
     * Известные варианты написания ОДНОГО И ТОГО ЖЕ бренда у разных
     * поставщиков — НЕ путать со случаями вроде ремней (7PK1905 и т.п.),
     * где разные бренды (Gates/Masuma/Mitsuboshi) действительно выпускают
     * РАЗНЫЕ товары под совпадающим по размеру номером — там композитный
     * ключ (sku, brand) абсолютно правильно держит их разделёнными.
     *
     * Здесь — обратный случай: LEMFOERDER / LEMFORDER / LMI — это
     * официально один и тот же бренд (LMI — альтернативная маркировка
     * LEMFÖRDER, входящего в группу ZF). Без нормализации эти три
     * написания конкурировали бы друг с другом как "разные бренды" в
     * PARTITION BY (sku, brand), искусственно расщепляя один физический
     * товар на 3 записи — и минимум 2 из 3 с высокой вероятностью не
     * найдут свою карточку на Kaspi, потому что написание бренда у
     * поставщика не совпадёт с тем, что указано на самой карточке.
     *
     * Ключ — канонiчное (итоговое) название бренда, значение — список
     * альтернативных написаний, которые нужно СХЛОПНУТЬ в канонiчное
     * перед группировкой по (sku, brand).
     */
    const BRAND_ALIASES = [
        'lemforder' => ['lemfoerder', 'lmi'],
    ];

    /**
     * Строит SQL CASE-выражение, приводящее сырой brand к канонiчному
     * написанию согласно BRAND_ALIASES. Если алиасов нет — просто
     * LOWER(brand) без изменений.
     */
    private function buildBrandNormalizeCase(): string
    {
        if (empty(self::BRAND_ALIASES)) {
            return 'LOWER(eligible.brand)';
        }

        $case = 'CASE ';
        foreach (self::BRAND_ALIASES as $canonical => $aliases) {
            $inList = implode(',', array_map(
                fn($a) => "'" . addslashes(mb_strtolower($a)) . "'",
                $aliases
            ));
            $case .= "WHEN LOWER(eligible.brand) IN ({$inList}) THEN '" . addslashes(mb_strtolower($canonical)) . "' ";
        }
        $case .= 'ELSE LOWER(eligible.brand) END';

        return $case;
    }

    public function handle(): int
    {
        $this->info('Выбираем лучшие предложения по каждому SKU...');
        $placeholders = implode(',', array_fill(0, count(self::EXCLUDED_SKUS), '?'));

        /**
         * ВАЖНО: PARTITION BY идёт по (sku, brand), а НЕ только по sku.
         * Иначе разные производители со случайно совпавшим числовым кодом
         * (например, INKO "231073" = вал приводной, но CARGO "231073" =
         * бендикс стартера — два совершенно разных товара) конкурируют
         * между собой по цене, и дешёвый, но физически ДРУГОЙ товар
         * побеждает и вытесняет правильный. Таблица kaspi_initial_products
         * и так имеет UNIQUE KEY (sku, brand) — она задумана хранить оба
         * бренда как отдельные записи; ошибка была именно в группировке
         * здесь, которая схлопывала их в одного "победителя" до того,
         * как бренд вообще принимался во внимание.
         *
         * Тай-брейк внутри ОДНОГО (sku, brand) — по цене, затем по
         * preorder_days (быстрее доставка при равной цене), затем по id.
         */
        $brandNormalizeExpr = $this->buildBrandNormalizeCase();

        $bestOffers = DB::select("
            WITH ranked AS (
                SELECT
                    eligible.sku,
                    eligible.supplier_name,
                    eligible.title,
                    {$brandNormalizeExpr} AS brand,
                    eligible.purchase_price,
                    eligible.stock,
                    eligible.preorder_days,
                    ROW_NUMBER() OVER (
                        PARTITION BY eligible.sku, {$brandNormalizeExpr}
                        ORDER BY eligible.purchase_price ASC, eligible.preorder_days ASC, eligible.id ASC
                    ) AS rn
                FROM supplier_offers eligible
                WHERE eligible.stock >= ?
                  AND eligible.purchase_price >= ?
                  AND eligible.supplier_name != ?
                  AND eligible.sku NOT IN ($placeholders)
            )
            SELECT sku, supplier_name, title, brand, purchase_price, stock, preorder_days
            FROM ranked
            WHERE rn = 1
        ", array_merge(
            [self::MIN_STOCK, self::MIN_PRICE, self::PROTECTED_SUPPLIER], self::EXCLUDED_SKUS
        ));

        $this->info('Прошедших фильтр SKU: ' . count($bestOffers));

        // --- Предзагрузка существующих sku+brand/supplier_name одним запросом,
        // чтобы не делать SELECT на каждую строку в цикле ---
        // ВАЖНО: ключ карты теперь составной (sku+"\u0001"+brand), а не
        // просто sku — раз таблица допускает несколько строк с одним sku
        // у разных брендов (см. комментарий у ROW_NUMBER выше), просто sku
        // как ключ схлопывал бы их и терял информацию о конкретном бренде.
        $existingMap = [];
        DB::table('kaspi_initial_products')
            ->select('sku', 'brand', 'supplier_name')
            ->orderBy('sku')
            ->chunkById(5000, function ($rows) use (&$existingMap) {
                foreach ($rows as $row) {
                    $existingMap[$this->compositeKey($row->sku, $row->brand)] = $row->supplier_name;
                }
            }, 'sku');

        $newCount         = 0;
        $updatedCount     = 0;
        $skippedProtected = 0;
        $activeKeys       = []; // составные ключи sku+brand, а не просто sku
        $upsertRows       = [];
        $now              = now();

        foreach ($bestOffers as $offer) {
            $key = $this->compositeKey($offer->sku, mb_strtolower($offer->brand));
            $existingSupplier = $existingMap[$key] ?? null;

            // avtozakup — защищённые заказные позиции, не трогаем вообще
            if ($existingSupplier === self::PROTECTED_SUPPLIER) {
                $activeKeys[] = $key;
                $skippedProtected++;
                continue;
            }

            $activeKeys[] = $key;

            $purchasePrice = (float) $offer->purchase_price;
            $qty           = (int) $offer->stock;
            $retailPrice   = (float) KaspiPriceCalculator::calculate($purchasePrice);

            $upsertRows[] = [
                'sku'            => $offer->sku,
                'title'          => $offer->title,
                'brand'          => mb_strtolower($offer->brand),
                'category_code'  => null,
                'purchase_price' => $purchasePrice,
                'price'          => $retailPrice,
                'stock'          => $qty,
                'supplier_name'  => $offer->supplier_name,
                'preorder_days'  => (int) $offer->preorder_days,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            if ($existingSupplier === null) {
                $newCount++;
            } else {
                $updatedCount++;
            }
        }

        // --- Батч upsert чанками по 500, вместо отдельного insert/update на строку ---
        // Conflict-ключ явно (sku, brand) — соответствует реальному
        // UNIQUE KEY таблицы; раньше здесь было только ['sku'], что вводило
        // в заблуждение при чтении кода (MySQL всё равно ориентируется на
        // объявленный в таблице уникальный ключ, а не на этот параметр, но
        // явное указание верного ключа делает код correct-by-inspection).
        foreach (array_chunk($upsertRows, 500) as $chunk) {
            DB::table('kaspi_initial_products')->upsert(
                $chunk,
                ['sku', 'brand'],
                [
                    'title', 'category_code', 'purchase_price', 'price',
                    'stock', 'supplier_name', 'preorder_days', 'updated_at',
                ]
            );
        }

        $this->info("Добавлено:  {$newCount}");
        $this->info("Обновлено:  {$updatedCount}");
        $this->info("Защищено (avtozakup, не тронуто): {$skippedProtected}");

        $this->removeStaleProducts($activeKeys);

        $this->info('Готово.');
        return 0;
    }

    /**
     * Формирует составной ключ для карт/сравнений — sku и brand ВМЕСТЕ
     * идентифицируют товар, раз одна и та же числовая/буквенная строка sku
     * может принадлежать разным производителям с совершенно разными
     * физическими товарами (см. комментарий у ROW_NUMBER выше). Отдельный
     * непечатный разделитель "\u0001" практически исключает случайную
     * коллизию ключей (в отличие от, например, простой конкатенации через
     * "|", которая теоретически могла бы совпасть, если бы такой символ
     * встретился в реальном sku).
     */
    private function compositeKey(string $sku, string $brand): string
    {
        return $sku . "\u{0001}" . mb_strtolower($brand);
    }

    private function removeStaleProducts(array $activeKeys): void
    {
        if (empty($activeKeys)) {
            $this->warn('  ⚠ Нет ни одной активной позиции — удаление пропущено (защита от пустого результата).');
            return;
        }

        // array_flip даёт O(1) проверку через isset() вместо O(N) через in_array()
        $activeKeysMap = array_flip($activeKeys);

        $staleIds = collect();

        // ВАЖНО: сравниваем по составному ключу (sku+brand), а не по
        // одному sku — иначе, например, актуальная запись INKO/231073
        // могла бы ошибочно "защитить" от удаления устаревшую запись
        // CARGO/231073 (или наоборот), раз они делят один и тот же sku,
        // но физически разные товары с независимым жизненным циклом
        // в прайсах поставщиков.
        DB::table('kaspi_initial_products')
            ->where('supplier_name', '!=', self::PROTECTED_SUPPLIER)
            ->select('id', 'sku', 'brand')
            ->orderBy('id')
            ->chunkById(2000, function ($products) use ($activeKeysMap, $staleIds) {
                foreach ($products as $product) {
                    $key = $this->compositeKey($product->sku, $product->brand);
                    if (!isset($activeKeysMap[$key])) {
                        $staleIds->push($product->id);
                    }
                }
            });

        if ($staleIds->isEmpty()) {
            $this->comment('  Исчезнувших позиций: 0');
            return;
        }

        $this->comment('  Исчезнувших позиций: ' . $staleIds->count());

        // Для деактивации в kaspi_feed_items нужен sku (по нему там связь
        // через our_article) — подтягиваем sku по собранным id отдельно,
        // чтобы не городить сложный JOIN внутри chunk-колбэка выше.
        $staleSkusForFeed = DB::table('kaspi_initial_products')
            ->whereIn('id', $staleIds)
            ->pluck('sku')
            ->unique()
            ->values();

        $staleSkusForFeed->chunk(2000)->each(function ($chunk) {
            $deactivated = DB::table('kaspi_feed_items')
                ->whereIn('our_article', $chunk)
                ->update([
                    'is_active'  => 0,
                    'stock'      => 0,
                    'updated_at' => now(),
                ]);

            if ($deactivated > 0) {
                $this->comment("  Деактивировано в kaspi_feed_items: {$deactivated}");
            }
        });

        $deletedTotal = 0;
        $staleIds->chunk(2000)->each(function ($chunk) use (&$deletedTotal) {
            $deletedTotal += DB::table('kaspi_initial_products')
                ->whereIn('id', $chunk)
                ->delete();
        });

        $this->comment("  Удалено из kaspi_initial_products: {$deletedTotal}");
    }
}