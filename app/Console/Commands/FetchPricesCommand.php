<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Shuchkin\SimpleXLSX;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\KaspiInitialProduct; // Убедись, что модель называется так и у неё в $fillable есть 'kaspi_code'

class FetchPricesCommand extends Command
{
    protected $signature = 'prices:fetch';
    protected $description = 'Чтение прайсов с почты, динамический мэтчинг категорий Каспи из БД, расчет дельты изменений и синхронизация с очередью выгрузки';

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

        // КЭШ ПРАВИЛ КАТЕГОРИЙ: Вытягиваем правила из БД один раз перед всеми циклами,
        // чтобы не насиловать базу SQL-запросами внутри перебора сотен тысяч строк Excel
        $categoryRules = DB::table('kaspi_category_rules')->pluck('category_code', 'keyword')->toArray();

        foreach ($messages as $message) {
            $sender = $message->getFrom()[0]->mail;
            $this->info("--------------------------------------------------");
            $this->info("Найдено письмо от: {$sender}");

            if ($message->hasAttachments()) {
                foreach ($message->getAttachments() as $attachment) {
                    $filename = $attachment->getName();
                    $lowercaseFilename = mb_strtolower($filename);
                    
                    // Проверяем расширение Excel
                    if (str_ends_with($lowercaseFilename, '.xlsx') || str_ends_with($lowercaseFilename, '.xls')) {
                        $this->info("Обнаружен файл прайса: {$filename}");
                        
                        // Сохраняем файл во временное хранилище Laravel для парсинга
                        $localPath = 'temp_prices/' . $filename;
                        Storage::put($localPath, $attachment->getContent());
                        
                        $absolutePath = Storage::path($localPath);

                        if ($xlsx = SimpleXLSX::parse($absolutePath)) {
                            $rows = $xlsx->rows();
                            
                            $newCount = 0;
                            $updatedCount = 0;
                            $queuedCount = 0;

                            foreach ($rows as $index => $row) {
                                if ($index === 0) continue; // Пропускаем шапку таблицы

                                // Базовая очистка данных из Excel строк
                                $article = isset($row[0]) ? trim($row[0]) : '';
                                $brand   = isset($row[1]) ? trim($row[1]) : '';
                                $name    = isset($row[2]) ? trim($row[2]) : '';
                                $price   = isset($row[3]) ? floatval($row[3]) : 0.0;

                                if (empty($article) || empty($brand)) continue;

                                // 1. ДИНАМИЧЕСКИЙ МЭТЧИНГ КАТЕГОРИИ
                                $kaspiCategoryCode = null;
                                $itemNameLower = mb_strtolower($name);

                                foreach ($categoryRules as $keyword => $code) {
                                    if (str_contains($itemNameLower, $keyword)) {
                                        $kaspiCategoryCode = $code;
                                        break; // Категория найдена, выходим из перебора правил для этой строки
                                    }
                                }

                                // ЖЕСТКОЕ ОТСЕЧЕНИЕ: Категории нет в БД правил -> пропускаем товар, не спамим базу мусором
                                if (!$kaspiCategoryCode) {
                                    continue;
                                }

                                // 2. ПРОВЕРКА ДЕЛЬТЫ ИЗМЕНЕНИЙ (Сверяем с текущей базой KaspiInitialProduct)
                                $existingProduct = KaspiInitialProduct::where('sku', $article)
                                    ->where('brand', $brand)
                                    ->first();

                                if ($existingProduct) {
                                    // Сверяем, изменилась ли цена или привязалась новая категория
                                    $isPriceChanged = (int)$existingProduct->price !== (int)$price;
                                    $isCategoryChanged = $existingProduct->kaspi_code !== $kaspiCategoryCode;

                                    if ($isPriceChanged || $isCategoryChanged) {
                                        // Обновляем данные в основной таблице продуктов
                                        $existingProduct->update([
                                            'price'      => $price,
                                            'kaspi_code' => $kaspiCategoryCode,
                                            'title'      => strlen($name) > strlen($existingProduct->title) ? $name : $existingProduct->title
                                        ]);
                                        $updatedCount++;

                                        // СИНХРОНИЗАЦИЯ С ОЧЕРЕДЬЮ: пушим дельту на обновление в Каспи
                                        DB::table('kaspi_update_queue')->updateOrInsert(
                                            ['sku' => $article, 'brand' => $brand],
                                            [
                                                'price'      => $price,
                                                'kaspi_code' => $kaspiCategoryCode,
                                                'action'     => 'update',
                                                'updated_at' => now()
                                            ]
                                        );
                                        $queuedCount++;
                                    }
                                } else {
                                    // ТОВАРА НЕТ В БАЗЕ: Создаем новую уникальную карточку с категорией
                                    KaspiInitialProduct::create([
                                        'sku'        => $article,
                                        'brand'      => $brand,
                                        'title'      => $name,
                                        'price'      => $price,
                                        'kaspi_code' => $kaspiCategoryCode,
                                    ]);
                                    $newCount++;

                                    // СИНХРОНИЗАЦИЯ С ОЧЕРЕДЬЮ: пушим как абсолютно новый товар для Каспи
                                    DB::table('kaspi_update_queue')->updateOrInsert(
                                        ['sku' => $article, 'brand' => $brand],
                                        [
                                            'price'      => $price,
                                            'kaspi_code' => $kaspiCategoryCode,
                                            'action'     => 'insert',
                                            'updated_at' => now()
                                        ]
                                    );
                                    $queuedCount++;
                                }
                            }
                            
                            $this->info("Файл {$filename} успешно обработан!");
                            $this->comment("Добавлено новых валидных товаров: {$newCount}");
                            $this->comment("Обновлено цен/категорий в каталоге: {$updatedCount}");
                            $this->comment("Отправлено измененных позиций (дельта) в kaspi_update_queue: {$queuedCount}");
                            
                        } else {
                            $this->error("Ошибка парсинга SimpleXLSX: " . SimpleXLSX::parseError());
                        }

                        // Удаляем временный файл прайса за собой, чтобы не засорять диск под Ubuntu
                        Storage::delete($localPath);
                    }
                }
            }

            // Помечаем письмо прочитанным на почтовом сервере
            $message->setFlag('Seen');
        }

        $this->info('==================================================');
        $this->info('Импорт прайсов и синхронизация очередей завершены успешно!');
        return 0;
    }
}