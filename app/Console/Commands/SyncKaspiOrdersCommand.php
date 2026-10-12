<?php

namespace App\Console\Commands;

use App\Services\KaspiCardSnapshotService;
use App\Services\KaspiMerchantApi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Забирает заказы Kaspi через Merchant API в kaspi_orders/kaspi_order_entries
 * и для каждой НОВОЙ позиции сразу делает снимок карточки
 * (KaspiCardSnapshotService) — позиция на карточке фиксируется близко к
 * моменту продажи, без слепых зон редкого парсинга каталога.
 *
 * Снимок делаем только для заказов не старше SNAPSHOT_MAX_AGE_HOURS: снимок
 * карточки через неделю после продажи уже не отвечает на вопрос «почему
 * купили у нас», только засоряет отчёт.
 *
 * На проде — по HTTP-крону /api/cron/kaspi-orders (нет SSH, как и у очереди
 * WhatsApp).
 */
class SyncKaspiOrdersCommand extends Command
{
    protected $signature = 'kaspi:sync-orders {--days=2 : За сколько дней назад забирать заказы} {--no-snapshot}';
    protected $description = 'Заказы Kaspi из Merchant API + снимок карточки на момент заказа';

    const SNAPSHOT_MAX_AGE_HOURS = 48;

    public function handle(KaspiMerchantApi $api, KaspiCardSnapshotService $snapshots): int
    {
        $sinceMs = now()->subDays((int) $this->option('days'))->getTimestampMs();
        $newOrders = $newEntries = $snaps = $errors = 0;

        foreach (KaspiMerchantApi::STATES as $state) {
            try {
                $orders = $api->orders($state, $sinceMs);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $errors++;
                continue;
            }

            foreach ($orders as $o) {
                $a = $o['attributes'] ?? [];
                $now = now();
                $fields = [
                    'code'                     => (string) $a['code'],
                    'state'                    => $a['state'] ?? $state,
                    'status'                   => $a['status'] ?? null,
                    'kaspi_created_at'         => isset($a['creationDate']) ? Carbon::createFromTimestampMs($a['creationDate']) : null,
                    'planned_delivery_at'      => isset($a['plannedDeliveryDate']) ? Carbon::createFromTimestampMs($a['plannedDeliveryDate']) : null,
                    'total_price'              => (float) ($a['totalPrice'] ?? 0),
                    'delivery_cost_for_seller' => (float) ($a['deliveryCostForSeller'] ?? 0),
                    'delivery_mode'            => $a['deliveryMode'] ?? null,
                    'payment_mode'             => $a['paymentMode'] ?? null,
                    'is_kaspi_delivery'        => (bool) ($a['isKaspiDelivery'] ?? false),
                    'pre_order'                => (bool) ($a['preOrder'] ?? false),
                    'town'                     => $a['deliveryAddress']['town'] ?? null,
                    'customer_name'            => trim(($a['customer']['firstName'] ?? '') . ' ' . ($a['customer']['lastName'] ?? '')) ?: null,
                    'raw'                      => json_encode($o, JSON_UNESCAPED_UNICODE),
                    'updated_at'               => $now,
                ];

                $existing = DB::table('kaspi_orders')->where('kaspi_id', $o['id'])->first();
                if ($existing) {
                    // Статус/состояние меняются (NEW → … → ARCHIVE), позиции — нет.
                    DB::table('kaspi_orders')->where('id', $existing->id)->update($fields);
                    continue;
                }

                $orderId = DB::table('kaspi_orders')->insertGetId($fields + ['kaspi_id' => $o['id'], 'created_at' => $now]);
                $newOrders++;

                try {
                    $entries = $api->entries($o['id']);
                } catch (\Throwable $e) {
                    $this->error($e->getMessage());
                    $errors++;
                    continue;
                }

                $fresh = $fields['kaspi_created_at'] && $fields['kaspi_created_at']->gt(now()->subHours(self::SNAPSHOT_MAX_AGE_HOURS));

                foreach ($entries as $e) {
                    $ea = $e['attributes'] ?? [];
                    $sku = (string) ($ea['offer']['code'] ?? '');
                    $entryId = DB::table('kaspi_order_entries')->insertGetId([
                        'kaspi_order_id' => $orderId,
                        'kaspi_entry_id' => $e['id'],
                        'entry_number'   => (int) ($ea['entryNumber'] ?? 0),
                        'kaspi_sku'      => $sku,
                        'offer_name'     => mb_substr((string) ($ea['offer']['name'] ?? ''), 0, 500),
                        'category'       => $ea['category']['title'] ?? null,
                        'quantity'       => (int) ($ea['quantity'] ?? 1),
                        'base_price'     => (float) ($ea['basePrice'] ?? 0),
                        'total_price'    => (float) ($ea['totalPrice'] ?? 0),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                    $newEntries++;

                    if ($sku === '' || !$fresh || $this->option('no-snapshot')) {
                        continue;
                    }
                    try {
                        $snapId = $snapshots->snapshot($sku, 'order', $entryId);
                        DB::table('kaspi_order_entries')->where('id', $entryId)->update(['snapshot_id' => $snapId]);
                        $snaps++;
                    } catch (\Throwable $ex) {
                        $this->warn("Снимок {$sku}: " . $ex->getMessage());
                        $errors++;
                    }
                }

                $this->line("  + заказ {$fields['code']} ({$fields['state']}/{$fields['status']}) " . number_format($fields['total_price'], 0, '.', ' ') . ' ₸');
            }
        }

        $this->info("Новых заказов: {$newOrders}, позиций: {$newEntries}, снимков: {$snaps}, ошибок: {$errors}");

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
