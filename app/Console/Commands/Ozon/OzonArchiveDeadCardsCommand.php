<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Архивирует на Ozon мёртвые слоты, которым не удалось подобрать замену
 * (просьба Романа 2026-10-04, чистка каталога под Rossko/Shatem) —
 * последний шаг этой чистки, добавлен после живого открытия, что
 * "заменить контент" физически не работает для подавляющего большинства
 * карточек: Ozon блокирует смену и категории/типа, и бренда у карточек,
 * перенесённых из другого кабинета (clone_change_category_not_allowed /
 * clone_change_brand_not_allowed — см. докблок OzonReplaceDeadCardsCommand).
 * Замена сработала только там, где нашёлся кандидат ТОГО ЖЕ бренда и
 * той же суб-категории — на практике это закрыло малую часть ~1100+
 * мёртвых слотов.
 *
 * Для всего остального правильный и достижимый итог — НЕ оставлять
 * мёртвый контент активным (риск продать то, чего нет у Rossko/Shatem —
 * ровно тот инцидент с бампером от Автотрейд-Алматы, из-за которого вся
 * эта чистка затевалась), а снять с продажи через /v1/product/archive.
 * Архивная карточка не продаётся и не возвращается в выдачу, но слот
 * (offer_id/product_id) не удаляется физически — если однажды у
 * Rossko/Shatem снова появится эта позиция, ozon:fix-to-rossko-shatem/
 * ozon:sync-price-stock могут её реактивировать (Ozon поддерживает
 * разархивирование через /v1/product/unarchive, отдельно не
 * реализовано — не просили).
 */
class OzonArchiveDeadCardsCommand extends Command
{
    protected $signature = 'ozon:archive-dead-cards {--limit=2000} {--dry-run}';

    protected $description = 'Архивирует на Ozon мёртвые слоты (нет Rossko/Shatem, замена не нашлась)';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $deadSlots = $this->pickDeadSlots($limit);

        if ($deadSlots->isEmpty()) {
            $this->info('Нет мёртвых слотов для архивации.');
            return 0;
        }

        $this->info("Мёртвых слотов к архивации: {$deadSlots->count()}");

        $archived = 0;
        $alreadyArchived = 0;
        $missing = 0;
        $failed = 0;

        foreach ($deadSlots->chunk(100) as $chunk) {
            $byOfferId = $chunk->keyBy('offer_id');

            try {
                $items = $client->productInfoBatch($byOfferId->keys()->all());
            } catch (\Throwable $e) {
                $this->error("  ⨯ батч productInfoBatch упал: {$e->getMessage()}");
                continue;
            }

            foreach ($byOfferId as $offerId => $row) {
                $item = $items[$offerId] ?? null;

                if (!$item) {
                    DB::table('ozon_created_cards')->where('id', $row->id)->update([
                        'status' => 'missing',
                        'comment' => 'Реально отсутствует на Ozon (архивация мёртвых слотов 2026-10-04)',
                        'updated_at' => now(),
                    ]);
                    $missing++;
                    continue;
                }

                if (!empty($item['is_archived'])) {
                    DB::table('ozon_created_cards')->where('id', $row->id)->update([
                        'status' => 'archived',
                        'ozon_product_id' => $item['id'] ?? null,
                        'comment' => 'Уже был в архиве на Ozon (архивация мёртвых слотов 2026-10-04)',
                        'updated_at' => now(),
                    ]);
                    $alreadyArchived++;
                    continue;
                }

                $productId = $item['id'] ?? null;
                if (!$productId) {
                    $this->error("  ⨯ {$offerId}: нет product_id в ответе Ozon");
                    $failed++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] {$offerId}: заархивировал бы product_id={$productId} ({$row->brand} {$row->article})");
                    $archived++;
                    continue;
                }

                try {
                    $ok = $client->archiveProduct($productId);
                } catch (\Throwable $e) {
                    $this->error("  ⨯ {$offerId}: исключение при архивации — {$e->getMessage()}");
                    $failed++;
                    continue;
                }

                if ($ok) {
                    DB::table('ozon_created_cards')->where('id', $row->id)->update([
                        'status' => 'archived',
                        'ozon_product_id' => $productId,
                        'comment' => 'Заархивирован 2026-10-04 — нет позиции у Rossko/Shatem, замена не нашлась (не продаём то, чего нет на складе)',
                        'updated_at' => now(),
                    ]);
                    $this->line("  ✓ {$offerId}: заархивирован (product_id={$productId})");
                    $archived++;
                } else {
                    $this->error("  ⨯ {$offerId}: Ozon отказал в архивации");
                    $failed++;
                }
            }
        }

        $this->info("Готово: заархивировано={$archived}, уже были в архиве={$alreadyArchived}, отсутствуют на Ozon={$missing}, ошибка={$failed}");

        return 0;
    }

    /** Та же логика, что и в OzonReplaceDeadCardsCommand — слоты, у которых НЕТ вообще позиции Rossko/Shatem с тем же article+brand. */
    private function pickDeadSlots(int $limit)
    {
        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->whereNotNull('parts_catalog_id')
            ->whereNotNull('offer_id')
            ->get(['id', 'parts_catalog_id', 'article', 'brand', 'offer_id']);

        $catalogIds = $rows->pluck('parts_catalog_id')->unique();
        $cards = PartsCatalog::whereIn('id', $catalogIds)->get()->keyBy('id');

        $articles = $cards->pluck('article_normalized')->unique()->values();
        $brands = $cards->pluck('brand_normalized')->unique()->values();

        $rsOffers = DB::table('supplier_offers')
            ->whereIn('supplier_name', self::ALLOWED_SUPPLIERS)
            ->whereIn('sku_normalized', $articles)
            ->whereIn('brand_normalized', $brands)
            ->get(['sku_normalized', 'brand_normalized'])
            ->map(fn ($r) => $r->sku_normalized . '|' . $r->brand_normalized)
            ->unique();

        return $rows->filter(function ($row) use ($cards, $rsOffers) {
            $card = $cards->get($row->parts_catalog_id);
            if (!$card) {
                return false;
            }
            $key = $card->article_normalized . '|' . $card->brand_normalized;
            return !$rsOffers->contains($key);
        })->take($limit)->values();
    }
}
