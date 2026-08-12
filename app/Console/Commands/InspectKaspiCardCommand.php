<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InspectKaspiCardCommand extends Command
{
    protected $signature = 'kaspi:inspect-card {sku}';
    protected $description = 'Скачивает сырой HTML карточки Kaspi и сохраняет для разбора структуры';

    public function handle(): int
    {
        $sku = $this->argument('sku');
        $url = "https://kaspi.kz/shop/p/-{$sku}/";

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'ru,ru-RU;q=0.9',
        ])->timeout(15)->get($url);

        if (!$response->ok()) {
            $this->error("HTTP {$response->status()} для {$url}");
            return 1;
        }

        $html = $response->body();
        $path = storage_path("app/tmp/card_{$sku}.html");
        file_put_contents($path, $html);

        $this->info("Сохранено: {$path} (" . strlen($html) . " байт)");

        // Пробуем найти известные точки входа embedded-state
        foreach (['__NEXT_DATA__', '__INITIAL_STATE__', '"specifications"', '"images"', '"galleryImages"', 'window.__'] as $needle) {
            $pos = strpos($html, $needle);
            $this->line($needle . ': ' . ($pos !== false ? "найдено на позиции {$pos}" : 'не найдено'));
        }

        return 0;
    }
}