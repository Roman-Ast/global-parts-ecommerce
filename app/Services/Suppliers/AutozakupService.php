<?php

namespace App\Services\Suppliers;

class AutozakupService
{
    const BASE_URL           = 'https://service.tradesoft.ru/3/';
    const CONNECTION_TIMEOUT = 5;
    const TIMEOUT            = 25;
    const ROUND_LIMIT        = 0;

    // ID поставщика Автозакуп в системе Tradesoft — уточни в личном кабинете
    const PROVIDER_ID = 'autozakup';

    // Курс RUB → KZT (обнови или подтяни динамически)
    const RUB_TO_KZT = 5.5;

    private string $user;
    private string $password;

    public function __construct()
    {
        $this->user     = env('AUTOZAKUP_USER', '');
        $this->password = env('AUTOZAKUP_PASSWORD', '');
    }

    // =========================================================================
    // Публичный метод — аналог searchShatem()
    // Вызывать: $this->searchAvtozakup($brand, $partnumber)
    // Пишет напрямую в $this->finalArr через ссылку
    // =========================================================================
    public function searchAvtozakup(string $brand, string $partnumber): void
    {
        // --- Шаг 1: цены на искомый артикул+бренд → searchedNumber -----------
        $exactOffers = $this->getPriceList($partnumber, $brand);

        foreach ($exactOffers as $offer) {
            $priceKzt = $this->convertToKzt($offer->price ?? 0, $offer->currencycode ?? 'RUB');

            array_push($this->finalArr['searchedNumber'], [
                'brand'            => $offer->producer      ?? $brand,
                'article'          => $offer->code          ?? $partnumber,
                'name'             => $offer->caption       ?? '',
                'price'            => $priceKzt,
                'priceWithMargine' => round($this->setPrice($priceKzt), self::ROUND_LIMIT),
                'qty'              => $offer->rest          ?? 0,
                'supplier_name'    => 'azk',
                'supplier_city'    => 'ast',
                'supplier_color'   => '#1a73e8',
                'delivery_time'    => $this->formatDelivery($offer),
            ]);
        }

        // --- Шаг 2: получаем кроссы ------------------------------------------
        $analogs = $this->getAnalogs($partnumber, $brand);

        // --- Шаг 3: для каждого кросса получаем цены → crosses_to_order ------
        foreach ($analogs as $analog) {
            // analog = [article, brand, ?, name]
            $analogArticle = $analog[0] ?? null;
            $analogBrand   = $analog[1] ?? null;
            $analogName    = $analog[3] ?? '';

            if (empty($analogArticle) || empty($analogBrand)) {
                continue;
            }

            $analogOffers = $this->getPriceList($analogArticle, $analogBrand);

            if (empty($analogOffers)) {
                continue; // нет предложений — не показываем пустой аналог
            }

            foreach ($analogOffers as $offer) {
                $priceKzt = $this->convertToKzt($offer->price ?? 0, $offer->currencycode ?? 'RUB');

                array_push($this->finalArr['crosses_to_order'], [
                    'brand'            => $offer->producer      ?? $analogBrand,
                    'article'          => $offer->code          ?? $analogArticle,
                    'name'             => $offer->caption       ?? $analogName,
                    'qty'              => $offer->rest          ?? 0,
                    'price'            => $priceKzt,
                    'priceWithMargine' => round($this->setPrice($priceKzt), self::ROUND_LIMIT),
                    'delivery_time'    => $this->formatDelivery($offer),
                    'stocks'           => [
                        [
                            'qty'              => $offer->rest ?? 0,
                            'price'            => $priceKzt,
                            'priceWithMargine' => round($this->setPrice($priceKzt), self::ROUND_LIMIT),
                        ]
                    ],
                    'supplier_name'    => 'azk',
                    'supplier_city'    => 'ast',
                    'supplier_color'   => '#1a73e8',
                ]);
            }
        }
    }

    // =========================================================================
    // POST /provider/get-price-list/
    // Возвращает массив офферов от поставщика по артикул+бренд
    // =========================================================================
    private function getPriceList(string $code, string $producer): array
    {
        $body = [
            'user'      => $this->user,
            'password'  => $this->password,
            'service'   => 'provider',
            'action'    => 'getPriceList',
            'timelimit' => 10,
            'container' => [
                [
                    'provider' => self::PROVIDER_ID,
                    'login'    => $this->user,
                    'password' => $this->password,
                    'code'     => $code,
                    'producer' => $producer,
                ]
            ],
        ];

        $response = $this->post('provider/get-price-list/', $body);

        // Ответ: {error, time, data: [{code, producer, price, ...}, ...]}
        if (!$response || !empty($response->error) || empty($response->data)) {
            return [];
        }

        // data может быть объектом с ключами или массивом — нормализуем
        $data = $response->data;
        if (is_object($data)) {
            $data = array_values((array) $data);
        }

        return is_array($data) ? $data : [];
    }

    // =========================================================================
    // POST /analog/get-analogs
    // Возвращает плоский массив аналогов: [[article, brand, ?, name], ...]
    // =========================================================================
    private function getAnalogs(string $partnumber, string $brand): array
    {
        $body = [
            'user'     => $this->user,
            'password' => $this->password,
            'service'  => 'analog',
            'action'   => 'getAnalogs',
            'rating'   => 3,
            'lng'      => 'ru,en',
            'data'     => [
                [$partnumber, $brand],
            ],
        ];

        $response = $this->post('analog/get-analogs', $body);

        // data: {"KL9": {code, brand, analogs: [[...], ...]}}
        if (!$response || !empty($response->error) || empty($response->data)) {
            return [];
        }

        $result = [];
        foreach ($response->data as $key => $item) {
            if (!empty($item->analogs)) {
                foreach ($item->analogs as $analog) {
                    $result[] = $analog; // каждый элемент — массив [article, brand, ?, name]
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function convertToKzt(float $price, string $currency): float
    {
        return match (strtoupper($currency)) {
            'RUB'   => $price * self::RUB_TO_KZT,
            'KZT'   => $price,
            'USD'   => $price * 450, // обнови курс
            default => $price * self::RUB_TO_KZT,
        };
    }

    private function formatDelivery(object $offer): string
    {
        $min = $offer->deliverydays_min ?? null;
        $max = $offer->deliverydays_max ?? null;
        $str = $offer->deliverydays    ?? null;

        if ($min !== null && $max !== null && $min !== $max) {
            return "{$min}-{$max} дн.";
        }
        if ($str) {
            return "{$str} дн.";
        }
        return 'уточняется';
    }

    private function setPrice(float $purchasePrice): float
    {
        // Подставь свою логику наценки как в других поставщиках
        return $purchasePrice * 1.3;
    }

    // =========================================================================
    // HTTP helper
    // =========================================================================
    private function post(string $endpoint, array $body): ?object
    {
        $ch = curl_init(self::BASE_URL . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECTION_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        try {
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err || !$raw) {
                \Log::warning("AutozakupService [{$endpoint}] cURL: {$err}");
                return null;
            }

            $decoded = json_decode($raw);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \Log::warning("AutozakupService [{$endpoint}] JSON parse error. Raw: " . substr($raw, 0, 200));
                return null;
            }

            return $decoded;

        } catch (\Throwable $th) {
            \Log::error("AutozakupService [{$endpoint}] exception: " . $th->getMessage());
            return null;
        }
    }
}
