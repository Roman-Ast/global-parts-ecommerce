<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class FetchPricesCommand extends Command
{
    protected $signature = 'prices:fetch';
    protected $description = 'Чтение прайсов с почты, фильтрация по остаткам и минимальной цене, расчет розницы и обновление kaspi_initial_products';

    public function handle()
    {
        $this->info('Подключаемся к почтовому ящику...');
        
        try {
            $client = Client::account('default');
            $client->connect();
            $folder = $client->getFolder('INBOX');
            
            // Берем только непрочитанные письма
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
            $subject = $message->getSubject();
            $this->info("Обрабатываем письмо: {$subject}");

            // Проверяем, что письмо пришло от нужного поставщика (например, Шатэм)
            if (str_contains(mb_strtolower($subject), 'shatem') || str_contains(mb_strtolower($subject), 'шатэм')) {
                
                if ($message->hasAttachments()) {
                    $attachments = $message->getAttachments();
                    
                    foreach ($attachments as $attachment) {
                        $filename = $attachment->getName();
                        
                        // Обрабатываем только файлы Excel (.xlsx)
                        if (!str_ends_with(mb_strtolower($filename), '.xlsx')) {
                            continue;
                        }

                        $this->info("Найдено вложение: {$filename}. Скачиваем...");
                        
                        // Сохраняем файл во временное хранилище под Ubuntu
                        $fileContent = $attachment->getContent();
                        $localPath = 'tmp/' . $filename;
                        Storage::disk('local')->put($localPath, $fileContent);
                        $fullPath = storage_path('app/' . $localPath);

                        $this->info("Парсим файл через SimpleXLSX...");

                        if ($xlsx = SimpleXLSX::parse($fullPath)) {
                            $rows = $xlsx->rows();
                            
                            // Пропускаем шапку таблицы (первую строку)
                            unset($rows[0]);

                            $newCount = 0;
                            $updatedCount = 0;
                            $skippedCount = 0;

                            foreach ($rows as $row) {
                                // Зависит от структуры колонок твоего поставщика. 
                                // Предположим базовый вариант: 0 - SKU, 1 - Артикул, 2 - Бренд, 3 - Название, 4 - Цена закупа, 5 - Количество
                                $sku          = trim($row[0] ?? '');
                                $article      = trim($row[1] ?? '');
                                $brand        = trim($row[2] ?? '');
                                $title        = trim($row[3] ?? '');
                                $price        = trim($row[4] ?? 0); // Изначальная закупочная цена
                                $qty          = trim($row[5] ?? 0);

                                // Защита от пустых строк
                                if (empty($sku) || empty($article) || empty($brand)) {
                                    continue;
                                }

                                // Чистим количество от знаков типа ">", "более" и приводим к числу
                                $qty = (int)preg_replace('/[^0-9]/', '', $qty);

                                // === ПОПРАВКА №1: Если количество менее 2 штук — жестко пропускаем товар ===
                                if ($qty < 2) {
                                    $skippedCount++;
                                    continue;
                                }

                                // Чистим цену закупа от пробелов и мусора
                                $price = (float)str_replace([' ', ','], ['', '.'], $price);

                                // === ПОПРАВКА №2: Если изначальная закупочная цена поставщика менее 10 000 тенге — откидываем ===
                                if ($price < 10000) {
                                    $skippedCount++;
                                    continue;
                                }

                                // НАЦЕНКА: Считаем розничную цену для Каспи (твоя формула наценки, например +15%)
                                // Можешь заменить этот расчет на свою функцию, которая у тебя использовалась
                                $retailPrice = ceil($price * 1.15); 

                                // Ищем, есть ли уже этот товар в таблице Каспи
                                $existProduct = DB::table('kaspi_initial_products')
                                    ->where('sku', $sku)
                                    ->first();

                                if ($existProduct) {
                                    // Если товар уже есть — обновляем цену и остаток
                                    DB::table('kaspi_initial_products')
                                        ->where('id', $existProduct->id)
                                        ->update([
                                            'title'      => $title,
                                            'brand'      => mb_strtolower($brand),
                                            'price'      => $retailPrice,
                                            'stock'      => $qty,
                                            'updated_at' => now()
                                        ]);
                                    $updatedCount++;
                                } else {
                                    // Если товара нет — создаем новую запись
                                    DB::table('kaspi_initial_products')->insert([
                                        'sku'           => $sku,
                                        'title'         => $title,
                                        'brand'         => mb_strtolower($brand),
                                        'category_code' => null, // для упрощенного XML пока пишем NULL
                                        'price'         => $retailPrice,
                                        'stock'         => $qty,
                                        'preorder_days' => 0, // Обычный товар, в наличии в Астане (выдача 1.5 часа)
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
                            
                        } else {
                            $this->error("Ошибка парсинга SimpleXLSX: " . SimpleXLSX::parseError());
                        }

                        // Удаляем временный файл Excel за собой, чтобы не забивать диск Ubuntu
                        Storage::delete($localPath);
                    }
                }
            }

            // Помечаем письмо прочитанным на почтовом сервере
            $message->setFlag('Seen');
        }

        $this->info('==================================================');
        $this->info('Импорт прайсов завершен. Запускаем обновление XML фида...');
        
        // Автоматически запускаем нашу команду генерации упрощенного XML
        \Illuminate\Support\Facades\Artisan::call('kaspi:generate-xml');
        
        $this->info('XML-фид для Каспи успешно перегенерирован!');
        return 0;
    }
}