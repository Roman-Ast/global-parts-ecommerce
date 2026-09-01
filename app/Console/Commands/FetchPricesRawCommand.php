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

/**
 * Нефильтрованная выкачка прайсов поставщиков — для наполнения контента
 * (GSC), не для продажи. Специально ОТДЕЛЬНАЯ команда, а не режим
 * prices:fetch, по двум причинам:
 *
 * 1. prices:fetch читает только unseen() и помечает письма Seen — если бы
 *    эта команда делала так же, она конкурировала бы с prices:fetch за одни
 *    и те же непрочитанные письма (кто первый запустился, тот и "съел"
 *    письма, второй остался бы ни с чем). Эта команда читает по дате
 *    (--days) и НИЧЕГО не помечает прочитанным — можно перезапускать сколько
 *    угодно раз, не мешая обычному пайплайну.
 * 2. Не трогаем FetchPricesCommand — она в проде и по словам Романа
 *    "отточена", копипаста логики парсеров сюда безопаснее, чем рефакторинг
 *    рабочей боевой команды ради переиспользования кода.
 *
 * Пишет в supplier_offers_raw, не в supplier_offers — эта таблица не влияет
 * на цены/наличие на сайте (тем по-прежнему занимается supplier_offers).
 */
class FetchPricesRawCommand extends Command
{
    protected $signature = 'prices:fetch-raw {--days=90 : читать письма за последние N дней (не только непрочитанные)}';
    protected $description = 'Полная выкачка прайсов поставщиков без отсева по остатку/цене — источник кандидатов для расширенного скрейпа контента Kaspi';

    public function handle()
    {
        $days = (int) $this->option('days');

        $this->info("Подключаемся к почтовому ящику (письма за последние {$days} дн.)...");

        try {
            $client = Client::account('default');
            $client->connect();
            $folder   = $client->getFolder('INBOX');
            $messages = $folder->query()->since(now()->subDays($days))->get();
        } catch (\Exception $e) {
            $this->error('Ошибка IMAP: ' . $e->getMessage());
            return 1;
        }

        if ($messages->count() === 0) {
            $this->info('Писем за указанный период не найдено.');
            return 0;
        }

        $this->info("Найдено писем: " . $messages->count());

        $totalNew = 0;
        $totalUpdated = 0;

        foreach ($messages as $message) {
            $subjectLower   = mb_strtolower($message->getSubject() ?? '');
            $fromEmailLower = '';
            if ($message->getFrom() && $message->getFrom()->count() > 0) {
                $fromEmailLower = mb_strtolower($message->getFrom()->first()->mail ?? '');
            }

            $bodyLower = mb_strtolower($message->getTextBody() ?? '');
            if ($bodyLower === '') {
                $bodyLower = mb_strtolower(strip_tags($message->getHTMLBody() ?? ''));
            }

            $this->info("Обрабатываем письмо: {$message->getSubject()} [От: {$fromEmailLower}]");

            if (!$message->hasAttachments()) {
                $this->line('Вложений нет, пропускаем письмо.');
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
                    [$new, $updated] = $this->processMultiSheetFile($parser, $fullPath, $isXlsx, $isXls, $filename);
                    $totalNew += $new;
                    $totalUpdated += $updated;

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

                $skuListFromPrice = [];

                foreach ($rows as $row) {
                    $parsedData = $parser->parseRow($row);

                    if (!$parsedData) {
                        continue;
                    }

                    // Без отсева по остатку/цене — тут нужны все позиции,
                    // цена/наличие на сайте всё равно решает supplier_offers,
                    // не эта таблица.
                    $qty = (int) preg_replace('/[^0-9]/', '', (string)$parsedData['stock']);
                    $purchasePrice = (float) str_replace([' ', ','], ['', '.'], (string)$parsedData['price']);

                    $skuListFromPrice[] = $parsedData['sku'];

                    $existOffer = DB::table('supplier_offers_raw')
                        ->where('sku', $parsedData['sku'])
                        ->where('supplier_name', $supplierKey)
                        ->first();

                    DB::table('supplier_offers_raw')->updateOrInsert(
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

                $totalNew += $newCount;
                $totalUpdated += $updatedCount;

                $this->info("Файл {$filename} успешно обработан!");
                $this->comment("Добавлено: {$newCount}");
                $this->comment("Обновлено: {$updatedCount}");

                $this->removeStaleOffers($supplierKey, $skuListFromPrice);
            }
        }

        $this->info('==================================================');
        $this->info("Готово. Всего добавлено: {$totalNew}, обновлено: {$totalUpdated}.");

        return 0;
    }

    /**
     * Без отсева по остатку/цене прайсы здесь на порядок больше, чем в
     * supplier_offers (Rossko/TISS — десятки тысяч строк) — whereNotIn() со
     * всем списком сразу упирается в лимит MySQL на плейсхолдеры в
     * prepared statement ("Prepared statement contains too many
     * placeholders"). Поэтому считаем разницу в PHP (текущие в БД минус
     * присланные прайсом) и удаляем только реально устаревшие, чанками.
     */
    private function removeStaleOffers(string $supplierKey, array $skuListFromPrice): void
    {
        if (empty($skuListFromPrice)) {
            $this->warn("  ⚠ Прайс {$supplierKey} не дал ни одной валидной позиции — удаление пропущено (защита от пустого файла).");
            return;
        }

        $existingSkus = DB::table('supplier_offers_raw')
            ->where('supplier_name', $supplierKey)
            ->pluck('sku');

        $staleSkus = $existingSkus->diff($skuListFromPrice)->values();

        if ($staleSkus->isEmpty()) {
            $this->comment("  Удалено офферов {$supplierKey} (исчезли из прайса): 0");
            return;
        }

        $deletedTotal = 0;
        foreach ($staleSkus->chunk(1000) as $chunk) {
            $deletedTotal += DB::table('supplier_offers_raw')
                ->where('supplier_name', $supplierKey)
                ->whereIn('sku', $chunk)
                ->delete();
        }

        $this->comment("  Удалено офферов {$supplierKey} (исчезли из прайса): {$deletedTotal}");
    }

    private function processMultiSheetFile(
        MultiSheetParserInterface $parser,
        string $fullPath,
        bool   $isXlsx,
        bool   $isXls,
        string $filename
    ): array {
        if ($isXlsx) {
            $book = SimpleXLSX::parse($fullPath);
        } elseif ($isXls) {
            $book = SimpleXLS::parse($fullPath);
        } else {
            $this->error("Multi-sheet парсер поддерживает только XLS/XLSX: {$filename}");
            return [0, 0];
        }

        if (!$book) {
            $this->error("Не удалось распарсить книгу: {$filename}");
            return [0, 0];
        }

        $sheetNames   = $book->sheetNames();
        $dataStartRow = $parser->getDataStartRow();

        $totalNew = 0;
        $totalUpdated = 0;

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
            $skuListFromPrice = [];

            foreach ($dataRows as $row) {
                $parsedData = $parser->parseRow($row);

                if (!$parsedData) {
                    continue;
                }

                $qty = (int) preg_replace('/[^0-9]/', '', (string)$parsedData['stock']);
                $purchasePrice = (float) str_replace([' ', ','], ['', '.'], (string)$parsedData['price']);

                $skuListFromPrice[] = $parsedData['sku'];

                $existOffer = DB::table('supplier_offers_raw')
                    ->where('sku', $parsedData['sku'])
                    ->where('supplier_name', $supplierKey)
                    ->first();

                DB::table('supplier_offers_raw')->updateOrInsert(
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
            $this->comment("    Добавлено: {$newCount}");
            $this->comment("    Обновлено: {$updatedCount}");

            $this->removeStaleOffers($supplierKey, $skuListFromPrice);

            $totalNew += $newCount;
            $totalUpdated += $updatedCount;
        }

        return [$totalNew, $totalUpdated];
    }

    // -------------------------------------------------------------------------
    // Ниже — детекция парсера и чтение файлов, скопировано без изменений из
    // FetchPricesCommand (см. комментарий в начале файла про то, почему не
    // общий код).

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
            str_starts_with($filenameLower, 'аст')
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
