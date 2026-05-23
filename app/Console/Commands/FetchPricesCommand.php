<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\KaspiPriceCalculator;

// Импортируем наши парсеры
use App\Services\PriceParsers\ShatemParser;
use App\Services\PriceParsers\PhaetonParser;
use App\Services\PriceParsers\ZakazAutoParser;


class FetchPricesCommand extends Command
{
    protected $signature = 'prices:fetch';
    protected $description = 'Чтение прайсов с почты, фильтрация по остаткам и минимальной цене через специализированные парсеры';

    public function handle()
    {
        $this->info('Подключаемся к почтовому ящику...');
        
        try {
            $client = Client::account('default');
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();
        } catch (\Exception $e) {
            $this->error('Ошибка ИМАП: ' . $e->getMessage());
            return 1;
        }

        if ($messages->count() === 0) {
            $this->info('Нет новых непрочитанных писем.');
            return 0;
        }

        $this->info("Найдено новых писем: " . $messages->count());

        foreach ($messages as $message) {
            // 1. Берем тему письма в нижнем регистре
            $subject = mb_strtolower($message->getSubject());
            
            // 2. Берем email отправителя в нижнем регистре (Безопасный дефолт, если пусто)
            $fromEmail = '';
            if ($message->getFrom() && $message->getFrom()->count() > 0) {
                $fromEmail = mb_strtolower($message->getFrom()->first()->mail ?? '');
            }

            $this->info("Обрабатываем письмо: {$message->getSubject()} [От: {$fromEmail}]");

            // ОПРЕДЕЛЯЕМ ПАРСЕР (И по теме, и по email отправителя!)
            $parser = null;

            // Проверка для Шатэм
            if (str_contains($subject, 'shatem') || str_contains($subject, 'шатэм') || str_contains($fromEmail, 'shate-m.com')) {
                $this->comment('Выбран парсер: Шатэм');
                $parser = new ShatemParser();
                
            // Проверка для Фаэтон
            } elseif (str_contains($subject, 'phaeton') || str_contains($subject, 'фаэтон') || str_contains($fromEmail, 'phaeton.kz')) {
                $this->comment('Выбран парсер: Фаэтон');
                $parser = new PhaetonParser();
                
            // Проверка для ЗаказАвто
            } elseif (str_contains($subject, 'zakaz') || str_contains($subject, 'заказ') || str_contains($fromEmail, 'zakaz')) {
                $this->comment('Выбран парсер: ЗаказАвто');
                $parser = new ZakazAutoParser();
            }

            // Если письмо не относится ни к одному поставщику — пропускаем его
            if (!$parser) {
                $this->line('Поставщик не распознан, пропускаем письмо.');
                continue;
            }

            if ($message->hasAttachments()) {
                foreach ($message->getAttachments() as $attachment) {
                    $filename = $attachment->getName();
                    
                    if (!str_ends_with(mb_strtolower($filename), '.xlsx')) {
                        continue;
                    }

                    $this->info("Скачиваем вложение: {$filename}...");
                    $localPath = 'tmp/' . $filename;
                    Storage::disk('local')->put($localPath, $attachment->getContent());
                    $fullPath = storage_path('app/' . $localPath);

                    if ($xlsx = SimpleXLSX::parse($fullPath)) {
                        $rows = $xlsx->rows();
                        unset($rows[0]); // Удаляем шапку

                        $newCount = 0;
                        $updatedCount = 0;
                        $skippedCount = 0;

                        foreach ($rows as $row) {
                            // 1. ПЕРЕДАЕМ СТРОКУ В ВЫБРАННЫЙ ПАРСЕР
                            $parsedData = $parser->parseRow($row);

                            // Если парсер вернул null (пустая строка или шапка) — скипаем
                            if (!$parsedData) {
                                continue;
                            }

                            // 2. ОБЩАЯ ОЧИСТКА И ФИЛЬТРАЦИЯ ДАННЫХ ДЛЯ ВСЕХ ПОСТАВЩИКОВ

                            // Чистим и приводим количество к числу
                            $qty = (int)preg_replace('/[^0-9]/', '', $parsedData['stock']);

                            // ГЛОБАЛЬНЫЙ ФИЛЬТР №1: Остаток меньше 2 штук — откидываем риск отмены
                            if ($qty < 2) {
                                $skippedCount++;
                                continue;
                            }

                            // Чистим цену закупа от пробелов и мусора
                            $rawPrice = str_replace([' ', ','], ['', '.'], $parsedData['price']);
                            $purchasePrice = (float)$rawPrice;

                            // ГЛОБАЛЬНЫЙ ФИЛЬТР №2: Закупка меньше 10 000 тенге — откидываем мелочевку
                            if ($purchasePrice < 10000) {
                                $skippedCount++;
                                continue;
                            }

                            // НАЦЕНКА ДЛЯ КАСПИ (например, чистые +15% и округление вверх до целого)
                            $retailPrice = KaspiPriceCalculator::calculate($purchasePrice);

                            // Ищем, есть ли уже этот товар в базе (чтобы не затереть ручные 78 позиций)
                            $existProduct = DB::table('kaspi_initial_products')
                                ->where('sku', $parsedData['sku'])
                                ->first();

                            if ($existProduct) {
                                // Если это твои ручные 78 позиций (10 дней) ИЛИ уже записанный Костанай (7 дней),
                                // и при этом текущий парсер пытается пропихнуть этот же товар как "в наличии" (0 дней),
                                // мы просто игнорируем дни предзаказа от поставщика, оставляя те, что уже сохранены в базе!
                                $daysToUpdate = $existProduct->preorder_days > 0 
                                    ? $existProduct->preorder_days 
                                    : ($parsedData['preorder_days'] ?? 0);

                                DB::table('kaspi_initial_products')
                                    ->where('id', $existProduct->id)
                                    ->update([
                                        'title'         => $parsedData['title'],
                                        'brand'         => mb_strtolower($parsedData['brand']),
                                        'price'         => $retailPrice,
                                        'stock'         => $qty,
                                        'preorder_days' => $daysToUpdate, // Сохраняем приоритет предзаказа
                                        'updated_at'    => now()
                                    ]);
                                $updatedCount++;
                            } else {
                                // Если товара вообще нет в базе — создаем новую запись
                                DB::table('kaspi_initial_products')->insert([
                                    'sku'           => $parsedData['sku'],
                                    'title'         => $parsedData['title'],
                                    'brand'         => mb_strtolower($parsedData['brand']),
                                    'category_code' => null,
                                    'price'         => $retailPrice,
                                    'stock'         => $qty,
                                    'preorder_days' => $parsedData['preorder_days'] ?? 0, 
                                    'created_at'    => now(),
                                    'updated_at'    => now()
                                ]);
                                $newCount++;
                            }
                        }
                        
                        $this->info("Файл {$filename} успешно обработан!");
                        $this->comment("Пропущено по фильтрам (остаток < 2 или закуп < 10к): {$skippedCount}");
                        $this->comment("Добавлено новых позиций: {$newCount}");
                        $this->comment("Обновлено позиций: {$updatedCount}");
                    }
                    
                    Storage::delete($localPath);
                }
            }
            // Помечаем письмо прочитанным
            $message->setFlag('Seen');
        }

        $this->info('==================================================');
        $this->info('Импорт прайсов завершен. Запускаем обновление XML фида...');
        
        // Автоматически запускаем генерацию упрощенного XML
        \Illuminate\Support\Facades\Artisan::call('kaspi:generate-xml');
        
        $this->info('XML-фид для Каспи успешно перегенерирован!');
        return 0;
    }
}