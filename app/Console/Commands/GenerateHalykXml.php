<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Генерация XML прайс-листа под Halyk Market — по формату из их XSD
 * (namespace halyk_market, см. CLAUDE.md, раздел "Halyk Market"). В отличие
 * от Kaspi, тут нет self-service загрузки — ссылку на этот файл нужно
 * передать персональному менеджеру Halyk, который сам настроит импорт
 * (~раз в час опрос).
 *
 * Источник данных — kaspi_initial_products напрямую (не kaspi_feed_items,
 * не привязка через API halyk:bind): это первый, самый простой фид без
 * per-city цен (одиночный <price>) и без multi-warehouse остатков (один
 * <stock> на точку продаж).
 *
 * <stocks> ОБЯЗАТЕЛЕН несмотря на minOccurs="0" в XSD — сама схема технически
 * это разрешает через offer[@stockLevel] как альтернативу, но по факту без
 * <stocks><stock storeId=.../></stocks> Halyk не считает товар доступным к
 * покупке (обратная связь от их менеджера, 2026-08-21) — комментарий в XSD
 * "should not be used if stockLevel specified inside any of availability-
 * element" на самом деле означает "используй ЛИБО то, ЛИБО другое", но на
 * практике им нужен именно вариант с <stocks>, привязанный к конкретной
 * точке продаж.
 */
class GenerateHalykXml extends Command
{
    protected $signature = 'halyk:generate-xml';

    protected $description = 'Генерация XML прайс-листа под Halyk Market из kaspi_initial_products';

    /**
     * Та же логика, что и EXCLUDED_NAME_PATTERNS в GenerateKaspiXml — набор
     * проблемных/некорректных карточек по названию, не по SKU (артикул
     * может законно встречаться и в нормальных товарах).
     */
    const EXCLUDED_NAME_PATTERNS = [
        ['meyle', 'комплект рычагов'],
    ];

    public function handle(): int
    {
        $this->info('Старт генерации XML фида для Halyk Market...');

        $merchantBin = env('HALYK_MERCHANT_BIN');
        if (!$merchantBin) {
            $this->warn('HALYK_MERCHANT_BIN не задан в .env — в фид уйдёт заглушка вместо реального БИН, Halyk такое не примет. Впиши БИН и перезапусти команду перед тем, как слать ссылку менеджеру.');
        }

        // storeId для <stocks><stock> — тот же код точки продаж, что нужен и
        // для halyk:bind (HALYK_POINT_CODE), просто под другим именем в этой
        // части их API/фида. Отдельная переменная HALYK_STORE_ID — на случай,
        // если на практике окажется другим значением, но по умолчанию берём
        // тот же код точки продаж, раз лично не проверяли, что они разные.
        $storeId = env('HALYK_STORE_ID', env('HALYK_POINT_CODE'));
        if (!$storeId) {
            $this->warn('HALYK_STORE_ID / HALYK_POINT_CODE не заданы в .env — в фид уйдёт заглушка вместо реального storeId, Halyk не примет товар как доступный к покупке без привязки к точке продаж. Впиши код и перезапусти команду перед тем, как слать ссылку менеджеру.');
        }
        $storeId = $storeId ?: 'УКАЖИТЕ_STORE_ID';

        $publicPath = public_path('halyk_feed.xml');

        $xml = new \XMLWriter();
        $xml->openURI($publicPath);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElement('merchant_offers');
        $xml->writeAttribute('date', now()->format('Y-m-d\TH:i:sP'));
        $xml->writeAttribute('xmlns', 'halyk_market');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        $xml->writeElement('company', 'Global Parts');
        $xml->writeElement('merchantid', $merchantBin ?: 'УКАЖИТЕ_БИН');

        $xml->startElement('offers');

        $totalCount    = 0;
        $skippedCount  = 0;
        $excludedCount = 0;

        DB::table('kaspi_initial_products')
            ->where('stock', '>', 0)
            ->where('price', '>', 0)
            ->orderBy('sku')
            ->chunk(1000, function ($items) use ($xml, $storeId, &$totalCount, &$skippedCount, &$excludedCount) {
                foreach ($items as $item) {
                    $name = trim(str_replace(['¶', '"', "'"], '', $item->title));
                    if (empty($name)) {
                        $skippedCount++;
                        continue;
                    }

                    if ($this->isExcludedByName($name)) {
                        $excludedCount++;
                        continue;
                    }

                    // sku — до 64 символов по XSD (Sku simpleType); наши
                    // артикулы короче на практике, substr — только
                    // защитная страховка, не рассчитываем, что сработает.
                    $sku = mb_substr((string) $item->sku, 0, 64);

                    $xml->startElement('offer');
                    $xml->writeAttribute('sku', $sku);

                    // Порядок элементов строго по XSD: model → brand →
                    // barcodes → stocks → deliveryOptions → price|cityprices
                    // → sale_price → loanPeriod. Barcodes/deliveryOptions/
                    // sale_price опциональны — их нет, stocks — обязателен
                    // на практике (см. комментарий класса).
                    $xml->writeElement('model', $name);
                    $xml->writeElement('brand', mb_strtoupper($item->brand));

                    $xml->startElement('stocks');
                    $xml->startElement('stock');
                    $xml->writeAttribute('storeId', $storeId);
                    $xml->writeAttribute('available', $item->stock > 0 ? 'yes' : 'no');
                    $xml->writeAttribute('stockLevel', (int) $item->stock);
                    $xml->endElement(); // stock
                    $xml->endElement(); // stocks

                    $xml->writeElement('price', (int) $item->price);
                    // Минимальный из допустимых периодов рассрочки — не
                    // обсуждали отдельно с Романом, взял как безопасный
                    // дефолт (тот же выбор, что и в halyk:bind).
                    $xml->writeElement('loanPeriod', '3');

                    $xml->endElement(); // offer

                    $totalCount++;
                }

                $this->line("Упаковано: {$totalCount}...");
            });

        $xml->endElement(); // offers
        $xml->endElement(); // merchant_offers
        $xml->endDocument();
        $xml->flush();

        $this->info('=============================================');
        $this->info("Фид сохранён: {$publicPath}");
        $this->info("Товаров в фиде: {$totalCount}");

        if ($skippedCount > 0) {
            $this->warn("Пропущено (пустое название): {$skippedCount}");
        }

        if ($excludedCount > 0) {
            $this->warn("Исключено по названию (EXCLUDED_NAME_PATTERNS): {$excludedCount}");
        }

        return 0;
    }

    private function isExcludedByName(string $name): bool
    {
        $nameLower = mb_strtolower($name);

        foreach (self::EXCLUDED_NAME_PATTERNS as $patternGroup) {
            $allMatch = true;

            foreach ($patternGroup as $keyword) {
                if (!str_contains($nameLower, mb_strtolower($keyword))) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return true;
            }
        }

        return false;
    }
}
