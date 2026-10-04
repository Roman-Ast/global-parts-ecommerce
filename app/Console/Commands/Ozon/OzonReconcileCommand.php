<?php

namespace App\Console\Commands\Ozon;

use App\Services\OzonClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сверка ozon_created_cards с реальным состоянием на Ozon (просьба Романа
 * 2026-10-04) — живая находка при чистке под Rossko/Shatem: из 111 карточек,
 * которые локально числились status=imported, 33 ответили HTTP NOT_FOUND
 * при попытке обновить цену/остаток — на Ozon их физически больше нет, хотя
 * у нас числятся успешными. Расхождение подтверждено и в масштабе: локально
 * 3499 imported, а в реальном кабинете Романа "Все 2399" (В продаже 2207 +
 * Ошибки 141 + На доработку 3 + Архив 1) — около 1100 карточек локально
 * считаются существующими, а на деле нет.
 *
 * Батчами по offer_id сверяем с /v3/product/info/list (см.
 * OzonClient::productInfoBatch) — то, что Ozon не вернул вообще, помечаем
 * status=missing; то, что вернул — обновляем статус по их же
 * statuses.status_name/is_archived, чтобы дальше пайплайн (фикс
 * Rossko/Shatem, будущая замена мёртвых карточек) работал от правды, а не
 * от устаревшей локальной пометки.
 */
class OzonReconcileCommand extends Command
{
    protected $signature = 'ozon:reconcile {--limit=100000} {--batch=100}';

    protected $description = 'Сверяет ozon_created_cards (status=imported) с реальным состоянием на Ozon, чинит расхождения';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');

        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->limit($limit)
            ->get(['id', 'article', 'brand']);

        if ($rows->isEmpty()) {
            $this->info('Нечего сверять — нет карточек в статусе imported.');
            return 0;
        }

        $this->info("Сверяем {$rows->count()} карточек...");

        $missing = 0;
        $archived = 0;
        $stillSelling = 0;
        $otherStatus = 0;

        foreach ($rows->chunk($batchSize) as $chunk) {
            $offerIdToRowId = [];
            foreach ($chunk as $row) {
                $offerId = mb_substr("{$row->brand}-{$row->article}", 0, 50);
                $offerIdToRowId[$offerId] = $row->id;
            }

            try {
                $items = $client->productInfoBatch(array_keys($offerIdToRowId));
            } catch (\Throwable $e) {
                $this->error("  ⨯ батч упал: {$e->getMessage()}");
                continue;
            }

            foreach ($offerIdToRowId as $offerId => $rowId) {
                $item = $items[$offerId] ?? null;

                if (!$item) {
                    DB::table('ozon_created_cards')->where('id', $rowId)->update([
                        'status' => 'missing',
                        'comment' => 'Реально отсутствует на Ozon (сверка 2026-10-04) — локально считался imported',
                        'updated_at' => now(),
                    ]);
                    $missing++;
                    continue;
                }

                $isArchived = (bool) ($item['is_archived'] ?? false);
                $statusName = $item['statuses']['status_name'] ?? 'unknown';

                if ($isArchived) {
                    DB::table('ozon_created_cards')->where('id', $rowId)->update([
                        'status' => 'archived',
                        'comment' => "В архиве на Ozon (сверка 2026-10-04)",
                        'updated_at' => now(),
                    ]);
                    $archived++;
                } elseif ($statusName === 'Продается') {
                    // imported и так уже верно отражает это, просто обновляем updated_at
                    DB::table('ozon_created_cards')->where('id', $rowId)->update(['updated_at' => now()]);
                    $stillSelling++;
                } else {
                    DB::table('ozon_created_cards')->where('id', $rowId)->update([
                        'status' => 'imported', // оставляем imported, но фиксируем реальный статус в comment
                        'comment' => "Реальный статус на Ozon: {$statusName} (сверка 2026-10-04)",
                        'updated_at' => now(),
                    ]);
                    $otherStatus++;
                }
            }

            $this->line("  ...обработан батч из " . count($offerIdToRowId));
        }

        $this->info("Готово: продаётся={$stillSelling}, отсутствует на Ozon={$missing}, в архиве={$archived}, другой статус={$otherStatus}");

        return 0;
    }
}
