<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Тонкий клиент Kaspi Merchant API (заказы). Формат JSON:API, авторизация —
 * заголовок X-Auth-Token (токен из кабинета продавца, .env Kaspi_token).
 * Проверено живьём 2026-10-12: /orders требует filter[orders][state] и
 * filter[orders][creationDate][$ge] (мс); позиции — /orders/{id}/entries,
 * Kaspi SKU карточки в entries[].attributes.offer.code.
 */
class KaspiMerchantApi
{
    const BASE_URL = 'https://kaspi.kz/shop/api/v2';

    /** Все состояния заказа в API — по каждому отдельный запрос. */
    const STATES = ['NEW', 'SIGN_REQUIRED', 'PICKUP', 'DELIVERY', 'KASPI_DELIVERY', 'ARCHIVE'];

    private function client()
    {
        $token = config('services.kaspi.api_token');
        if (!$token) {
            throw new RuntimeException('Не задан токен Kaspi API (Kaspi_token в .env)');
        }

        return Http::withHeaders([
            'X-Auth-Token' => $token,
            'Accept'       => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'User-Agent'   => 'Mozilla/5.0',
        ])->timeout(30)->retry(2, 2000, throw: false);
    }

    /**
     * Заказы в состоянии $state, созданные не раньше $sinceMs (unix мс).
     * Возвращает массив data[] целиком (все страницы).
     */
    public function orders(string $state, int $sinceMs): array
    {
        $all = [];
        $page = 0;
        do {
            $resp = $this->client()->get(self::BASE_URL . '/orders', [
                'page[number]' => $page,
                'page[size]' => 100,
                'filter[orders][state]' => $state,
                'filter[orders][creationDate][$ge]' => $sinceMs,
            ]);
            if (!$resp->successful()) {
                throw new RuntimeException("Kaspi API orders {$state}: HTTP {$resp->status()} " . mb_substr($resp->body(), 0, 300));
            }
            $json = $resp->json();
            $all = array_merge($all, $json['data'] ?? []);
            $pageCount = (int) ($json['meta']['pageCount'] ?? 0);
            $page++;
        } while ($page < $pageCount);

        return $all;
    }

    public function entries(string $orderKaspiId): array
    {
        $resp = $this->client()->get(self::BASE_URL . "/orders/{$orderKaspiId}/entries");
        if (!$resp->successful()) {
            throw new RuntimeException("Kaspi API entries {$orderKaspiId}: HTTP {$resp->status()}");
        }

        return $resp->json('data') ?? [];
    }
}
