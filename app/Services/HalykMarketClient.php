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

    /**
     * Загрузка/статус фида живут на ОТДЕЛЬНОМ хосте от остального API
     * (/gw/... на halykmarket.kz) — см. CLAUDE.md, "Авторизация": прод —
     * api.halykmarket.com, тест — test2-api.halykmarket.com (БЕЗ "2" в
     * "api", только в "test2"). Не проверено вживую до 2026-08-25, тот ли
     * это OAuth-токен, что и для /gw/... — пробуем тот же token(), это
     * единственный токен, который у нас вообще есть.
     */
    private function apiBaseUrl(): string
    {
        return env('HALYK_USE_TEST_ENV', false)
            ? 'https://test2-api.halykmarket.com'
            : 'https://api.halykmarket.com';
    }

    /**
     * Загрузка XML-фида (см. GenerateHalykXml) — единственный bulk-путь у
     * Halyk, аналог kaspi:sync-feed, но с реальным ответом с id вместо
     * ручной передачи ссылки менеджеру.
     * POST /api/merchant/v1/offers/upload (multipart/form-data, поле file)
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function uploadFeed(string $filePath): array
    {
        $response = Http::withToken($this->token())
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post($this->apiBaseUrl() . '/api/merchant/v1/offers/upload');

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Статус обработки загруженного фида — status
     * (CREATED/PROCESSING/COMPLETED/FAILED/SKIPPED), totalCount/
     * successCount/notMappedCount/failCount/message. SKIPPED — фид
     * идентичен предыдущей загрузке, не обработан повторно.
     * GET /api/merchant/v1/offers/upload/status/{id}
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function getFeedUploadStatus(int $uploadId): array
    {
        // GET без явного Content-Type ловил 415 ("content type not
        // supported") — их API (в отличие от /gw/... эндпоинтов) требует
        // Content-Type: application/json даже на чтение без тела, одного
        // Accept недостаточно (проверено вживую 2026-08-25 — curl с
        // Content-Type прошёл, без него/только с Accept — нет).
        $response = Http::withToken($this->token())
            ->contentType('application/json')
            ->acceptJson()
            ->timeout(15)
            ->get($this->apiBaseUrl() . "/api/merchant/v1/offers/upload/status/{$uploadId}");

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
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

                if (!$response->successful()) {
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
            ->retry(2, 500, throw: false)
            ->get($this->baseUrl() . '/gw/merchant/public/skus/search', [
                'q'    => $query,
                'page' => $page,
                'size' => $size,
            ]);

        if (!$response->successful()) {
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
     * ВАЖНО (2026-08-28): реальное тело запроса, подтверждённое живьём
     * менеджером Halyk (взяли наши креды, вручную выполнили запрос — оба
     * тестовых skuId успешно привязались), оборачивает merchantProductCode/
     * loanPeriod и city+price+points в "info.pointByCity[]", а не кладёт их
     * плоско на верхний уровень рядом со skuId. Это и объясняет старую
     * персистентную ошибку 500 "getInfo() is null" — тело физически не
     * содержало ключ "info" вообще. Оставляем сигнатуру метода (плоский
     * $payload) прежней, чтобы не трогать вызывающий код в HalykBindCommand/
     * HalykCreateCardCommand — просто оборачиваем здесь, в одном месте.
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function bindSku(array $payload): array
    {
        $body = [
            'skuId' => $payload['skuId'],
            'info'  => [
                'merchantProductCode' => $payload['merchantProductCode'],
                'pointByCity'         => [
                    [
                        'city'   => $payload['city'],
                        'price'  => $payload['price'],
                        'points' => $payload['points'],
                    ],
                ],
                'loanPeriod' => $payload['loanPeriod'],
            ],
        ];

        $response = Http::withToken($this->token())
            ->asJson()
            ->timeout(15)
            ->put($this->baseUrl() . '/gw/merchant/public/product/remaining/save-and-map-sku', $body);

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Поиск категории для создания новой карточки. Нужен именно 3-й
     * (глубочайший) уровень — вызывающий код сам фильтрует по level===3,
     * этот метод просто возвращает сырые кандидаты.
     * GET /gw/merchant/public/category/search?q=&page=&size=
     *
     * @return array<int, array{id:int,name:string,level:int}>
     */
    public function searchCategory(string $query, int $page = 1, int $size = 10): array
    {
        $response = Http::withToken($this->token())
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->get($this->baseUrl() . '/gw/merchant/public/category/search', [
                'q' => $query, 'page' => $page, 'size' => $size,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('content') ?? $response->json() ?? [];
    }

    /**
     * Поиск ID бренда — та же форма ответа, что и у category/search.
     * GET /gw/merchant/public/brand/search?q=&page=&size=
     *
     * @return array<int, array{id:int,name:string,code:?string,url:?string}>
     */
    public function searchBrand(string $query, int $page = 1, int $size = 10): array
    {
        $response = Http::withToken($this->token())
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->get($this->baseUrl() . '/gw/merchant/public/brand/search', [
                'q' => $query, 'page' => $page, 'size' => $size,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json('content') ?? $response->json() ?? [];
    }

    /**
     * Форма характеристик под конкретную категорию 3-го уровня —
     * classAttrAssignmentId/classAttrType(NUMBER|ENUM|STRING|BOOLEAN)/
     * required/attrValue.classAttrValueId (для ENUM — фиксированный список).
     * GET /gw/merchant/public/form/product/feature?categoryId=
     *
     * @return array
     */
    public function getCharacteristicsForm(int $categoryId): array
    {
        $response = Http::withToken($this->token())
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->get($this->baseUrl() . '/gw/merchant/public/form/product/feature', [
                'categoryId' => $categoryId,
            ]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Загрузка фото товара (до создания карточки) — обычный multipart-
     * аплоад файлов, НЕ ссылок. requirements по их доке (белый фон,
     * квадрат 500-2000px, минимум 3 шт) на практике НЕ проверяются самим
     * upload-эндпоинтом (проверено вживую 2026-08-22 — приняли 403×500,
     * не квадрат, HTTP 200) — похоже, это soft-guidance для модерации, а
     * не hard-валидация при загрузке.
     * POST /gw/merchant/public/file/image/upload/multiple
     *
     * @param array<int, array{name: string, contents: string}> $files
     * @return array<int, array{id:int,assetUrl:string}>
     */
    public function uploadPhotos(array $files): array
    {
        $request = Http::withToken($this->token())->timeout(30);

        foreach ($files as $file) {
            $request = $request->attach('files', $file['contents'], $file['name']);
        }

        $response = $request->post($this->baseUrl() . '/gw/merchant/public/file/image/upload/multiple');

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Отправка заполненной формы нового товара на модерацию.
     * POST /gw/merchant/public/draft/product/moderation
     *
     * Успешный ответ: {"productDraftStatus":"CHECK","id":<int>} — id нужен
     * для последующего опроса статуса модерации (getDraftStatus).
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function submitForModeration(array $payload): array
    {
        $response = Http::withToken($this->token())
            ->asJson()
            ->timeout(20)
            ->post($this->baseUrl() . '/gw/merchant/public/draft/product/moderation', $payload);

        // successful() (весь диапазон 2xx), не ok() (строго 200 у Laravel)
        // — этот эндпоинт отвечает 202 Accepted на успех (проверено вживую
        // 2026-08-22: HTTP 202 + productDraftStatus:"CHECK" + реальный id —
        // с ok() это ошибочно считалось провалом).
        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
    }

    /**
     * Статус модерации созданной карточки: MODERATION (в очереди) / REJECT
     * (ошибки в характеристиках) / DELETED (дубль или неверная категория) /
     * SUCCESS (на витрине).
     * GET /gw/merchant/public/draft/product/{id}
     *
     * @return array{ok:bool,status:int,body:mixed}
     */
    public function getDraftStatus(int $productId): array
    {
        $response = Http::withToken($this->token())
            ->timeout(15)
            ->retry(2, 500, throw: false)
            ->get($this->baseUrl() . "/gw/merchant/public/draft/product/{$productId}");

        return [
            'ok'     => $response->successful(),
            'status' => $response->status(),
            'body'   => $response->json() ?? $response->body(),
        ];
    }
}
