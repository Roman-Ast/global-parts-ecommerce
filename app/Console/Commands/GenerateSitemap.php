<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GlobalCatalog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';
    protected $description = 'Генерация раздельных Sitemap для 184к товаров';

    public function handle()
    {
        $this->info('Начинаю генерацию карты сайта...');

        // Настройки
        $chunkSize = 40000; // По сколько ссылок в одном файле
        $baseUrl = config('app.url'); // Твой домен из .env
        $sitemaps = [];

        // Берем только уникальные Артикул + Бренд, чтобы не плодить дубли
        $query = DB::table('global_catalog')
            ->select('article', 'brand')
            ->distinct();

        $count = 0;
        $fileNum = 1;

        // Используем chunkById или просто chunk для экономии памяти
        $query->orderBy('article')->chunk($chunkSize, function ($products) use (&$sitemaps, &$fileNum, $baseUrl) {
            $fileName = "sitemap_products_{$fileNum}.xml";
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($products as $product) {
                // Формируем ссылку по нашему роуту /product/{brand}/{article}
                $url = $baseUrl . '/product/' . urlencode($product->brand) . '/' . urlencode($product->article);
                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars($url) . '</loc>';
                $xml .= '<changefreq>weekly</changefreq>';
                $xml .= '<priority>0.6</priority>';
                $xml .= '</url>';
            }

            $xml .= '</urlset>';
            
            // Сохраняем файл в папку public
            file_put_contents(public_path($fileName), $xml);
            
            $sitemaps[] = $fileName;
            $this->info("Создан файл: {$fileName}");
            $fileNum++;
        });

        // Создаем главный индексный файл sitemap.xml
        $this->generateIndex($sitemaps, $baseUrl);

        $this->info('Готово! Все файлы созданы в папке public.');
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
