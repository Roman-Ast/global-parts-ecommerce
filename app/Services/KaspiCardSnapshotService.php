<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Снимок карточки Kaspi: все продавцы (включая нас) и наша позиция по цене и
 * сроку. Тот же публичный эндпоинт, что у ParseKaspiSkuCommand::fetchOffers(),
 * но в отличие от него НАШ оффер не выкидывается — нужен, чтобы видеть
 * реальный срок доставки, который Kaspi показывает покупателю у нас.
 * Проверено 2026-10-12: бренд/категории в теле запроса не обязательны.
 */
class KaspiCardSnapshotService
{
    const OUR_MERCHANT_ID = '30360429';
    const CITY_ID = '710000000'; // Астана

    /** Срок доставки в днях для сравнения «кто быстрее». */
    const DURATION_DAYS = [
        'EXPRESS' => 0, 'TODAY' => 0, 'TOMORROW' => 1, 'TILL_2_DAYS' => 2,
        'TILL_5_DAYS' => 5, 'TILL_7_DAYS' => 7, 'OTHER' => 10,
    ];

    public function fetchOffers(string $kaspiSku): array
    {
        $resp = Http::withHeaders([
            'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Referer'      => "https://kaspi.kz/shop/p/{$kaspiSku}/",
        ])->timeout(20)->retry(2, 2000, throw: false)->post("https://kaspi.kz/yml/offer-view/offers/{$kaspiSku}", [
            'cityId'         => self::CITY_ID,
            'id'             => $kaspiSku,
            'merchantUID'    => [],
            'limit'          => 64,
            'page'           => 0,
            'sortOption'     => 'PRICE',
            'zoneId'         => ['Magnum_ZONE1'],
            'installationId' => '-1',
        ]);

        if (!$resp->successful()) {
            throw new RuntimeException("Kaspi offers {$kaspiSku}: HTTP {$resp->status()}");
        }

        return $resp->json() ?? [];
    }

    private static function speed(?string $duration, int $preorder): int
    {
        return max(self::DURATION_DAYS[$duration] ?? 10, $preorder);
    }

    /**
     * Делает снимок и пишет его в kaspi_card_snapshots. Возвращает id снимка.
     */
    public function snapshot(string $kaspiSku, string $reason = 'order', ?int $entryId = null): int
    {
        $data = $this->fetchOffers($kaspiSku);
        $raw = $data['offers'] ?? [];

        $offers = [];
        $our = null;
        foreach ($raw as $o) {
            $row = [
                'merchant_id'   => (string) ($o['merchantId'] ?? ''),
                'merchant_name' => $o['merchantName'] ?? null,
                'price'         => isset($o['price']) ? (float) $o['price'] : null,
                'delivery'      => $o['deliveryDuration'] ?? null,
                'preorder'      => (int) ($o['preorder'] ?? 0),
                'rating'        => $o['merchantRating'] ?? null,
                'reviews'       => (int) ($o['merchantReviewsQuantity'] ?? 0),
            ];
            $offers[] = $row;
            if ($row['merchant_id'] === self::OUR_MERCHANT_ID) {
                $our = $row;
            }
        }

        $competitors = array_values(array_filter($offers, fn($o) => $o['merchant_id'] !== self::OUR_MERCHANT_ID));
        $compPrices = array_filter(array_column($competitors, 'price'), fn($p) => $p !== null);
        $fast = array_filter($competitors, fn($o) => self::speed($o['delivery'], $o['preorder']) <= 1);
        $fastPrices = array_filter(array_column($fast, 'price'), fn($p) => $p !== null);

        $cheaper = 0;
        $rank = null;
        $weFastest = null;
        if ($our && $our['price'] !== null) {
            $cheaper = count(array_filter($compPrices, fn($p) => $p < $our['price']));
            $rank = $cheaper + 1;
            $ourSpeed = self::speed($our['delivery'], $our['preorder']);
            $weFastest = !array_filter($competitors, fn($o) => self::speed($o['delivery'], $o['preorder']) < $ourSpeed);
        }

        $now = now();

        return DB::table('kaspi_card_snapshots')->insertGetId([
            'kaspi_sku'                 => $kaspiSku,
            'reason'                    => $reason,
            'kaspi_order_entry_id'      => $entryId,
            'taken_at'                  => $now,
            'sellers_total'             => (int) ($data['offersCount'] ?? count($offers)),
            'we_present'                => $our !== null,
            'our_price'                 => $our['price'] ?? null,
            'our_delivery'              => $our['delivery'] ?? null,
            'our_preorder_days'         => $our['preorder'] ?? null,
            'our_price_rank'            => $rank,
            'cheaper_count'             => $cheaper,
            'min_competitor_price'      => $compPrices ? min($compPrices) : null,
            'fast_competitors'          => count($fast),
            'min_fast_competitor_price' => $fastPrices ? min($fastPrices) : null,
            'we_fastest'                => $weFastest,
            'offers'                    => json_encode($offers, JSON_UNESCAPED_UNICODE),
            'created_at'                => $now,
            'updated_at'                => $now,
        ]);
    }
}
