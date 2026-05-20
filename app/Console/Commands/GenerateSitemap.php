<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\SlugHelper;

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

        // Захватываем $this явно для использования внутри chunk
        $command = $this;

        DB::table('global_catalog')
            ->select('article', 'clean_article', 'brand')
            ->whereNotNull('article')
            ->where('article', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->chunk($chunkSize, function ($products) use (&$sitemaps, &$fileNum, $baseUrl, $command) {

                $fileName = "sitemap_products_{$fileNum}.xml";
                $filePath = public_path($fileName);

                $handle = fopen($filePath, 'w');
                fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>');
                fwrite($handle, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');

                foreach ($products as $product) {
                    $urlBrand = SlugHelper::brandToSlug($product->brand);

                    $finalArticle = !empty($product->clean_article)
                        ? $product->clean_article
                        : preg_replace('/[^A-Za-z0-9]/', '', $product->article ?? '');

                    // Пропускаем записи без артикула
                    if (empty($finalArticle)) {
                        continue;
                    }

                    $url = $baseUrl . '/product/' . $urlBrand . '/' . $finalArticle;

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