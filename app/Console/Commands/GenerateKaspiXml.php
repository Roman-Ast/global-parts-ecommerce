<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateKaspiXml extends Command
{
    // Имя команды для запуска через консоль
    protected $signature = 'kaspi:generate-xml';
    protected $description = 'Генерация упрощенного XML прайс-листа цен и остатков для Каспи';

    public function handle()
    {
        $this->info('Старт генерации упрощенного XML прайса...');

        $fileName = 'kaspi_feed.xml';
        $publicPath = public_path($fileName);

        // Используем XMLWriter для потоковой записи, чтобы 250к+ строк не перегрузили RAM
        $xml = new \XMLWriter();
        $xml->openURI($publicPath);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        // Корневой тег Каспи прайс-листа
        $xml->startElement('kaspi_catalog');
        $xml->writeAttribute('date', now()->format('Y-m-d H:i'));
        $xml->writeAttribute('xmlns', 'http://kaspi.kz/kaspicatalog/3.0');

        // Данные компании
        $xml->writeElement('company', 'Global Parts'); 
        $xml->writeElement('merchantid', 'GlobalPartsKz'); 

        $xml->startElement('offers');

        $totalCount = 0;

        // Выбираем ВСЕ товары, где есть цена и остаток (категории теперь НЕ требуются)
        DB::table('kaspi_initial_products')
            ->where('price', '>', 0)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->chunkById(1000, function ($products) use ($xml, &$totalCount) {
                foreach ($products as $product) {
                    
                    // Очищаем название от технических символов логов Шатэма (типа ¶)
                    $cleanTitle = str_replace(['¶', '"', "'"], '', $product->title);

                    // Открываем блок товара. Атрибут sku — обязательный уникальный ID
                    $xml->startElement('offer');
                    $xml->writeAttribute('sku', $product->sku); 

                    // Внутренние обязательные теги по спецификации упрощенного прайса
                    $xml->writeElement('model', $cleanTitle);
                    $xml->writeElement('brand', \Illuminate\Support\Str::upper($product->brand));
                    $xml->writeElement('price', number_format($product->price, 0, '.', ''));

                    // Блок складов и доступности
                    $xml->startElement('availabilities');
                    
                    $xml->startElement('availability');
                    $xml->writeAttribute('available', 'yes');
                    
                    // !!! ВАЖНО: Сюда впиши ID твоего склада в Астане из кабинета Каспи (вместо PP1)
                    $xml->writeAttribute('storeId', 'PP1'); 

                    // Умный предзаказ для твоих 60 карточек:
                    // Если в базе у товара preorder_days > 0 (например, 10), запишется предзаказ.
                    // Если там 0, атрибут не создается, и Каспи включит твои "1.5 часа" для Астаны!
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