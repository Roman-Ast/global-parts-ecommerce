<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SoapClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use App\SetPrice;
use App\Models\OfficePrice;
use App\Models\gm_pricelist_from_adil;
use App\Models\XuiPoimiPrice;
use App\Models\IngvarPrice;
use App\Models\VoltagePrice;
use App\Models\BlueStarPrice;
use App\Models\InterkomPrice;
use App\Models\AdilPhaetonPrice;
use Illuminate\Support\Facades\Auth;

class SparePartController extends Controller
{
    const API_KEY1_ROSSKO = '4adcbb9794b8e537bd2aa6272b36bdb0';
    const API_KEY2_ROSSKO = '5fcc040a8188a51baf5a6f36ca15ce05';
    const ROUND_LIMIT = 0;
    const CONNECTION_TIMEOUT = 2;
    const TIMEOUT = 5;
    const TISS_API_KEY = 'QXO7oqkH1_aifhVdi8W1GiMx4SEwzkPMTdwYjgcktOjW70aX_ve_xGDC7bTRBmQ37rH1k2ETsA3ZdCIfja0yHosRNNGwaYGGXuXFR6U4TADCRZF6lLvyjfKcg-zS5y4xQT4SNpi86vVPN5zOEFdhiZfRaKGh_U1MfHJz9IpAsyuc0ZHDHRaw0dO1tDHgQw2N4uPP0sq0kStch43q9zfZKhMsqTSNtgGVBnGRzaCkJzzuaXmfrL4Ot5ODBJ3x1tXnyVGW-p5IeZXOtIfeRWZMSnw3luiMztyY1m7p84r_qWJeVvr1J_3rR0R1EP7qAHjvX_QEnud83oqMCJppN4RCnD4sb5_fkylpyrEyuXRVvqviPx2-xiNhBwwLLkt67cNaZYBbtcaLcaZT5apXtVFW4B0IcwMHyqt_Oy3USMl3bkiBiJ7fGW6bOBidnoRCE6OqS1JTWKCkAZEoqY8rOX4A7p8YZTkamldmGbzf7sveBYhPSJvwmaUVWvzju6iEr7cB';
    const SHATEM_API_KEY = '{a9000264-381b-4c69-9af4-51fdd93b8eda}';
    const KULAN_API_KEY='UYWUVoxme116qJlmeSzl7uCsI7Mrlv0D4symnBbR0tyVjMdOMnzkhys5hOvvRoEhcOJYc8Ntcf9sM9tDpUvpz60HTFcMcnJ1mpVU5PNbxuDxJR4DyLhf10y317musSOo';
    const KULAN_ASTSTORE_ID = '2198d63c-35f3-11eb-925f-00155d20f705';
    const API_KEY_TREID = '73daf78112373b8326bea5558b0b2ec0';
    const TREID_STORAGE_IDs = [
        168102, 247102, 262102,
    ];

    protected $finalArr = [
        'brands' => [],
        'searchedNumber' => [],
        'crosses_on_stock' => [],
        'crosses_to_order' => [],
        'originNumber' => ''
    ];

    public function catalogSearch(Request $request) 
    {
        $partNumber = $this->removeAllUnnecessaries(trim($request->partNumber));

        function catalogAutopiterSearch(String $partNumber) {
            $connect = array(
                'options' => array(
                    'connection_timeout' => 1,
                    'trace' => true
                )
            );

            $client = new SoapClient("http://service.autopiter.ru/v2/price?WSDL", $connect['options']);
    
            if (!($client->IsAuthorization()->IsAuthorizationResult)) {
                $client->Authorization(array("UserID"=>"1440698", "Password"=>"B_RH019rAk", "Save"=> "true"));
            }
            
            try {
                $result = $client->FindCatalog (array("Number"=>$partNumber));
            } catch (\Throwable $th) {
                return [];
            }
            
            if (!property_exists($result->FindCatalogResult, 'SearchCatalogModel')) {
                return [];
            }
            
            $catalog = [];

            if (is_array($result->FindCatalogResult->SearchCatalogModel)) {
                foreach ($result->FindCatalogResult->SearchCatalogModel as $value) {
                    array_push($catalog, [
                        'brand' => $value->CatalogName,
                        'partnumber' => $value->Number,
                        'name' => $value->Name,
                        'guid' => '',
                        'rossko_need_to_search' => false
                    ]);
                }
            } else {
                array_push($catalog, [
                    'brand' => $result->FindCatalogResult->SearchCatalogModel->CatalogName,
                    'partnumber' => $result->FindCatalogResult->SearchCatalogModel->Number,
                    'name' => $result->FindCatalogResult->SearchCatalogModel->Name,
                    'guid' => '',
                    'rossko_need_to_search' => false
                ]);        
            }
            
            return $catalog;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 2 // общее время ожидания (подключение + ответ)
            ]
        ]);

        //поиск брэндлиста по каталогам
        $connect = array(
            'wsdl'    => 'http://api.rossko.ru/service/v2.1/GetSearch',
            'options' => array(
                'connection_timeout' => 1,
                'trace' => true,
                'stream_context' => $context
            )
        );
        
        $param = array(
            'KEY1' => self::API_KEY1_ROSSKO,
            'KEY2' => self::API_KEY2_ROSSKO,
            'text' => $partNumber,
            'delivery_id' => '000000001',
            'address_id'  => '229881'
        );
        
        try {
            $query = new SoapClient($connect['wsdl'], $connect['options']);
            
        } catch (\Throwable $th) {
            $catalog = catalogAutopiterSearch($partNumber);

            if(empty($catalog)) {
                return view('components.nothingFound');
            }

            return view('catalogSearchRes')->with([
                    'finalArr' => $catalog,
                    'only_on_stock' => $request->only_on_stock
                ]
            );
        }
        
        try {
            $result = $query->GetSearch($param);
        } catch (\Throwable $th) {
            
            $catalog = catalogAutopiterSearch($partNumber);

            if(empty($catalog)) {
                return view('components.nothingFound');
            }

            return view('catalogSearchRes')->with([
                    'finalArr' => $catalog,
                    'only_on_stock' => $request->only_on_stock
                ]
            );
        } 
       
        if ($result->SearchResult->success) {
            $catalog = [];

            if (!is_countable($result->SearchResult->PartsList->Part)) {
                array_push($catalog,[
                    'brand' => $result->SearchResult->PartsList->Part->brand,
                    'partnumber' => $result->SearchResult->PartsList->Part->partnumber,
                    'name' => $result->SearchResult->PartsList->Part->name,
                    'guid' => $result->SearchResult->PartsList->Part->guid,
                    'rossko_need_to_search' => true
                ]);
            } else {
                foreach ($result->SearchResult->PartsList->Part as $part) {
                    array_push($catalog,[
                        'brand' => $part->brand,
                        'partnumber' => $part->partnumber,
                        'name' => $part->name,
                        'guid' => $part->guid,
                        'rossko_need_to_search' => true
                    ]);
                }
            }
            
            return view('catalogSearchRes')->with([
                'finalArr' => $catalog,
                'only_on_stock' => $request->only_on_stock
            ]);
        } else {
           $catalog = catalogAutopiterSearch($partNumber);

           if(empty($catalog)) {
                return view('components.nothingFound');
            }

           return view('catalogSearchRes')->with([
                    'finalArr' => $catalog,
                    'only_on_stock' => $request->only_on_stock
                ]
            );
        }
    }

    public function getSearchedPartAndCrosses(Request $request)
    {
        $this->finalArr['originNumber'] = $request->partnumber;
        $partNumber = $this->removeAllUnnecessaries(trim($request->partnumber));
        $brand = $request->brand;

        // 1. Локальные быстрые методы (БД)
        /*$this->searchStockInOffice($brand, $partNumber);
        $this->searchGerat($brand, $partNumber);
        $this->searchVoltage($brand, $partNumber);
        $this->searchBlueStar($brand, $partNumber);*/
        $this->searchInterkom($brand, $partNumber);
        //$this->searchAdilPhaeton($brand, $partNumber);

        // 2. Подготовка для Treid
        $treidBrand = $brand;
        if ($treidBrand == 'Hyundai/Kia') { $treidBrand = 'Hyundai'; }
        else if ($treidBrand == 'Peugeot/Citroen') { $treidBrand = 'Peugeot'; }
        else if ($treidBrand == 'TOYOTA/LEXUS') { $treidBrand = 'Toyota'; }
        else if ($treidBrand == 'NISSAN/INFINITI') { $treidBrand = 'Nissan'; }

        // Сначала быстро получаем кроссы от Автотрейда (это работает быстро)
        $treidArticles = [$partNumber => [$treidBrand => 1]]; 
        $treidCrosses = $this->getTreidCrosses($partNumber);
        if (!empty($treidCrosses)) {
            foreach ($treidCrosses as $crossArt) {
                $treidArticles[$crossArt] = 1; // Автотрейд принимает массив артикулов
            }
        }
        // 2. Подготовка для Shatem
        $shatemToken = $this->getShatemToken();

        // Маппинг брендов как в вашем старом методе
        $shatemBrand = $brand;
        if ($shatemBrand == 'Citroen/Peugeot') { $shatemBrand = 'PSA'; } 
        else if ($shatemBrand == 'HYUNDAI/KIA' || $shatemBrand == 'Hyndai/Kia') { $shatemBrand = 'HYUNDAI-KIA'; } 
        else if ($shatemBrand == 'GM') { $shatemBrand = 'General Motors'; } 
        else if ($shatemBrand == 'nissan/infiniti') { $shatemBrand = 'nissan'; }

        $shatemId = $this->getShatemArticleId($shatemToken, $partNumber, $shatemBrand);

        // 3. ПАРАЛЛЕЛЬНЫЙ ПУЛ (REST API)
        $responses = Http::pool(function (Pool $pool) use ($partNumber, $brand, $shatemToken, $treidArticles, $shatemId, $request) {
            
            // Armtek (исправленный под официальный конфиг)
            $pool->as('armtek')->timeout(self::TIMEOUT)
                // Используем логин и пароль из твоего конфига
                ->withBasicAuth('ROMAN_PLANETA@MAIL.RU', 'Rimma240609')
                ->asForm()
                // ВАЖНО: Хост именно ws.armtek.ru, как в твоем файле
                ->post('http://ws.armtek.ru/api/ws_search/search', [
                    'VKORG'       => '8800',
                    'KUNNR_RG'    => '43387356',
                    'PIN'         => $partNumber,
                    'BRAND'       => $brand,
                    'QUERY_TYPE'  => '', // Из твоего метода searchArmtek
                    'format'      => 'json'
                ]);

            // Phaeton Main & Local
            $pool->as('phtn_1')->timeout(self::TIMEOUT)->get('https://api.phaeton.kz/api/Search', [
                'Article' => $partNumber, 'Brand' => $brand, 'Sources[]' => '1', 'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D', 'ApiKey' => '0UKIrpU3W3AnAfDf97Nr', 'includeAnalogs' => 'true'
            ]);
            $pool->as('phtn_2')->timeout(self::TIMEOUT)->get('https://api.phaeton.kz/api/Search', [
                'Article' => $partNumber, 'Brand' => $brand, 'Sources[]' => '2', 'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D', 'ApiKey' => 'LnxrDfpQVZz1ncuoI14e', 'includeAnalogs' => 'true'
            ]);

            // Shatem
            if ($shatemId && $shatemToken) {
                $pool->as('shatem')->timeout(self::TIMEOUT)
                    ->withToken($shatemToken)
                    ->post('https://api.shate-m.kz/api/v1/prices/search/with_article_info', [
                        [
                            'articleId' => $shatemId,
                            'includeAnalogs' => true
                        ]
                    ]);
            }

            // Forum Auto (исправлено под .kz и listGoods)
            $pool->as('forum')->timeout(self::TIMEOUT)->get('https://api.forum-auto.kz/v2/listGoods', [
                'login' => '432537_popadinets_roman',
                'pass'  => '0xJcsnuE69xI',
                'art'   => $partNumber, // в рабочем коде именно 'art'
                'br'    => $brand,
                'cross' => 1,
            ]);

            // Tiss
            $pool->as('tiss')->timeout(self::TIMEOUT)
                ->withToken(self::TISS_API_KEY) // Если константа в этом же классе
                ->get('https://api.tiss.parts/api/StockByArticle', [
                    'JSONparameter' => json_encode([
                        'Brand' => $brand,
                        'Article' => $partNumber,
                        'is_main_warehouse' => 1
                    ])
                ]);

            // Treid (Автотрейд)
            $pool->as('treid')->timeout(self::TIMEOUT)->asForm()
                ->post('https://api2.autotrade.su/?json', [
                    'data' => json_encode([
                        "auth_key" => self::API_KEY_TREID,
                        "method" => "getStocksAndPrices",
                        'params' => [
                            "storages" => self::TREID_STORAGE_IDs,
                            "items" => $treidArticles // Искомый + все кроссы
                        ]
                    ])
                ]);

            // Kulan - Искомый номер
            $pool->as('kulan_main')->timeout(self::TIMEOUT)->withHeaders([
                'token' => self::KULAN_API_KEY
            ])->get('https://connect.adkulan.kz/api/request/api/v2/catalog/article/productCart', [
                'article' => $partNumber,
                'brand' => $brand
            ]);

            // Kulan - Аналоги
            $pool->as('kulan_crosses')->timeout(self::TIMEOUT)->withHeaders([
                'token' => self::KULAN_API_KEY
            ])->get('https://connect.adkulan.kz/api/request/api/v2/catalog/article/analogues', [
                'article' => $partNumber,
                'brand' => $brand,
                'order_by' => 'price_asc'
            ]);

            // Gerat
            $pool->as('gerat')->timeout(self::TIMEOUT)->get('https://gerat.kz/bitrix/catalog_export/storage_astana.php');

            // Autopiter
            if (!$request->only_on_stock) {
                $pool->as('autopiter')->timeout(10)->get('https://api.autopiter.ru/api/v1/search', ['number' => $partNumber]);
            }
        });

        // 4. Rossko (SOAP) - пока пул долетает
        if($request->rossko_need_to_search) {
            $this->searchRossko($request->brand,  $partNumber, $request->guid);
        }
        $this->searchFebest($request->brand, $partNumber);

        // 5. Обработка результатов Пула
        foreach ($responses as $key => $res) {
            // 1. Проверяем, что это объект ответа, а не ошибка/исключение
            if (!($res instanceof \Illuminate\Http\Client\Response)) {
                \Log::warning("Поставщик {$key} не ответил (ошибка соединения или таймаут)");
                continue;
            }

            // 2. Теперь безопасно проверяем статус ответа (200 OK)
            if (!$res->ok()) {
                \Log::warning("Поставщик {$key} вернул ошибку: " . $res->status());
                continue;
            }

            // 3. Пытаемся получить данные
            $data = $res->object();
            //dd($data);
            if (!$data) continue;

            switch($key) {
                case 'armtek': $this->parseArmtek($data); break;
                case 'phtn_1': $this->parsePhaeton($data, false, $partNumber); break;
                case 'phtn_2': $this->parsePhaeton($data, true, $partNumber); break;
                case 'shatem': $this->parseShatem($data, $partNumber); break;
                case 'forum': $this->parseForum($data); break;
                case 'tiss': $this->parseTiss($data, $partNumber); break;
                case 'treid': $this->parseTreid($data, $partNumber); break;
                case 'kulan_main': $this->parseKulan($data, true, $partNumber); break;
                case 'kulan_crosses': $this->parseKulan($data, false, $partNumber); break;
                case 'febest': $this->parseFebest($data, $partNumber); break;
                case 'gerat': $this->parseGerat($res->body(), $partNumber); break;
            }
        }

        return $this->finalizeSearch($request);
    }

    // --- Методы Парсинга ---
    public function searchRossko(String $brand, String $partNumber, String $guid)
    {   
        //$start = microtime(true);
        $connect = array(
            'wsdl'    => 'http://api.rossko.ru/service/v2.1/GetSearch',
            'options' => array(
                'connection_timeout' => 1,
                'trace' => true
            )
        );
        
        $param = array(
            'KEY1' => self::API_KEY1_ROSSKO,
            'KEY2' => self::API_KEY2_ROSSKO,
            'text' => $guid,
            'delivery_id' => '000000001',
            'address_id'  => '229881'
        );
        
        $query  = new SoapClient($connect['wsdl'], $connect['options']);
        try {
            $result = $query->GetSearch($param);
        } catch (\Throwable $th) {
            return view('components.hostError');
        }
        
        $result = (json_decode(json_encode($result), true));
        
        if (!$result['SearchResult']['success']) {
            return;
        }
        //dd($result);
        //добавляем данные по искомому номеру в итоговый массив
        if ($result['SearchResult']['success'] == true) {
            if (isset($result['SearchResult']['PartsList']['Part']['stocks'])) {
                if (count($result['SearchResult']['PartsList']['Part']['stocks']['stock']) == 10) {
                    array_push($this->finalArr['brands'],  $result['SearchResult']['PartsList']['Part']['brand']);
                        
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($result['SearchResult']['PartsList']['Part']['stocks']['stock']['price']),
                            'priceWithMargine' => round($this->setPrice($result['SearchResult']['PartsList']['Part']['stocks']['stock']['price']), self::ROUND_LIMIT),
                            'qty' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['count'],
                            'multiplicity' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['multiplicity'],
                            'type' => '',
                            'delivery' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['delivery'],
                            'extra' => '',
                            'description' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['description'],
                            'deliveryStart' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryStart'],
                            'deliveryEnd' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryEnd'],
                            'supplier_name' => 'rssk',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#ed2d2f'
                        ]);
                } else {
                    foreach ($result['SearchResult']['PartsList']['Part']['stocks']['stock'] as $stockItem) {
                        array_push($this->finalArr['brands'],  $result['SearchResult']['PartsList']['Part']['brand']);
                        
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($stockItem['price']),
                            'priceWithMargine' => round($this->setPrice($stockItem['price']), self::ROUND_LIMIT),
                            'qty' => $stockItem['count'],
                            'multiplicity' => $stockItem['multiplicity'],
                            'type' => '',
                            'delivery' => $stockItem['delivery'],
                            'extra' => '',
                            'description' => $stockItem['description'],
                            'deliveryStart' => $stockItem['deliveryStart'],
                            'deliveryEnd' => $stockItem['deliveryEnd'],
                            'supplier_name' => 'rssk',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#ed2d2f'
                        ]);
                    }
                }
            }
        }
        
        //добавляем данные по кроссам в итоговый массив
        if (array_key_exists('crosses',$result['SearchResult']['PartsList']['Part'])) {
            $firstKey = array_key_first($result['SearchResult']['PartsList']['Part']['crosses']['Part']);
            $firstElem = $result['SearchResult']['PartsList']['Part']['crosses']['Part'][$firstKey];
            
            if (is_array($firstElem)) {
                foreach ($result['SearchResult']['PartsList']['Part']['crosses']['Part'] as $key => $part_stock) {
                    foreach ($part_stock['stocks'] as $key => $innerArr) {
                        $crosses_stocks = [];
                        if (count($innerArr) == 10) {
                            if (str_contains($innerArr['description'], 'Астана')) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'delivery_time' => '1.5-2 часа',
                                ];
                                array_push($this->finalArr['brands'], $part_stock['brand']);

                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'price' => round($innerArr['price']),
                                    'qty' => $innerArr['count'],
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                    'supplier_city' => 'ast',
                                    'supplier_color' => '#ed2d2f'
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                ];
                                array_push($this->finalArr['brands'] , $part_stock['brand']);

                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
                                    'supplier_city' => $innerArr['description'],
                                    'supplier_color' => '#ed2d2f'
                                ]);
                            }
                        } else {
                            foreach ($innerArr as $key => $item) {
                                if (str_contains($item['description'], 'Астана')) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['brands'],  $part_stock['brand']);

                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                        'supplier_city' => 'ast',
                                        'supplier_color' => '#ed2d2f'
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'delivery_time' => $item['deliveryEnd'],
                                    ];
                                    array_push($this->finalArr['brands'], $part_stock['brand']);
                                    
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        'supplier_name' => 'rssk',
                                        'supplier_city' => $item['description'],
                                        'supplier_color' => '#ed2d2f'
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                    foreach ($result['SearchResult']['PartsList']['Part']['crosses']['Part']['stocks'] as $key => $innerArr) {
                        $crosses_stocks = [];
                        if (count($innerArr) == 10) {
                            if (str_contains($innerArr['description'], 'Астана')) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'delivery_time' => '1.5-2 часа'
                                ];
                                array_push($this->finalArr['brands'], $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand']);
                                
                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                    'supplier_city' => 'ast',
                                    'supplier_color' => '#ed2d2f'
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                ];
                                array_push($this->finalArr['brands'], $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand']);
                                
                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'priceWithMargine' => round($this->setPrice($innerArr['price']), self::ROUND_LIMIT),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
                                    'supplier_city' => $innerArr['description'],
                                    'supplier_color' => '#ed2d2f'
                                ]);
                            }
                        } else {
                            foreach ($innerArr as $key => $item) {
                                if (str_contains($item['description'], 'Астана')) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['brands'], $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand']);
                                    
                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                        'supplier_city' => 'ast',
                                        'supplier_color' => '#ed2d2f'
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'delivery_time' => $item['deliveryEnd'],
                                    ];
                                    array_push($this->finalArr['brands'], $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand']);
                                    
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        'supplier_name' => 'rssk',
                                        'supplier_city' => $item['description'],
                                        'supplier_color' => '#ed2d2f'
                                    ]);
                                }
                            }
                        }
                    }
            }
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. rossko';
        return;
    }

    public function searchFebest(String $brand, String $partnumber)
    {
        //$start = microtime(true);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://febest.kz/api/v1/search/{pHgK46xXxD3pxbeyTtWJ}/' . $partnumber);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $result = json_decode(curl_exec($ch));
            curl_close($ch);
        } catch (\Throwable $th) {
            return;
        }

        if (gettype($result) == 'object' && property_exists($result, 'error')) {
            return;
        }
        if (!$result) {
            return;
        }
        foreach ($result as $item) {
            array_push($this->finalArr['crosses_on_stock'], [
                'brand' => $item->manufacturer,
                'article' => $item->code,
                'name' => $item->name,
                'price' => $item->price,
                'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                'qty' => $item->amount,
                'supplier_name' => 'fbst',
                'stock_legend' => 'Астана',
                'delivery_time' => '2-2.5 часа',
                'supplier_city' => 'ast',
                'supplier_color' => '#a27745',
            ]);
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. fbst';

        return;
    }

    private function parseGerat($xmlString, $partnumber)
    {
        try {
            $xml = simplexml_load_string($xmlString);
            if (!$xml) return;
            
            // Конвертируем в объект для удобства, как у тебя и было
            $json = json_decode(json_encode($xml));
            if (!isset($json->shop->offers->offer)) return;

            foreach ($json->shop->offers->offer as $item) {
                // ПРОВЕРКА НА НОЛЬ: если кол-во 0 или не указано - скипаем
                $qty = (int)($item->count ?? 0);
                if ($qty <= 0) continue;

                $vendorCode = $this->removeAllUnnecessaries($item->vendorCode);
                $description = $item->description ?? '';
                $crossNumbers = explode(', ', $description);
                
                $isFound = false;
                $isSearched = (strtolower($partnumber) == strtolower($vendorCode));

                // Проверяем, подходит ли номер (как основной или как кросс)
                foreach ($crossNumbers as $cn) {
                    if (strtolower($cn) == strtolower($partnumber) || $isSearched) {
                        $isFound = true;
                        break;
                    }
                }

                if (!$isFound) continue;

                $this->finalArr['brands'][] = $item->vendor;
                $priceWithMargin = round($this->setPrice($item->price), self::ROUND_LIMIT);

                // Собираем доп. инфо (картинки, параметры)
                $info = [
                    'pictures' => $item->picture ?? '',
                    'params' => (isset($item->param) && count($item->param) > 3) ? [
                        'OEM' => explode(',', $item->param[3]),
                        'suitable_to' => '',
                        'tech_info' => '',
                        'sizes' => count($item->param) > 4 ? [
                            'width' => $item->param[6] ?? 'нет информации',
                            'height' => $item->param[5] ?? 'нет информации',
                            'depth' => $item->param[4] ?? 'нет информации'
                        ] : [
                            'width' => 'нет информации', 'height' => 'нет информации', 'depth' => 'нет информации'
                        ]
                    ] : [],
                ];

                $resData = [
                    'brand' => $item->vendor,
                    'article' => $item->vendorCode,
                    'name' => mb_substr($item->model, 0, 60),
                    'price' => $item->price,
                    'priceWithMargine' => $priceWithMargin,
                    'qty' => $qty,
                    'supplier_name' => 'grt',
                    'supplier_city' => 'Астана',
                    'info' => $info,
                ];

                if ($isSearched) {
                    $this->finalArr['searchedNumber'][] = array_merge($resData, [
                        'supplier_color' => '#7bafcf',
                        'deliveryStart' => '1.5-2 часа',
                    ]);
                } else {
                    $this->finalArr['crosses_on_stock'][] = array_merge($resData, [
                        'delivery_time' => "1.5-2 часа",
                        'supplier_color' => '#feed00',
                        'stocks' => [[
                            'qty' => $qty,
                            'price' => $item->price,
                            'priceWithMargine' => $priceWithMargin,
                        ]],
                    ]);
                }
            }
        } catch (\Throwable $th) {
            \Log::error("Gerat parsing error: " . $th->getMessage());
        }
    }

    private function parseKulan($data, $isMain, $originNumber)
    {
        // У Кулана в разных методах разная структура (в main есть поле .data)
        $items = ($isMain) ? ($data->data ?? []) : ($data ?? []);

        if (empty($items) || isset($data->messages)) return;

        foreach ($items as $item) {
            if (!isset($item->remains)) continue;

            foreach ($item->remains as $store) {
                // Проверка склада Астаны (используем твою константу)
                // Внимание: в одном методе у них store_id, в другом id. Проверяем оба.
                $storeId = $store->store_id ?? $store->id ?? null;

                if ($storeId == self::KULAN_ASTSTORE_ID) {
                    $this->finalArr['brands'][] = $item->manufacturer;
                    $priceWithMargin = round($this->setPrice($store->price), self::ROUND_LIMIT);

                    $res = [
                        'brand' => $item->manufacturer,
                        'article' => $item->article,
                        'name' => $item->name,
                        'price' => $store->price,
                        'priceWithMargine' => $priceWithMargin,
                        'qty' => $store->quantity,
                        'supplier_name' => 'kln',
                        'supplier_city' => 'ast',
                        'supplier_color' => 'green',
                    ];

                    if ($isMain && $item->article == $originNumber) {
                        $this->finalArr['searchedNumber'][] = array_merge($res, [
                            'deliveryStart' => date('d-m-Y')
                        ]);
                    } else {
                        $this->finalArr['crosses_on_stock'][] = array_merge($res, [
                            'stock_legend' => $store->store ?? 'Склад Кулан',
                            'delivery_time' => '1.5-2 часа',
                            'stocks' => [[
                                'qty' => $store->quantity,
                                'price' => $store->price,
                                'priceWithMargine' => $priceWithMargin,
                            ]]
                        ]);
                    }
                }
            }
        }
    }

    private function parseTiss($data, $originNumber)
    {
        // Tiss возвращает массив объектов
        if (empty($data) || !is_array($data)) return;

        foreach ($data as $item) {
            $brandName = $item->brand;
            $this->finalArr['brands'][] = $brandName;

            // Предварительный расчет цены с твоей наценкой
            $priceWithMargin = round($this->setPrice($item->min_price), self::ROUND_LIMIT);

            // 1. Если это искомый номер
            if (strtolower($item->article) == strtolower($originNumber)) {
                $this->finalArr['searchedNumber'][] = [
                    'brand' => $brandName,
                    'article' => $item->article,
                    'name' => $item->article_name,
                    'price' => $item->min_price,
                    'priceWithMargine' => $priceWithMargin,
                    'qty' => $item->warehouse_offers[0]->quantity ?? 0,
                    'supplier_name' => 'tss',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#7bafcf',
                    'deliveryStart' => date('d.m.Y'),
                ];
            } 
            // 2. Если это кросс (аналог)
            else {
                $stocks = [];
                if (isset($item->warehouse_offers)) {
                    foreach ($item->warehouse_offers as $offer) {
                        $stocks[] = [
                            'qty' => $offer->quantity,
                            'price' => $offer->price,
                            'priceWithMargine' => round($this->setPrice($offer->price), self::ROUND_LIMIT)
                        ];
                    }
                }

                $this->finalArr['crosses_on_stock'][] = [
                    'brand' => $brandName,
                    'article' => $item->article,
                    'name' => $item->article_name,
                    'qty' => $item->warehouse_offers[0]->quantity ?? 0,
                    'price' => $item->min_price,
                    'priceWithMargine' => $priceWithMargin,
                    'stocks' => $stocks,
                    'supplier_name' => 'tss',
                    'stock_legend' => $item->warehouse_offers[0]->warehouse_name ?? 'Склад',
                    'delivery_time' => '1.5-2 часа',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#7bafcf',
                ];
            }
        }
    }

    private function parsePhaeton($data, $isLocal, $origin) {
        if (!isset($data->Items)) return;
        foreach ($data->Items as $item) {
            $this->finalArr['brands'][] = $item->Brand;
            $price = round($this->setPrice($item->Price), self::ROUND_LIMIT);
            
            $row = [
                'brand' => $item->Brand, 'article' => $item->Article, 'name' => mb_substr($item->Name, 0, 60),
                'qty' => $item->AvailableCount, 'price' => $item->Price, 'priceWithMargine' => $price,
                'supplier_name' => 'phtn', 'supplier_city' => $item->Warehouse
            ];

            if ($item->Warehouse == 'Астана' && $item->Article == $origin) {
                $this->finalArr['searchedNumber'][] = array_merge($row, ['delivery_time' => '1.5-2 часа']);
            } elseif ($item->Warehouse == 'Астана') {
                $this->finalArr['crosses_on_stock'][] = array_merge($row, ['delivery_time' => '1.5-2 часа', 'stocks' => [['qty' => $item->AvailableCount, 'price' => $item->Price, 'priceWithMargine' => $price]]]);
            } else {
                $row['delivery_time'] = date('d.m.Y', strtotime('+' . $item->GuaranteedDelivery .' day'));
                $this->finalArr['crosses_to_order'][] = $row;
            }
        }
    }

    private function parseArmtek($data) {
        if (!isset($data->RESP)) return;
        foreach ($data->RESP as $item) {
            if (in_array($item->KEYZAK, ['MOV0071371', 'MOV0009026'])) {
                $this->finalArr['brands'][] = $item->BRAND;
                $this->finalArr['crosses_on_stock'][] = [
                    'brand' => $item->BRAND, 'article' => $item->PIN, 'name' => $item->NAME, 'qty' => $item->RVALUE,
                    'price' => round($item->PRICE), 'priceWithMargine' => round($this->setPrice($item->PRICE), self::ROUND_LIMIT),
                    'delivery_time' => '1.5-2 часа', 'supplier_name' => 'rmtk', 'supplier_city' => 'ast'
                ];
            } else { break; }
        }
    }

    private function parseShatem($priceOffer, $partnumber)
    {
        // Если пришла ошибка или пустой ответ
        if (empty($priceOffer) || isset($priceOffer->messages)) {
            return;
        }

        foreach ($priceOffer as $priceEntity) {
            $tradeMarkName = $priceEntity->article->tradeMarkName;
            $this->finalArr['brands'][] = $tradeMarkName;

            foreach ($priceEntity->prices as $priceItem) {
                $city = $priceItem->addInfo->city;
                
                // Твои города для "В наличии" и "На заказ"
                $isTargetCity = in_array($city, [
                    'Шымкент', 'Екатеринбург', 'Алматы', 'Подольск', 'Костанай', 'Караганда', 'Астана'
                ]);

                if (!$isTargetCity) continue;

                $priceValue = $priceItem->price->value;
                $priceWithMargin = round($this->setPrice($priceValue), self::ROUND_LIMIT);

                $resData = [
                    'brand' => $tradeMarkName,
                    'article' => $priceEntity->article->code,
                    'name' => $priceEntity->article->name,
                    'price' => $priceValue,
                    'priceWithMargine' => $priceWithMargin,
                    'qty' => $priceItem->quantity->available,
                    'supplier_name' => 'shtm',
                    'supplier_color' => '#6b6b6b',
                ];

                // 1. Если это искомый артикул
                if ($priceEntity->article->code == $partnumber) {
                    if ($city == 'Астана') {
                        $this->finalArr['searchedNumber'][] = array_merge($resData, [
                            'supplier_city' => 'ast',
                            'delivery_time' => '1.5-2 часа',
                        ]);
                    } else {
                        $this->finalArr['searchedNumber'][] = array_merge($resData, [
                            'supplier_city' => 'ast', // Для совместимости с твоим старым кодом
                            'deliveryStart' => date('d.m.Y', strtotime(explode('T', $priceItem->shippingDateTime)[0])),
                        ]);
                    }
                } 
                // 2. Если это кросс (аналог)
                else {
                    $stockInfo = [
                        'qty' => $priceItem->quantity->available,
                        'price' => $priceValue,
                        'priceWithMargine' => $priceWithMargin,
                    ];

                    if ($city == 'Астана') {
                        $this->finalArr['crosses_on_stock'][] = array_merge($resData, [
                            'stock_legend' => $city,
                            'delivery_time' => '1.5-2 часа',
                            'stocks' => [$stockInfo],
                            'supplier_city' => 'ast',
                        ]);
                    } else {
                        $this->finalArr['crosses_to_order'][] = array_merge($resData, [
                            'delivery_time' => date('d.m.Y', strtotime(explode('T', $priceItem->shippingDateTime)[0])),
                            'stocks' => [$stockInfo],
                            'supplier_city' => $city,
                        ]);
                    }
                }
            }
        }
    }

    private function parseForum($data)
    {
        // В listGoods данные приходят как массив объектов сразу
        if (empty($data) || !is_iterable($data)) return;

        foreach ($data as $item) {
            // Проверка на ошибки в ответе
            if (isset($item->errors)) continue;

            $this->finalArr['brands'][] = $item->brand;
            $priceWithMargin = round($this->setPrice($item->price), self::ROUND_LIMIT);

            // Логика только для Астаны (как в твоем рабочем коде)
            if (isset($item->whse) && $item->whse == 'AST') {
                $res = [
                    'brand' => $item->brand,
                    'article' => $item->art,
                    'name' => mb_substr($item->name, 0, 60),
                    'price' => $item->price,
                    'priceWithMargine' => $priceWithMargin,
                    'qty' => $item->num,
                    'supplier_name' => 'frmt',
                    'supplier_city' => 'Астана',
                ];

                if ($item->art == $this->finalArr['originNumber']) {
                    $this->finalArr['searchedNumber'][] = array_merge($res, [
                        'delivery_time' => 'в наличии',
                        'supplier_color' => '#feed00'
                    ]);
                } else {
                    $this->finalArr['crosses_on_stock'][] = array_merge($res, [
                        'delivery_time' => '2-2.5 часа',
                        'supplier_color' => '#34689e',
                        'stocks' => [[
                            'qty' => $item->num,
                            'price' => $item->price,
                            'priceWithMargine' => $priceWithMargin,
                        ]]
                    ]);
                }
            } 
            // Если хочешь добавить и другие склады (не AST), раскомментируй:
            /*
            else {
                $this->finalArr['crosses_to_order'][] = [ ... ];
            }
            */
        }
    }

    private function parseTreid($data, $originNumber)
    {
        if (empty($data) || !isset($data->items)) return;

        foreach ($data->items as $item) {
            if (!isset($item->price) || $item->price <= 0) continue;

            // Считаем остатки только по нужным складам
            $totalQty = 0;
            if (isset($item->stocks)) {
                foreach ($item->stocks as $storageId => $stock) {
                    if (in_array($storageId, [168102, 247102, 262102]) && $stock->quantity_unpacked > 0) {
                        $totalQty += $stock->quantity_unpacked;
                    }
                }
            }

            if ($totalQty <= 0) continue;

            $this->finalArr['brands'][] = $item->brand;
            $priceWithMargin = round($this->setPrice($item->price), self::ROUND_LIMIT);

            // 1. Искомый номер
            if ($this->removeAllUnnecessaries($item->article) == $originNumber) {
                $this->finalArr['searchedNumber'][] = [
                    'brand' => $item->brand,
                    'article' => $item->article,
                    'name' => mb_substr($item->name, 0, 60),
                    'price' => $item->price,
                    'priceWithMargine' => $priceWithMargin,
                    'qty' => $totalQty,
                    'description' => 'trd',
                    'supplier_name' => 'trd',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#34689e',
                    'deliveryStart' => date('d.m.Y'),
                    'deliveryEnd' => date('d.m.Y'),
                ];
            } 
            // 2. Кроссы
            else {
                $this->finalArr['crosses_on_stock'][] = [
                    'brand' => $item->brand,
                    'article' => $item->article,
                    'name' => mb_substr($item->name, 0, 60),
                    'qty' => $totalQty,
                    'price' => round($item->price),
                    'priceWithMargine' => $priceWithMargin,
                    'supplier_name' => 'trd',
                    'delivery_time' => '1.5-2 часа',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#34689e',
                    'stocks' => [[
                        'qty' => $totalQty,
                        'price' => $item->price,
                        'priceWithMargine' => $priceWithMargin,
                    ]]
                ];
            }
        }
    }

    // --- Вспомогательные функции ---
    private function getTreidCrosses($partNumber)
    {
        try {
            $res = Http::asForm()->timeout(5)->post('https://api2.autotrade.su/?json', [
                'data' => json_encode([
                    "auth_key" => self::API_KEY_TREID,
                    "method" => "getReplacesAndCrosses",
                    'params' => ["article" => $partNumber, "brand" => '']
                ])
            ]);
            
            if ($res->ok()) {
                $data = $res->json();
                if (isset($data['items'])) {
                    return array_column($data['items'], 'article');
                }
            }
        } catch (\Exception $e) { \Log::error("Treid Crosses Error: " . $e->getMessage()); }
        return [];
    }

    private function getShatemToken()
    {
        return Cache::remember('shatem_token', 3600, function () {
            $res = Http::asForm()->post('https://api.shate-m.kz/api/v1/auth/loginByapiKey', [
                'ApiKey' => '{3f3b6eeb-709c-4dcb-be59-147ce8f9cb87}',
            ]);
            return $res->json()['access_token'] ?? null;
        });
    }

    private function getShatemArticleId($token, $number, $brand)
    {
        if (!$token) return null;

        try {
            $res = Http::withToken($token)
                ->timeout(5)
                ->get('https://api.shate-m.kz/api/v1/articles/search', [
                    'SearchString' => $number,
                    'TradeMarkNames' => $brand // Передаем нормализованный бренд
                ]);

            if ($res->ok() && !empty($res->json())) {
                $data = $res->json();
                return $data[0]['article']['id'] ?? null;
            }
        } catch (\Exception $e) {
            \Log::error("Shatem ID search error: " . $e->getMessage());
        }
        
        return null;
    }

    private function finalizeSearch($request) {
        $brands = array_unique($this->finalArr['brands']);
        sort($brands);
        $sortFn = fn($a, $b) => $a['priceWithMargine'] <=> $b['priceWithMargine'];
        usort($this->finalArr['crosses_on_stock'], $sortFn);
        usort($this->finalArr['crosses_to_order'], $sortFn);

        return view('partSearchRes', [
            'finalArr' => $this->finalArr, 'searchedPartNumber' => $this->finalArr['originNumber'],
            'chosenBrand' => $request->brand, 'brands' => $brands
        ]);
    }

    public function removeAllUnnecessaries($text) {
        return preg_replace('/[^a-zA-Z0-9]/', '', $text);
    }

    public function searchInterkom(String $brand, String $partnumber)
    {
        $searchedPart = InterkomPrice::where('oem', $partnumber)
            ->orWhere('article', $partnumber)
            ->get()
            ->toArray();
        
        if (empty($searchedPart)) {
            return;
        }
        //dd($searchedPart);
        foreach ($searchedPart as $item) {
            array_push($this->finalArr['brands'], $item['brand']);

            array_push($this->finalArr['searchedNumber'], [
                'brand' => $item['brand'],
                'article' => $item['article'],
                'name' => $item['name'],
                'price' => $item['price'],
                'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                'qty' => $item['qty'],
                'supplier_city' => 'Астана',
                'supplier_name' => 'ntrkm',
                'supplier_color' => 'green',
                'deliveryStart' => date('d.m.Y'),
            ]);    
        }
        
        return;
    }

    function setPrice($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.2; 
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.2;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 1.9;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.55;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.42;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.39;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.35;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.31;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.295;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.265;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.24;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.22;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.21;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.216;
        }
        
        // Исправленная логика расчета цены
        $user = Auth::user(); // Получаем пользователя один раз

        if ($user && $user->user_role == 'opt') {
            // Оптовик: даем скидку 7%
            return $priceWithMargin - ($priceWithMargin * 0.07);
        } else if ($user && $user->user_role == 'admin') {
            // Админ: спец. расчет
            return \App\SetPrice::setPriceForAdmin($price);
        } else {
            // Все остальные (гости из Google и обычные пользователи 'common')
            return $priceWithMargin;
        }
    }
     public function setPrice_new($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.2; 
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.2;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 1.9;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.55;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.42;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.39;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.35;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.31;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.295;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.265;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.24;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.22;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.21;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.216;
        }
        
        // Исправленная логика расчета цены
        $user = auth()->user(); // Получаем пользователя один раз

        if ($user && $user->user_role == 'opt') {
            // Оптовик: даем скидку 7%
            return $priceWithMargin - ($priceWithMargin * 0.07);
        } else if ($user && $user->user_role == 'admin') {
            // Админ: спец. расчет
            return \App\SetPrice::setPriceForAdmin($price);
        } else {
            // Все остальные (гости из Google и обычные пользователи 'common')
            return $priceWithMargin;
        }
    }
}
