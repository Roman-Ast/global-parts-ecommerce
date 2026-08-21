<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Тонкий клиент к партнёрскому REST API Halyk Market (см. CLAUDE.md, раздел
 * "Halyk Market"). В отличие от Kaspi (только статик-фид + ручная передача
 * ссылки менеджеру), тут полноценный API: OAuth2 client credentials, поиск
 * по каталогу, привязка к существующей карточке, загрузка/статус фида.
 *
 * Токен живёт ~2 часа (expires_in=7199) и кэшируется — тот же приём, что и
 * getShatemToken() в SparePartControllerTest: Cache::lock() вокруг
 * cache()->remember(), чтобы параллельные вызовы не устраивали гонку за
 * повторным логином (это уже один раз чинили для Shatem, см. историю).
 */
class HalykMarketClient
{
    private function baseUrl(): string
    {
        return env('HALYK_USE_TEST_ENV', false)
            ? 'https://test2.halykmarket.com'
            : 'https://halykmarket.kz';
    }

    public function token(): string
    {
        return Cache::lock('halyk_token_lock', 10)->block(5, function () {
            return cache()->remember('halyk_access_token', 6900, function () {
                $response = Http::asJson()->post($this->baseUrl() . '/gw/auth/token', [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => env('HALYK_CLIENT_ID'),
                    'client_secret' => env('HALYK_CLIENT_SECRET'),
                ]);

                if (!$response->ok()) {
                    throw new \RuntimeException('Halyk auth failed: HTTP ' . $response->status() . ' ' . $response->body());
                }

                $token = $response->json('access_token');

                if (!$token) {
                    throw new \RuntimeException('Halyk auth: access_token отсутствует в ответе — ' . $response->body());
                }

                return $token;
            });
        });
    }

    /**
     * Поиск карточек по названию/артикулу.
     * GET /gw/merchant/public/skus/search?q=&page=&size=
     *
     * @return array<int, array{skuId:int,name:string,imageUrl:?string,category:array,marketUrl:?string}>
     */
    public function searchSku(string $query, int $page = 1, int $size = 10): array
    {
        $response = Http::withToken($this->token())
            ->timeout(15)
            ->get($this->baseUrl() . '/gw/merchant/public/skus/search', [
                'q'    => $query,
                'page' => $page,
                'size' => $size,
            ]);

        if (!$response->ok()) {
            return [];
        }

        // Спринговая пагинация (Page<T>) — элементы лежат в "content".
        // Подстраховка на случай другой формы ответа.
        return $response->json('content') ?? $response->json() ?? [];
    }

    /**
     * Привязка нашего товара к найденной карточке Halyk.
     * PUT /gw/merchant/public/product/remaining/save-and-map-sku
     *
     * $payload: skuId, merchantProductCode, city.code, price, points[].code,
     * points[].amount, loanPeriod (только 3/6/12/24).
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function bindSku(array $payload): array
    {
        $response = Http::withToken($this->token())
            ->asJson()
            ->timeout(15)
            ->put($this->baseUrl() . '/gw/merchant/public/product/remaining/save-and-map-sku', $payload);

        return [
            'ok'     => $response->ok(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
    }
}
