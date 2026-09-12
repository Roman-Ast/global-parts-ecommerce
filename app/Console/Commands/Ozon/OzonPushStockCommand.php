<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Передаёт актуальный остаток на Ozon (`/v2/products/stocks`) для уже
 * созданных карточек (`ozon_created_cards`, status=imported) — тот самый
 * недостающий шаг, без которого карточка навсегда виснет в "Готов к
 * продаже" и никогда не переходит в "В продаже" (см. CLAUDE.md,
 * "Настоящая причина... оба FBS-склада отключены", 2026-09-12).
 *
 * Не запускать, пока склад Ozon (`OZON_WAREHOUSE_ID` в .env) в статусе
 * "disabled" — вызов будет падать с WAREHOUSE_WRONG_STATUS на каждой
 * позиции без исключения, это подтверждено живьём. Активировать способ
 * доставки/первую милю можно только в личном кабинете Ozon.
 *
 * Остаток берём ЗАНОВО через SupplierOfferPricer, а не тот, что был
 * записан в момент создания карточки — с момента создания (часть карточек
 * с 2026-09-03) остатки у поставщиков могли уйти в ноль или измениться.
 */
class OzonPushStockCommand extends Command
{
    protected $signature = 'ozon:push-stock {--limit=200}';

    protected $description = 'Передаёт актуальные остатки на Ozon для уже созданных карточек (status=imported)';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $warehouseId = (int) env('OZON_WAREHOUSE_ID');

        if (!$warehouseId) {
            $this->error('OZON_WAREHOUSE_ID не задан в .env — нечего передавать.');
            return 1;
        }

        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'article', 'brand']);

        if ($rows->isEmpty()) {
            $this->info('Нечего передавать — нет карточек в статусе imported.');
            return 0;
        }

        $this->info("Передаём остаток для {$rows->count()} карточек на склад {$warehouseId}...");

        $cards = PartsCatalog::query()
            ->whereIn('article', $rows->pluck('article'))
            ->get();
        $cards = (new SupplierOfferPricer())->attach($cards);
        $byArticleBrand = $cards->keyBy(fn ($c) => $c->article . '|' . $c->brand);

        $updated = 0;
        $noOffer = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $card = $byArticleBrand->get($row->article . '|' . $row->brand);
            $stock = $card->offer['stock'] ?? 0;

            if ($stock <= 0) {
                $this->line("  {$row->brand}-{$row->article}: нет остатка у поставщика сейчас — пропуск");
                $noOffer++;
                continue;
            }

            $offerId = mb_substr("{$row->brand}-{$row->article}", 0, 50);

            try {
                $result = $client->updateStock($offerId, $stock, $warehouseId);
            } catch (\Throwable $e) {
                $this->error("  ⨯ {$offerId}: {$e->getMessage()}");
                $failed++;
                continue;
            }

            if ($result['updated']) {
                $this->line("  {$offerId}: остаток {$stock} передан");
                $updated++;
            } else {
                $errCode = $result['errors'][0]['code'] ?? 'unknown';
                $this->error("  ⨯ {$offerId}: не обновилось ({$errCode})");
                $failed++;
            }
        }

        $this->info("Готово: обновлено={$updated}, без остатка сейчас={$noOffer}, ошибок={$failed}");

        return 0;
    }
}
