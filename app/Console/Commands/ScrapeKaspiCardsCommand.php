<?php

namespace App\Console\Commands;

use App\Models\PartsCatalog;
use App\Services\KaspiCardScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScrapeKaspiCardsCommand extends Command
{
    protected $signature = 'parts:scrape-cards
        {--source= : own|competitor, по умолчанию любые}
        {--limit=200 : сколько карточек обработать за прогон, 0 = без ограничения (вся очередь)}
        {--sku= : обработать конкретный kaspi_sku (для отладки)}
        {--rescrape-done : пересобрать уже готовые (done) карточки заново, а не только pending — нужно один раз после добавления новых полей вроде category_path}';

    protected $description = 'Скрапит публичные карточки Kaspi для позиций parts_catalog со статусом pending';

    public function handle(KaspiCardScraper $scraper): int
    {
        $limitOption = (int) $this->option('limit');
        $unlimited = $limitOption === 0;
        $rescrapeDone = (bool) $this->option('rescrape-done');

        $query = $rescrapeDone
            ? PartsCatalog::whereIn('scrape_status', ['pending', 'done'])
            : PartsCatalog::where('scrape_status', 'pending');

        if ($source = $this->option('source')) {
            $query->where('source', $source);
        }
        if ($sku = $this->option('sku')) {
            $query->where('source_kaspi_sku', $sku);
        }

        $totalPending = (clone $query)->count();

        if ($totalPending === 0) {
            $this->info('Очередь пуста.');
            return self::SUCCESS;
        }

        $toProcess = $unlimited ? $totalPending : min($limitOption, $totalPending);
        $this->info("В очереди: {$totalPending}. Будет обработано в этом прогоне: {$toProcess}.");

        $bar = $this->output->createProgressBar($toProcess);
        [$done, $notFound, $failed] = [0, 0, 0];
        $processed = 0;

        // Работаем пачками по 200, чтобы не держать в памяти сразу всю очередь
        // (актуально при --limit=0 на десятках тысяч записей) и чтобы каждая
        // пачка видела актуальный scrape_status (предыдущая уже проставлена).
        //
        // ВАЖНО при --rescrape-done: пачка берёт uже обработанные (done) строки
        // ПОВТОРНО каждый раз через (clone $query)->limit($batchSize), т.к. их
        // scrape_status не меняется на что-то, что убрало бы их из выборки —
        // поэтому дополнительно отслеживаем processed ID, чтобы не зациклиться
        // на одних и тех же первых $batchSize записях.
        $processedIds = [];

        while ($unlimited || $processed < $limitOption) {
            $batchQuery = (clone $query);
            if (!empty($processedIds)) {
                $batchQuery->whereNotIn('id', $processedIds);
            }

            $batchSize = $unlimited ? 200 : min(200, $limitOption - $processed);
            $cards = $batchQuery->orderBy('id')->limit($batchSize)->get();

            if ($cards->isEmpty()) {
                break; // очередь реально закончилась
            }

            foreach ($cards as $card) {
                try {
                    $data = $scraper->fetchCard($card->source_kaspi_sku);

                    $characteristics = $data['characteristics'] ?? [];
                    $characteristics['category_path']       = $data['category_path'] ?? [];
                    $characteristics['category_leaf_title'] = $data['category_leaf_title'] ?? null;
                    $characteristics['category_leaf_code']  = $data['category_leaf_code'] ?? null;

                    $card->update([
                        'name' => $data['name'] ?? $card->name,
                        'brand' => $data['brand'] ?? $card->brand,
                        'description' => $data['description'],
                        'characteristics' => $characteristics,
                        'applicability' => $data['applicability'],
                        'images' => $data['images'],
                        'scrape_status' => 'done',
                        'scraped_at' => now(),
                    ]);
                    $done++;
                } catch (\App\Exceptions\KaspiCardNotFoundException $e) {
                    $card->update(['scrape_status' => 'not_found', 'scraped_at' => now()]);
                    $notFound++;
                } catch (\Throwable $e) {
                    $card->update(['scrape_status' => 'failed', 'scraped_at' => now()]);
                    Log::channel('kaspi_scrape')->error($e->getMessage(), ['sku' => $card->source_kaspi_sku]);
                    $failed++;
                }

                $processedIds[] = $card->id;
                $bar->advance();
                $processed++;
                usleep(random_int(400_000, 1_000_000)); // джиттер, чтобы не забанили по IP
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("done: {$done}, not_found: {$notFound}, failed: {$failed}");

        
        $remaining = (clone $query)->whereNotIn('id', $processedIds)->count();
        if ($remaining > 0) {
            $this->comment("Осталось в очереди: {$remaining}. Запусти команду снова для продолжения.");
        }

        return self::SUCCESS;
    }
}