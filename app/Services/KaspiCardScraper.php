<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KaspiCardScraper
{
    /**
     * Скрапит публичную карточку Kaspi по SKU и возвращает структурированные данные.
     * Не требует авторизации/кук — работает для любого SKU (свой или конкурента).
     *
     * @throws \App\Exceptions\KaspiCardNotFoundException
     */
    public function fetchCard(string $kaspiSku): array
    {
        $url = "https://kaspi.kz/shop/p/-{$kaspiSku}/";

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'ru,ru-RU;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->timeout(15)->get($url);

        if ($response->status() === 404) {
            throw new \App\Exceptions\KaspiCardNotFoundException("Карточка не найдена: {$kaspiSku}");
        }

        if (!$response->ok()) {
            throw new \RuntimeException("HTTP {$response->status()} при запросе карточки {$kaspiSku}");
        }

        $html = $response->body();
        $item = $this->extractBackendItem($html);

        if ($item === null) {
            throw new \RuntimeException("Не удалось извлечь BACKEND.components.item для SKU {$kaspiSku}");
        }

        return $this->mapItemToCardData($item);
    }

    /**
     * Вырезает JSON-объект из "BACKEND.components.item = {...}" внутри <script>.
     * Не полагается на регэксп с жадным/нежадным матчингом фигурных скобок —
     * ищет по буквальным маркерам начала присвоения и следующего закрывающего </script>,
     * т.к. внутри JSON скобок много и они ломают наивный regex.
     */
    private function extractBackendItem(string $html): ?array
    {
        $marker = 'BACKEND.components.item = ';
        $startPos = strpos($html, $marker);

        if ($startPos === false) {
            return null;
        }

        $jsonStart = $startPos + strlen($marker);
        $endPos = strpos($html, '</script>', $jsonStart);

        if ($endPos === false) {
            return null;
        }

        $jsonStr = substr($html, $jsonStart, $endPos - $jsonStart);
        $jsonStr = trim($jsonStr);
        $jsonStr = rtrim($jsonStr, ";\n\r\t ");

        $data = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Превращает сырой "item" в плоскую структуру для записи в parts_catalog.
     */
    private function mapItemToCardData(array $item): array
    {
        $card = $item['card'] ?? [];

        $name = $card['title'] ?? $card['name'] ?? null;
        $brand = $card['promoConditions']['brand'] ?? null;

        $description = $item['description'] ?? null;

        $applicability = null;
        $oemNumbers = null;

        foreach ($item['descriptions'] ?? [] as $block) {
            $title = mb_strtolower($block['title'] ?? '');
            if (str_contains($title, 'применимост')) {
                $applicability = $block['text'] ?? null;
            }
            if (str_contains($title, 'оем') || str_contains($title, 'oem')) {
                $oemNumbers = $block['text'] ?? null;
            }
        }

        $images = [];
        foreach ($item['galleryImages'] ?? [] as $img) {
            if (!empty($img['large'])) {
                $images[] = $img['large'];
            } elseif (!empty($img['medium'])) {
                $images[] = $img['medium'];
            }
        }

        // Полная цепочка категорий (хлебные крошки), от корня до самой узкой
        // подкатегории. Лежит прямо на верхнем уровне item — item['breadcrumbs'],
        // как плоский список вида [{"title":..., "link":..., "id":...}, ...].
        // "id" — стабильный английский код категории (не зависит от локали/
        // склонений русского текста), удобен для маппинга на категории Ozon.
        // Последний элемент списка — самая узкая (точная) категория товара.
        $categoryPath = [];
        foreach ($item['breadcrumbs'] ?? [] as $crumb) {
            $categoryPath[] = [
                'title' => $crumb['title'] ?? null,
                'code'  => $crumb['id'] ?? null,
            ];
        }

        // characteristics — сохраняем как есть (специфический вложенный формат
        // Kaspi: секции -> features -> featureValues), плюс отдельно кладём
        // OEM-номера, т.к. для них в parts_catalog нет отдельного столбца.
        $characteristics = [
            'specifications'  => $item['specifications'] ?? [],
            'oem_numbers_raw' => $oemNumbers,
        ];

        return [
            'name'                => $name,
            'brand'               => $brand,
            'description'         => $description,
            'applicability'       => $applicability,
            'characteristics'     => $characteristics,
            'images'              => $images,
            'category_path'       => $categoryPath,
            // Последний уровень отдельно, для удобных плоских запросов
            // без JSON_EXTRACT по массиву.
            'category_leaf_title' => $categoryPath ? end($categoryPath)['title'] : null,
            'category_leaf_code'  => $categoryPath ? end($categoryPath)['code'] : null,
        ];
    }
}