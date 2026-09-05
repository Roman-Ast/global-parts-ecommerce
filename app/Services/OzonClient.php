<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Тонкий клиент к Ozon Seller API (api-seller.ozon.ru) — в отличие от
 * Kaspi/Halyk, это официальный документированный REST API, авторизация
 * простыми заголовками Client-Id/Api-Key (без OAuth/токенов с истечением).
 * Живьём проверено 2026-09-03: дерево категорий, атрибуты, поиск по
 * словарям (бренд/ТН ВЭД), создание карточки с фото ПО ССЫЛКЕ (не файлом,
 * как у Halyk) — всё отработало с первого раза после того, как поправили
 * валюту на RUB (см. докблок OzonCreateCardCommand про currency_code).
 */
class OzonClient
{
    private const BASE_URL = 'https://api-seller.ozon.ru';

    /** Дерево категорий ~1МБ, не меняется часто — кэшируем на сутки. */
    private const TREE_CACHE_TTL_HOURS = 24;

    private function headers(): array
    {
        return [
            'Client-Id' => env('OZON_SELLER_ID'),
            'Api-Key'   => env('OZON_API_KEY'),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Полное дерево категорий/типов. У Ozon для автозапчастей НЕТ
     * многоуровневой категоризации, как у Kaspi/Halyk — одна категория
     * "Автотовары > Запчасти для легковых автомобилей" (description_category_id
     * стабильно, проверено вживую) держит ~464 "типа" плоским списком
     * (амортизатор, сайлентблок, ступица и т.д.) — сам type_id и есть
     * фактическая "категория" товара с точки зрения атрибутов/модерации.
     */
    public function categoryTree(): array
    {
        return Cache::remember('ozon_category_tree', now()->addHours(self::TREE_CACHE_TTL_HOURS), function () {
            $response = Http::timeout(30)->withHeaders($this->headers())
                ->post(self::BASE_URL . '/v1/description-category/tree', ['language' => 'RU']);

            if (!$response->successful()) {
                throw new \RuntimeException('Ozon category/tree недоступен: HTTP ' . $response->status());
            }

            return $response->json('result') ?? [];
        });
    }

    /**
     * Обязательные и опциональные атрибуты для конкретной пары
     * категория+тип — аналог getCharacteristicsForm() у Halyk, но проще:
     * плоский список, без вложенных блоков.
     */
    public function attributes(int $categoryId, int $typeId): array
    {
        $response = Http::timeout(20)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v1/description-category/attribute', [
                'description_category_id' => $categoryId,
                'type_id' => $typeId,
                'language' => 'RU',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ozon attribute недоступен: HTTP ' . $response->status());
        }

        return $response->json('result') ?? [];
    }

    /**
     * Поиск значения в словаре атрибута (бренд, ТН ВЭД и т.п.) по тексту —
     * возвращает топ совпадений с их dictionary_value_id. Проверено
     * вживую: поиск "KYB" по атрибуту "Бренд" (id=85) даёт точное
     * совпадение первым результатом.
     */
    public function searchAttributeValue(int $categoryId, int $typeId, int $attributeId, string $query, int $limit = 10): array
    {
        $response = Http::timeout(20)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v1/description-category/attribute/values/search', [
                'description_category_id' => $categoryId,
                'type_id' => $typeId,
                'attribute_id' => $attributeId,
                'value' => $query,
                'limit' => $limit,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Ozon attribute/values/search недоступен (attribute_id={$attributeId}): HTTP " . $response->status());
        }

        return $response->json('result') ?? [];
    }

    /**
     * Создание/обновление карточки. Возвращает task_id — обработка
     * асинхронная, реальный результат смотреть через importStatus().
     */
    public function importProduct(array $item): int
    {
        $response = Http::timeout(30)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v3/product/import', ['items' => [$item]]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ozon product/import недоступен: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        return (int) $response->json('result.task_id');
    }

    /**
     * @return array{offer_id:string,product_id:int,status:string,errors:array}
     */
    public function importStatus(int $taskId): array
    {
        $response = Http::timeout(20)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v1/product/import/info', ['task_id' => $taskId]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ozon import/info недоступен: HTTP ' . $response->status());
        }

        return $response->json('result.items.0') ?? [];
    }

    /**
     * Полная информация по товару (статус модерации, комиссии,
     * видимость) — использовалась для живой проверки пилота 2026-09-03,
     * пригодится и для сверки комиссий по категориям.
     */
    public function productInfo(string $offerId): array
    {
        $response = Http::timeout(20)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v3/product/info/list', ['offer_id' => [$offerId]]);

        if (!$response->successful()) {
            throw new \RuntimeException('Ozon product/info/list недоступен: HTTP ' . $response->status());
        }

        return $response->json('items.0') ?? [];
    }

    public function archiveProduct(int $productId): bool
    {
        $response = Http::timeout(20)->withHeaders($this->headers())
            ->post(self::BASE_URL . '/v1/product/archive', ['product_id' => [$productId]]);

        return $response->successful() && (bool) $response->json('result');
    }
}
