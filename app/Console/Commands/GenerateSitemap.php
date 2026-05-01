<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Генерация раздельных Sitemap для 184к товаров с использованием чистых артикулов';

    public function handle()
    {
        $this->info('Начинаю генерацию карты сайта на основе clean_article...');

        // Настройки
        $chunkSize = 40000; 
        $baseUrl = config('app.url'); 
        $sitemaps = [];

        // Берем article (для подстраховки), clean_article и brand
        // Добавляем фильтр, чтобы не брать совсем пустые записи
        $query = DB::table('global_catalog')
            ->select('article', 'clean_article', 'brand')
            ->whereNotNull('article')
            ->distinct();

        $fileNum = 1;

        // Сортируем по id или бренду для стабильности чанков
        $query->orderBy('brand')->chunk($chunkSize, function ($products) use (&$sitemaps, &$fileNum, $baseUrl) {
            $fileName = "sitemap_products_{$fileNum}.xml";
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($products as $product) {
                // ПРИОРpriority: используем clean_article, если он есть
                $finalArticle = !empty($product->clean_article) 
                    ? $product->clean_article 
                    : preg_replace('/[^A-Za-z0-9]/', '', $product->article);

                // Формируем ЧИСТУЮ ссылку без мусора
                $url = $baseUrl . '/product/' . urlencode($product->brand) . '/' . urlencode($finalArticle);
                
                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars($url) . '</loc>';
                $xml .= '<changefreq>weekly</changefreq>';
                $xml .= '<priority>0.6</priority>';
                $xml .= '</url>';
            }

            $xml .= '</urlset>';
            
            file_put_contents(public_path($fileName), $xml);
            
            $sitemaps[] = $fileName;
            $this->info("Создан файл: {$fileName} (ссылки очищены)");
            $fileNum++;
        });

        $this->generateIndex($sitemaps, $baseUrl);

        $this->info('Готово! Теперь все ссылки в сайтмапе канонические и без тире.');
    }

    private function generateIndex($sitemaps, $baseUrl)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= '<sitemap>';
            $xml .= '<loc>' . $baseUrl . '/' . $sitemap . '</loc>';
            $xml .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
            $xml .= '</sitemap>';
        }

        $xml .= '</sitemapindex>';
        file_put_contents(public_path('sitemap.xml'), $xml);
    }
}
