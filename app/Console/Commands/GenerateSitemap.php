<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Генерация раздельных Sitemap для товаров';

    public function handle()
    {
        $this->info('Начинаю генерацию карты сайта...');

        $chunkSize = 40000;
        $baseUrl    = rtrim(config('app.url'), '/');
        $sitemaps   = [];
        $fileNum    = 1;
        $seenUrls   = []; // Дедуп-реестр на ВЕСЬ каталог, а не на один чанк

        // Захватываем $this явно для использования внутри chunk
        $command = $this;

        $skippedEmpty = 0;
        $skippedDup   = 0;

        // Источник — parts_catalog (заскрейпленные с Kaspi карточки), а не
        // устаревший global_catalog. В sitemap попадает только то, что реально
        // просим Google индексировать: scrape_status=done — та же граница,
        // что и в CatalogController::publishedQuery()/GlobalProductController.
        DB::table('parts_catalog')
            ->select('article', 'brand_slug')
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('brand_slug')
            ->where('brand_slug', '!=', '')
            ->orderBy('brand_slug')
            ->chunk($chunkSize, function ($products) use (&$sitemaps, &$fileNum, &$seenUrls, &$skippedEmpty, &$skippedDup, $baseUrl, $command) {

                $fileName = "sitemap_products_{$fileNum}.xml";
                $filePath = public_path($fileName);

                $handle = fopen($filePath, 'w');
                fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
                fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

                foreach ($products as $product) {
                    // Приводим артикул к нижнему регистру — совпадает с логикой
                    // канонического URL в GlobalProductController::showRichCard()
                    $finalArticle = strtolower(trim($product->article));

                    if (empty($finalArticle)) {
                        $skippedEmpty++;
                        continue;
                    }

                    // Дедуп: не пишем повторно уже добавленную комбинацию бренд+артикул
                    $dedupKey = $product->brand_slug . '|' . $finalArticle;
                    if (isset($seenUrls[$dedupKey])) {
                        $skippedDup++;
                        continue;
                    }
                    $seenUrls[$dedupKey] = true;

                    $url = $baseUrl . '/product/' . $product->brand_slug . '/' . $finalArticle;

                    fwrite($handle,
                        '<url>' .
                        '<loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>' .
                        '<changefreq>weekly</changefreq>' .
                        '<priority>0.6</priority>' .
                        '</url>'
                    );
                }

                fwrite($handle, '</urlset>');
                fclose($handle);

                $sitemaps[] = $fileName;
                $command->info("Создан файл: {$fileName}");
                $fileNum++;
            });

        $this->generateIndex($sitemaps, $baseUrl);
        $this->info("Пропущено (пустой артикул): {$skippedEmpty}");
        $this->info("Пропущено (дубли бренд+артикул): {$skippedDup}");
        $this->info('Готово!');
    }

    private function generateIndex(array $sitemaps, string $baseUrl): void
    {
        $handle = fopen(public_path('sitemap.xml'), 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
        fwrite($handle, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

        $lastmod = now()->toAtomString();
        foreach ($sitemaps as $sitemap) {
            fwrite($handle,
                '<sitemap>' .
                '<loc>' . $baseUrl . '/' . $sitemap . '</loc>' .
                '<lastmod>' . $lastmod . '</lastmod>' .
                '</sitemap>'
            );
        }

        fwrite($handle, '</sitemapindex>');
        fclose($handle);

        $this->info('sitemap.xml (индекс) создан.');
    }
}