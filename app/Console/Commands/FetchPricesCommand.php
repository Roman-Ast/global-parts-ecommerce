<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLS;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use App\Services\PriceParsers\ShatemParser;
use App\Services\PriceParsers\PhaetonParser;
use App\Services\PriceParsers\VoltazhParser;
use App\Services\PriceParsers\RosskoParser;
use App\Services\PriceParsers\ForumAutoParser;
use App\Services\PriceParsers\AutotradeAstParser;
use App\Services\PriceParsers\AutotradeAlmParser;
use App\Services\PriceParsers\KulanParser;
use App\Services\PriceParsers\InterkomParser; 
use App\Services\PriceParsers\ZakazAutoKostanayParser;
use App\Services\PriceParsers\TissParser;
use App\Services\PriceParsers\TstarterParser;
use App\Services\PriceParsers\MultiSheetParserInterface;

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
            $folder   = $client->getFolder('INBOX');
            $messages = $folder->query()->unseen()->get();
        } catch (\Exception $e) {
            $this->error('Ошибка IMAP: ' . $e->getMessage());
            return 1;
        }

        if ($messages->count() === 0) {
            $this->info('Нет новых непрочитанных писем.');
            return 0;
        }

        $this->info("Найдено новых писем: " . $messages->count());

        foreach ($messages as $message) {
            $subjectLower   = mb_strtolower($message->getSubject() ?? '');
            $fromEmailLower = '';
            if ($message->getFrom() && $message->getFrom()->count() > 0) {
                $fromEmailLower = mb_strtolower($message->getFrom()->first()->mail ?? '');
            }

            // --- НОВОЕ: текст письма нужен, чтобы находить оригинального
            // отправителя даже когда письмо переслано вручную (кнопкой
            // «Переслать») — mail.ru вставляет в тело блок
            // "От кого: price@phaeton.kz" / "prices_export@shate-m.com",
            // а заголовок From при этом содержит уже личный e-mail Романа.
            $bodyLower = mb_strtolower($message->getTextBody() ?? '');
            if ($bodyLower === '') {
                $bodyLower = mb_strtolower(strip_tags($message->getHTMLBody() ?? ''));
            }

            $this->info("Обрабатываем письмо: {$message->getSubject()} [От: {$fromEmailLower}]");

            if (!$message->hasAttachments()) {
                $this->line('Вложений нет, пропускаем письмо.');
                $message->setFlag('Seen');
                continue;
            }

            foreach ($message->getAttachments() as $attachment) {
                $filename      = $attachment->getName() ?? 'unknown';
                $filenameLower = mb_strtolower($filename);

                $isXlsx = str_ends_with($filenameLower, '.xlsx');
                $isCsv  = str_ends_with($filenameLower, '.csv');
                $isZip  = str_ends_with($filenameLower, '.zip');
                $isXls  = str_ends_with($filenameLower, '.xls');

                if (!$isXlsx && !$isCsv && !$isZip && !$isXls) {
                    continue;
                }

                [$parser, $supplierKey] = $this->detectParser(
                    $subjectLower,
                    $fromEmailLower,
                    $filenameLower,
                    $bodyLower
                );

                if (!$parser) {
                    $this->line("Поставщик не распознан для файла «{$filename}», пропускаем.");
                    continue;
                }

                $this->info("Скачиваем вложение: {$filename}...");

                // --- СКАЧИВАНИЕ (общее для всех парсеров) ---
                $localStoragePath = 'tmp/' . $filename;
                Storage::disk('local')->put($localStoragePath, $attachment->getContent());
                $fullPath = storage_path('app/' . $localStoragePath);

                $extractedFullPath = null;

                if ($isZip) {
                    [$fullPath, $extractedFullPath, $isXlsx, $isCsv, $isXls] = $this->extractFromZip(
                        $fullPath,
                        $localStoragePath
                    );

                    if ($fullPath === null) {
                        Storage::disk('local')->delete($localStoragePath);
                        continue;
                    }
                }

                if ($parser instanceof MultiSheetParserInterface) {
                    $this->processMultiSheetFile($parser, $fullPath, $isXlsx, $isXls, $filename);

                    Storage::disk('local')->delete($localStoragePath);
                    if ($extractedFullPath && file_exists($extractedFullPath)) {
                        unlink($extractedFullPath);
                    }

                    continue;
                }

                $rows = $this->readRows($fullPath, $isXlsx, $isCsv, $isXls, $filename);

                Storage::disk('local')->delete($localStoragePath);
                if ($extractedFullPath && file_exists($extractedFullPath)) {
                    unlink($extractedFullPath);
                }

                if ($rows === null) {
                    continue;
                }

                $newCount     = 0;
                $updatedCount = 0;
                $skippedCount = 0;

                $skuListFromPrice = [];

                foreach ($rows as $row) {
                    $parsedData = $parser->parseRow($row);

                    if (!$parsedData) {
                        continue;
                    }

                    $qty = (int) preg_replace('/[^0-9]/', '', (string)$parsedData['stock']);
                    if ($qty < 2) {
                        $skippedCount++;
                        continue;
                    }

                    $purchasePrice = (float) str_replace([' ', ','], ['', '.'], (string)$parsedData['price']);
                    if ($purchasePrice < 5000) {
                        $skippedCount++;
                        continue;
                    }

                    $skuListFromPrice[] = $parsedData['sku'];

                    $existOffer = DB::table('supplier_offers')
                        ->where('sku', $parsedData['sku'])
                        ->where('supplier_name', $supplierKey)
                        ->first();

                    DB::table('supplier_offers')->updateOrInsert(
                        [
                            'sku'           => $parsedData['sku'],
                            'supplier_name' => $supplierKey,
                        ],
                        [
                            'title'          => $parsedData['title'],
                            'brand'          => mb_strtolower($parsedData['brand']),
                            'purchase_price' => $purchasePrice,
                            'stock'          => $qty,
                            'preorder_days'  => $parsedData['preorder_days'] ?? 0,
                            'updated_at'     => now(),
                            'created_at'     => $existOffer ? $existOffer->created_at : now(),
                        ]
                    );

                    $existOffer ? $updatedCount++ : $newCount++;
                }

                $this->info("Файл {$filename} успешно обработан!");
                $this->comment("Пропущено по фильтрам: {$skippedCount}");
                $this->comment("Добавлено:            {$newCount}");
                $this->comment("Обновлено:            {$updatedCount}");

                $this->removeStaleOffers($supplierKey, $skuListFromPrice);
            }

            try {
                $message->setFlag('Seen');
            } catch (\Exception $e) {
                $this->warn("  ⚠ Не удалось пометить письмо как прочитанное: " . $e->getMessage());
            }
        }

        $this->info('==================================================');
        $this->info('Импорт прайсов завершён. Запускаем агрегацию лучших предложений...');

        \Illuminate\Support\Facades\Artisan::call('offers:aggregate', [], $this->getOutput());

        $this->info('Агрегация завершена.');

        return 0;
    }

    private function removeStaleOffers(string $supplierKey, array $skuListFromPrice): void
    {
        if (empty($skuListFromPrice)) {
            $this->warn("  ⚠ Прайс {$supplierKey} не дал ни одной валидной позиции — удаление пропущено (защита от пустого файла).");
            return;
        }

        $deleted = DB::table('supplier_offers')
            ->where('supplier_name', $supplierKey)
            ->whereNotIn('sku', $skuListFromPrice)
            ->delete();

        $this->comment("  Удалено офферов {$supplierKey} (исчезли из прайса): {$deleted}");
    }

    private function processMultiSheetFile(
        MultiSheetParserInterface $parser,
        string $fullPath,
        bool   $isXlsx,
        bool   $isXls,
        string $filename
    ): void {
        if ($isXlsx) {
            $book = SimpleXLSX::parse($fullPath);
        } elseif ($isXls) {
            $book = SimpleXLS::parse($fullPath);
        } else {
            $this->error("Multi-sheet парсер поддерживает только XLS/XLSX: {$filename}");
            return;
        }

        if (!$book) {
            $this->error("Не удалось распарсить книгу: {$filename}");
            return;
        }

        $sheetNames   = $book->sheetNames();
        $dataStartRow = $parser->getDataStartRow();

        foreach ($parser->getAllowedSheets() as $sheetName) {
            $sheetIndex = array_search($sheetName, $sheetNames, true);

            if ($sheetIndex === false) {
                $this->warn("  Лист «{$sheetName}» не найден в файле {$filename}, пропускаем.");
                continue;
            }

            $supplierKey = $parser->resolveSupplierName($sheetName);

            $allRows  = $book->rows($sheetIndex);
            $dataRows = array_slice($allRows, $dataStartRow);

            $newCount         = 0;
            $updatedCount     = 0;
            $skippedCount     = 0;
            $skuListFromPrice = [];

            foreach ($dataRows as $row) {
                $parsedData = $parser->parseRow($row);

                if (!$parsedData) {
                    continue;
                }

                $qty = (int) preg_replace('/[^0-9]/', '', (string)$parsedData['stock']);
                if ($qty < 2) {
                    $skippedCount++;
                    continue;
                }

                $purchasePrice = (float) str_replace([' ', ','], ['', '.'], (string)$parsedData['price']);
                if ($purchasePrice < 5000) {
                    $skippedCount++;
                    continue;
                }

                $skuListFromPrice[] = $parsedData['sku'];

                $existOffer = DB::table('supplier_offers')
                    ->where('sku', $parsedData['sku'])
                    ->where('supplier_name', $supplierKey)
                    ->first();

                DB::table('supplier_offers')->updateOrInsert(
                    [
                        'sku'           => $parsedData['sku'],
                        'supplier_name' => $supplierKey,
                    ],
                    [
                        'title'          => $parsedData['title'],
                        'brand'          => mb_strtolower($parsedData['brand']),
                        'purchase_price' => $purchasePrice,
                        'stock'          => $qty,
                        'preorder_days'  => $parsedData['preorder_days'] ?? 0,
                        'updated_at'     => now(),
                        'created_at'     => $existOffer ? $existOffer->created_at : now(),
                    ]
                );

                $existOffer ? $updatedCount++ : $newCount++;
            }

            $this->info("  Лист «{$sheetName}» → {$supplierKey}");
            $this->comment("    Пропущено: {$skippedCount}");
            $this->comment("    Добавлено: {$newCount}");
            $this->comment("    Обновлено: {$updatedCount}");

            $this->removeStaleOffers($supplierKey, $skuListFromPrice);
        }
    }

    // -------------------------------------------------------------------------

   private function detectParser(
        string $subjectLower,
        string $fromEmailLower,
        string $filenameLower,
        string $bodyLower = ''
    ): array {
        if (
            (str_contains($filenameLower, 'zakazauto') || str_contains($subjectLower, 'zakazauto') || str_contains($subjectLower, 'заказавто'))
            && (str_contains($filenameLower, 'kostanay') || str_contains($filenameLower, 'костанай') || str_contains($filenameLower, 'kst'))
        ) {
            $this->comment('Выбран парсер: ЗаказАвто Костанай');
            return [new ZakazAutoKostanayParser(), 'zakazauto_kst'];
        }

        // Шатэм (Shate-M Plus). Реальный домен price@shate-m.com отправитель
        // пишет как "shate-m.com". Тема реально приходит как "Шате-М" (через
        // дефис), а не "Шатэм" как в старом варианте, поэтому оставлены оба.
        // $bodyLower — самый надёжный сигнал: при пересылке письма вручную
        // (Fwd:) mail.ru вставляет в тело "От кого: prices_export@shate-m.com",
        // и это остаётся стабильным даже если поставщик поменяет тему письма.
        if (
            str_contains($bodyLower,      'shate-m.com') ||
            str_contains($subjectLower,   'shatem')      ||
            str_contains($subjectLower,   'шатэм')       ||
            str_contains($subjectLower,   'шате-м')      ||
            str_contains($subjectLower,   'shate-m')     ||
            str_contains($fromEmailLower, 'shate-m.com') ||
            str_contains($filenameLower,  'shate-m')     ||
            str_contains($filenameLower,  'shatem')
        ) {
            $this->comment('Выбран парсер: Шатэм');
            return [new ShatemParser(), 'shatem'];
        }

        // Phaeton — аналогично, $bodyLower ловит "От кого: price@phaeton.kz"
        // из пересланного письма даже если From-заголовок = личная почта.
        if (
            str_contains($bodyLower,      'phaeton.kz') ||
            str_contains($subjectLower,   'phaeton')    ||
            str_contains($subjectLower,   'фаэтон')     ||
            str_contains($fromEmailLower, 'phaeton.kz') ||
            str_contains($filenameLower,  'phaeton')
        ) {
            $this->comment('Выбран парсер: Фаэтон');
            return [new PhaetonParser(), 'phaeton'];
        }

        // Tstarter проверяем ДО АвтоТрейд Алматы/Астана — иначе письма из
        // алматинского филиала Tstarter (almaty@tstarter.ru) ложно матчатся
        // на АвтоТрейд Алматы.
        if (
            str_contains($subjectLower,   'tstarter')      ||
            str_contains($subjectLower,   'т-стартер')     ||
            str_contains($subjectLower,   'транс стартер') ||
            str_contains($fromEmailLower, 'tstarter.ru')   ||
            str_contains($filenameLower,  'tstarter')
        ) {
            $this->comment('Выбран парсер: Транс Стартер (Tstarter)');
            return [new TstarterParser(), 'tstarter'];
        }

        if (
            str_contains($subjectLower,   'алматы')        ||
            str_contains($subjectLower,   'almaty')         ||
            // Убрали str_contains($fromEmailLower, 'almaty') — ненадёжно,
            // т.к. у Tstarter тоже есть филиал с почтой almaty@tstarter.ru
            str_contains($filenameLower,  'алм')            ||
            str_contains($filenameLower,  'autotrade_alm')
        ) {
            $this->comment('Выбран парсер: АвтоТрейд Алматы');
            return [new AutotradeAlmParser(), 'autotrade_alm'];
        }

        if (
            str_contains($subjectLower,   'автотрейд')      ||
            str_contains($subjectLower,   'avtotrade')      ||
            str_contains($fromEmailLower, 'avtotrade')      ||
            str_contains($fromEmailLower, 'автотрейд')      ||
            str_starts_with($filenameLower, 'аст')           // было 'аст_'/'аст '/'autotrade_ast' — требовало разделитель
        ) {
            $this->comment('Выбран парсер: АвтоТрейд Астана');
            return [new AutotradeAstParser(), 'autotrade_ast'];
        }

        if (
            str_contains($subjectLower,   'вольтаж')    ||
            str_contains($subjectLower,   'voltazh')     ||
            str_contains($subjectLower,   'voltaj')      ||
            str_contains($fromEmailLower, 'voltazh')     ||
            str_contains($filenameLower,  'вольтаж')    ||
            str_contains($filenameLower,  'voltazh')     ||
            str_contains($filenameLower,  'склад_вольт')
        ) {
            $this->comment('Выбран парсер: Вольтаж');
            return [new VoltazhParser(), 'voltazh'];
        }

        if (
            str_contains($fromEmailLower, 'kulanoil') ||
            str_contains($subjectLower,   'kulan')    ||
            str_contains($filenameLower,  'kulan')
        ) {
            $this->comment('Выбран парсер: Кулан');
            return [new KulanParser(), 'kln'];
        }

        // Интерком. ВАЖНО: письмо Роман пересылает вручную с личной почты
        // (кнопкой «Переслать»), поэтому From-заголовок = его личный e-mail,
        // а тема письма — произвольная ("Прайс от 28.07.2026"), без слова
        // "interkom"/"интерком" вообще. Имя вложения тоже не содержит его
        // ("Прайс00-00045418.XLS.xls"). Единственный стабильный сигнал —
        // домен реального отправителя ("info@interkom.kz"), который остаётся
        // в теле пересланного письма ("От: Интерком <info@interkom.kz>") —
        // тот же самый приём, что уже работает для Шатэм/Фаэтон выше.
        // Без этой проверки приходилось вручную переименовывать файл перед
        // пересылкой, чтобы имя содержало "interkom".
        if (
            str_contains($bodyLower,      'interkom.kz') ||
            str_contains($fromEmailLower, 'interkom.kz') ||
            str_contains($filenameLower,  'interkom')    ||
            str_contains($filenameLower,  'интерком')
        ) {
            $this->comment('Выбран парсер: Интерком');
            return [new InterkomParser(), 'interkom'];
        }

        if (str_contains($filenameLower, 'forum') || str_contains($filenameLower, 'форум')) {
            if (str_contains($filenameLower, ' лп') || str_contains($filenameLower, '_лп')) {
                $this->comment('Выбран парсер: Forum Auto ЛП (по имени файла)');
                return [new ForumAutoParser(), 'forumauto_lp'];
            }
            if (str_contains($filenameLower, ' гп') || str_contains($filenameLower, '_гп')) {
                $this->comment('Выбран парсер: Forum Auto ГП (по имени файла)');
                return [new ForumAutoParser(), 'forumauto_gp'];
            }
            $this->comment('Выбран парсер: Forum Auto (суффикс не указан, по умолчанию ЛП)');
            return [new ForumAutoParser(), 'forumauto_lp'];
        }

        if (
            str_contains($subjectLower,   'rossko')    ||
            str_contains($subjectLower,   'росско')    ||
            str_contains($fromEmailLower, 'rossko')    ||
            str_contains($filenameLower,  'rossko')    ||
            str_contains($filenameLower,  'росско')
        ) {
            $this->comment('Выбран парсер: Росско');
            return [new RosskoParser(), 'rossko'];
        }

        if (
            str_contains($fromEmailLower, 'tabys.parts') ||
            str_contains($subjectLower,   'тисс')        ||
            str_contains($subjectLower,   'tiss')        ||
            str_contains($filenameLower,  'тисс')        ||
            str_contains($filenameLower,  'tiss')
        ) {
            $this->comment('Выбран парсер: TISS');
            return [new TissParser(), 'tiss'];
        }

        return [null, ''];
    }

    private function extractFromZip(string $zipFullPath, string $localStoragePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFullPath) !== true) {
            $this->error("Не удалось открыть ZIP: " . basename($zipFullPath));
            return [null, null, false, false, false];
        }

        $extractedFullPath = null;
        $innerFilename     = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $innerName      = $zip->getNameIndex($i);
            $innerNameLower = mb_strtolower($innerName);

            if (
                str_ends_with($innerNameLower, '.csv') ||
                str_ends_with($innerNameLower, '.xlsx') ||
                str_ends_with($innerNameLower, '.xls')
            ) {
                $innerFilename     = basename($innerName);
                $extractedFullPath = storage_path('app/tmp/' . $innerFilename);

                file_put_contents($extractedFullPath, $zip->getFromIndex($i));
                break;
            }
        }

        $zip->close();

        if (!$extractedFullPath) {
            $this->error("В ZIP нет CSV/XLSX/XLS: " . basename($zipFullPath));
            return [null, null, false, false, false];
        }

        $innerNameLower = mb_strtolower($innerFilename);
        $isXlsx = str_ends_with($innerNameLower, '.xlsx');
        $isCsv  = str_ends_with($innerNameLower, '.csv');
        $isXls  = str_ends_with($innerNameLower, '.xls');

        return [$extractedFullPath, $extractedFullPath, $isXlsx, $isCsv, $isXls];
    }

    private function readRows(
        string $fullPath,
        bool   $isXlsx,
        bool   $isCsv,
        bool   $isXls,
        string $displayName
    ): ?array {
        if ($isXlsx) {
            $xlsx = SimpleXLSX::parse($fullPath);
            if (!$xlsx) {
                $this->error("Не удалось распарсить XLSX: {$displayName}");
                return null;
            }
            $rows = $xlsx->rows();
            unset($rows[0]);
            return array_values($rows);
        }

        if ($isXls) {
            $xls = SimpleXLS::parse($fullPath);
            if (!$xls) {
                $this->error("Не удалось распарсить XLS: {$displayName}");
                return null;
            }
            $rows = $xls->rows();
            unset($rows[0]);
            return array_values($rows);
        }

        if ($isCsv) {
            $handle = fopen($fullPath, 'r');
            if (!$handle) {
                $this->error("Не удалось открыть CSV: {$displayName}");
                return null;
            }
            fgetcsv($handle, 0, ';');
            $rows = [];
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        }

        $this->error("Неизвестный формат файла: {$displayName}");
        return null;
    }
}