<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateKaspiXml extends Command
{
    protected $signature = 'kaspi:generate-xml';
    protected $description = 'Генерация упрощенного XML прайс-листа цен и остатков для Каспи';

    public function handle()
    {
        $this->info('Старт генерации XML фида...');

        $publicPath = public_path('kaspi_feed.xml');

$xml = new \XMLWriter();
$xml->openURI($publicPath);
$xml->startDocument('1.0', 'UTF-8');
$xml->setIndent(true);

$xml->startElement('kaspi_catalog');
$xml->writeAttribute('date', now()->format('Y-m-d H:i'));
$xml->writeAttribute('xmlns', 'kaspiShopping');
$xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
$xml->writeAttribute('xsi:schemaLocation', 'kaspiShopping http://kaspi.kz/kaspishopping.xsd');

$xml->writeElement('company', 'Global Parts');
$xml->writeElement('merchantid', 'GlobalPartsKz');

$xml->startElement('offers');

$totalCount   = 0;
$skippedCount = 0;

DB::table('kaspi_feed_items')
    ->select([
        'kaspi_sku',
        'kaspi_name',
        DB::raw('MIN(price) as price'),
        DB::raw('MAX(stock) as stock'),
        DB::raw('MIN(preorder_days) as preorder_days'),
        DB::raw('MAX(brand) as brand'),
    ])
    ->where('is_active', true)
    ->where('stock', '>=', 2)
    ->where('price', '>', 0)
    ->groupBy('kaspi_sku', 'kaspi_name')
    ->orderBy('kaspi_sku')
    ->chunk(1000, function ($items) use ($xml, &$totalCount, &$skippedCount) {
        foreach ($items as $item) {

            $name = trim(str_replace(['¶', '"', "'"], '', $item->kaspi_name));
            if (empty($name)) {
                $skippedCount++;
                continue;
            }

            $xml->startElement('offer');
            $xml->writeAttribute('sku', $item->kaspi_sku);

            $xml->writeElement('model', $name);
            $xml->writeElement('brand', mb_strtoupper($item->brand));

            $xml->startElement('availabilities');
            $xml->startElement('availability');
            $xml->writeAttribute('available', 'yes');
            $xml->writeAttribute('storeId', 'PP1');
            
            if ($item->preorder_days > 0) {
                $xml->writeAttribute('preOrder', $item->preorder_days);
            }
            $xml->endElement(); // availability
            $xml->endElement(); // availabilities

            $xml->writeElement('price', (int) $item->price);

            $xml->endElement(); // offer

            $totalCount++;
        }

        $this->line("Упаковано: {$totalCount}...");
    });

        $xml->endElement(); // offers
        $xml->endElement(); // kaspi_catalog
        $xml->endDocument();
        $xml->flush();

        $this->info('=============================================');
        $this->info("Фид сохранён: {$publicPath}");
        $this->info("Товаров в фиде: {$totalCount}");

        if ($skippedCount > 0) {
            $this->warn("Пропущено (пустое название): {$skippedCount}");
        }

        return 0;
    }
}