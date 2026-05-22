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
        $this->info('Старт генерации упрощенного XML прайса...');

        $fileName = 'kaspi_feed.xml';
        $publicPath = public_path($fileName);

        $xml = new \XMLWriter();
        $xml->openURI($publicPath);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        $xml->startElement('kaspi_catalog');
        $xml->writeAttribute('date', now()->format('Y-m-d H:i'));
        $xml->writeAttribute('xmlns', 'http://kaspi.kz/kaspicatalog/3.0');
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->writeAttribute('xsi:schemaLocation', 'http://kaspi.kz/kaspicatalog/3.0 http://kaspi.kz/kaspicatalog/3.0/kaspicatalog.xsd');

        $xml->startElement('offers');

        $totalCount = 0;

        DB::table('kaspi_initial_products')
            ->where('price', '>', 0)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunkById(1000, function ($products) use ($xml, &$totalCount) {
                foreach ($products as $product) {

                    $cleanTitle = str_replace(['¶', '"', "'"], '', $product->title);

                    $xml->startElement('offer');
                    $xml->writeAttribute('sku', $product->sku);

                    $xml->writeElement('model', $cleanTitle);
                    $xml->writeElement('brand', Str::upper($product->brand));
                    $xml->writeElement('price', number_format($product->price, 0, '.', ''));

                    $xml->startElement('availabilities');

                    $xml->startElement('availability');
                    $xml->writeAttribute('available', 'yes');
                    $xml->writeAttribute('storeId', 'PP1');

                    if (isset($product->preorder_days) && $product->preorder_days > 0) {
                        $xml->writeAttribute('preOrder', $product->preorder_days);
                    }

                    $xml->endElement(); // availability
                    $xml->endElement(); // availabilities
                    $xml->endElement(); // offer

                    $totalCount++;
                }

                $this->line("Упаковано товаров в XML: {$totalCount}...");
            });

        $xml->endElement(); // offers
        $xml->endElement(); // kaspi_catalog
        $xml->endDocument();
        $xml->flush();

        $this->info("=============================================");
        $this->info("Успех! Сводный прайс-лист сохранен: {$publicPath}");
        $this->info("Всего предложений отправлено на Каспи: {$totalCount}");

        return 0;
    }
}