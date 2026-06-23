<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OzonFeedGenerator
{
    public function generate(): string
    {
        $products = DB::table('kaspi_initial_products')
            ->where('stock', '>=', 2)
            ->get();

        $date = now()->format('Y-m-d H:i');

        $xml = new \SimpleXMLElement(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?><yml_catalog date=\"{$date}\"></yml_catalog>"
        );

        $shop = $xml->addChild('shop');

        $offers = $shop->addChild('offers');

        foreach ($products as $product) {
            $offer = $offers->addChild('offer');
            $offer->addAttribute('id', $product->sku);

            $offer->addChild('price', (string)$product->price);

            $outlets = $offer->addChild('outlets');
            $outlet = $outlets->addChild('outlet');
            $outlet->addAttribute('instock', (string)$product->stock);
            $outlet->addAttribute('warehouse_name', 'PP1');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    public function saveToFile(): string
    {
        $content = $this->generate();
        $path = 'ozon_feed.xml';
        \Storage::disk('public')->put($path, $content);
        return \Storage::disk('public')->url($path);
    }
}