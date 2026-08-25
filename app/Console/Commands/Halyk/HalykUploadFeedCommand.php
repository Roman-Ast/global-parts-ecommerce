<?php

namespace App\Console\Commands\Halyk;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;

/**
 * Загружает public/halyk_feed.xml (см. GenerateHalykXml) через реальный
 * upload-эндпоинт Halyk вместо ручной передачи ссылки менеджеру.
 * Первая живая попытка — 2026-08-25, до этого момента загрузка фида
 * происходила только вручную на стороне Halyk по присланной ссылке, наш
 * код только генерировал файл.
 */
class HalykUploadFeedCommand extends Command
{
    protected $signature = 'halyk:upload-feed {--no-regenerate : не перегенерировать фид перед загрузкой, взять файл как есть}';

    protected $description = 'Загружает public/halyk_feed.xml через API Halyk (POST /offers/upload)';

    public function handle(HalykMarketClient $client): int
    {
        $path = public_path('halyk_feed.xml');

        if (!$this->option('no-regenerate')) {
            $this->info('Перегенерирую фид перед загрузкой (halyk:generate-xml)...');
            $this->call('halyk:generate-xml');
        }

        if (!file_exists($path)) {
            $this->error("Файл не найден: {$path}. Сначала прогони halyk:generate-xml.");
            return 1;
        }

        $this->info('Загружаю ' . round(filesize($path) / 1024 / 1024, 2) . ' МБ...');

        $result = $client->uploadFeed($path);

        if (!$result['ok']) {
            $this->error('Загрузка не удалась: HTTP ' . $result['status'] . ' — ' . json_encode($result['body'], JSON_UNESCAPED_UNICODE));
            return 1;
        }

        $uploadId = $result['body']['id'] ?? null;

        if (!$uploadId) {
            $this->error('Загрузка отвечала успехом, но id не найден в ответе: ' . json_encode($result['body'], JSON_UNESCAPED_UNICODE));
            return 1;
        }

        $this->info("Загружено, id={$uploadId}. Статус: php artisan halyk:check-feed-status {$uploadId}");

        return 0;
    }
}
