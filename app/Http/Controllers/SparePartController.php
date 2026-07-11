<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SoapClient;
use Illuminate\Support\Facades\View;
use ArmtekRestClient\Http\Exception\ArmtekException as ArmtekException; 
use ArmtekRestClient\Http\Config\Config as ArmtekRestClientConfig;
use ArmtekRestClient\Http\ArmtekRestClient as ArmtekRestClient; 
use Illuminate\Pagination\LengthAwarePaginator;
use App\SetPrice as SetPrice;
use App\Models\OfficePrice;
use App\Models\gm_pricelist_from_adil;
use App\Models\XuiPoimiPrice;
use App\Models\IngvarPrice;
use App\Models\VoltagePrice;
use App\Models\BlueStarPrice;
use App\Models\InterkomPrice;
use App\Models\AdilPhaetonPrice;
use App\Models\ZakazautoPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use App\Services\Suppliers;
use Carbon;
use Collator;

class SparePartController extends Controller
{
    const API_KEY1_ROSSKO = '4adcbb9794b8e537bd2aa6272b36bdb0';
    const API_KEY2_ROSSKO = '5fcc040a8188a51baf5a6f36ca15ce05';
    const API_KEY_TREID = '73daf78112373b8326bea5558b0b2ec0';
    const TREID_STORAGE_IDs = [
        168102, 247102, 262102,
    ];
    const ARMTEK_LOGIN = 'ROMAN_PLANETA@MAIL.RU';
    const ARMTEK_PASSWORD = 'Rimma240609';
    const ARMTEK_SBIT_ORG = '8000';
    const ARMTEK_CUSTOMER = '43387356'; 
    const ARMTEK_STOCK_ASTANA = 'MOV0005505';
    const TISS_API_KEY = 'QXO7oqkH1_aifhVdi8W1GiMx4SEwzkPMTdwYjgcktOjW70aX_ve_xGDC7bTRBmQ37rH1k2ETsA3ZdCIfja0yHosRNNGwaYGGXuXFR6U4TADCRZF6lLvyjfKcg-zS5y4xQT4SNpi86vVPN5zOEFdhiZfRaKGh_U1MfHJz9IpAsyuc0ZHDHRaw0dO1tDHgQw2N4uPP0sq0kStch43q9zfZKhMsqTSNtgGVBnGRzaCkJzzuaXmfrL4Ot5ODBJ3x1tXnyVGW-p5IeZXOtIfeRWZMSnw3luiMztyY1m7p84r_qWJeVvr1J_3rR0R1EP7qAHjvX_QEnud83oqMCJppN4RCnD4sb5_fkylpyrEyuXRVvqviPx2-xiNhBwwLLkt67cNaZYBbtcaLcaZT5apXtVFW4B0IcwMHyqt_Oy3USMl3bkiBiJ7fGW6bOBidnoRCE6OqS1JTWKCkAZEoqY8rOX4A7p8YZTkamldmGbzf7sveBYhPSJvwmaUVWvzju6iEr7cB';
    const SHATEM_API_KEY = '{a9000264-381b-4c69-9af4-51fdd93b8eda}';
    const ROUND_LIMIT = -1;
    const KULAN_API_KEY='UYWUVoxme116qJlmeSzl7uCsI7Mrlv0D4symnBbR0tyVjMdOMnzkhys5hOvvRoEhcOJYc8Ntcf9sM9tDpUvpz60HTFcMcnJ1mpVU5PNbxuDxJR4DyLhf10y317musSOo';
    const KULAN_ASTSTORE_ID = '2198d63c-35f3-11eb-925f-00155d20f705';
    const CONNECTION_TIMEOUT = 2;
    const TIMEOUT = 3;

    public $partNumber = '';

    public $finalArr = [
        'originNumber' => '',
        'searchedNumber' => [],
        'crosses_in_office' => [],
        'crosses_on_stock' => [],
        'crosses_to_order' => [],
        'brands' => []
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
        

        // Fallback через Avtozakup (Tradesoft, service: provider / getProducerList) —
        // используется, когда Rossko не смог авторизоваться/ответить, или у Rossko
        // просто нет данных по этому артикулу вообще.
        /*$catalogAvtozakupSearch = function (string $partNumber) {
            $brands = $this->getBrandsByArticle($partNumber);

            if (empty($brands)) {
                return [];
            }

            $catalog = [];

            foreach ($brands as $item) {
                array_push($catalog, [
                    'brand'                  => $item['brand'],
                    'partnumber'             => $partNumber,
                    'name'                   => $item['name'],
                    'guid'                   => '',
                    'rossko_need_to_search'  => false,
                ]);
            }
            dd($catalog);
            return $catalog;
        };*/

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

            if (empty($catalog)) {
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

            if (empty($catalog)) {
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

        if (empty($catalog)) {
                return view('components.nothingFound');
            }

        return view('catalogSearchRes')->with([
                    'finalArr' => $catalog,
                    'only_on_stock' => $request->only_on_stock
                ]
            );
        }
    }

    public function getSearchedPartAndCrosses (Request $request)
    {
        $this->finalArr['originNumber'] = $request->partnumber;
        $partNumber = $this->removeAllUnnecessaries(trim($request->partnumber));
        
        if($request->rossko_need_to_search) {
            $this->searchRossko($request->brand,  $partNumber, $request->guid);
        }
        $this->searchArmtek($request->brand, $partNumber);
        $this->searchStockInOffice($request->brand, $partNumber);
        $this->searchZakazauto_kst($request->brand, $partNumber);
        $this->searchGerat($request->brand, $partNumber);
        $this->searchShatem($request->brand, $partNumber);
        $this->searchPhaeton($request->brand, $partNumber);
        $this->searchTreid($request->brand, $partNumber);
        $this->searchTiss($request->brand, $partNumber);
        $this->searchKulan($request->brand, $partNumber);
        $this->searchFebest($request->brand, $partNumber);
        $this->searchForumAuto($request->brand, $partNumber);
        $this->searchIngvar($request->brand, $partNumber);
        $this->searchVoltage($request->brand, $partNumber);
        $this->searchBlueStar($request->brand, $partNumber);
        $this->searchInterkom($request->brand, $partNumber);
        $this->searchAdilPhaeton($request->brand, $partNumber);

        if (!$request->only_on_stock) {
            $this->searchAutopiter($request->brand, $request->partnumber);
            $this->searchAvtozakup($request->brand, $partNumber);

        }

        $arr = array_unique($this->finalArr['brands']);

        usort($arr, function($a, $b) {
            if ($a == $b) {
                return 0;
            }
            return ($a < $b) ? -1 : 1;
        });

        usort($this->finalArr['crosses_on_stock'], function ($a, $b)
        {
            if ($a['priceWithMargine'] == $b['priceWithMargine']) {
                return 0;
            }
            return ($a['priceWithMargine'] < $b['priceWithMargine']) ? -1 : 1;
        });
        
        usort($this->finalArr['crosses_to_order'], function ($a, $b)
        {
            if ($a['priceWithMargine'] == $b['priceWithMargine']) {
                return 0;
            }
            return ($a['priceWithMargine'] < $b['priceWithMargine']) ? -1 : 1;
        });

        $finalArrEmpty = empty($this->finalArr['crosses_in_office']) 
            && empty($this->finalArr['crosses_on_stock']) 
            && empty($this->finalArr['searchedNumber']) 
            && empty($this->finalArr['crosses_to_order']);

        if ($finalArrEmpty) {
            return view('components.notFoundStub', [
                'article' => $request->partnumber,
                'brand'   => $request->brand ?? '',
            ]);
        }
        //dd($this->finalArr['crosses_on_stock']);
        // Если данные есть, показываем результат
        return view('partSearchRes', [
            'finalArr' => $this->finalArr,
            'searchedNumber' => $this->partNumber,
            'chosenBrand' => $request->brand,
            'brands' => $arr
        ]);
    }

    public function searchGerat(string $brand, string $partnumber)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://gerat.kz/bitrix/catalog_export/storage_astana.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $result = curl_exec($ch);
        curl_close($ch);

        $xml_snippet = simplexml_load_string($result);
        $json_convert = json_encode($xml_snippet);
        $json = json_decode($json_convert);

        if (!$json || empty($json->shop->offers->offer)) {
            return;
        }

        // защита от дублей одной и той же позиции (по артикулу+цене+кол-ву)
        $seen = [];

        foreach ($json->shop->offers->offer as $item) {
            $qty = (int) $this->xmlValueToString($item->count);

            // нулевой остаток — вообще не показываем
            if ($qty <= 0) {
                continue;
            }

            $vendorCodeNormalized = strtolower($this->removeAllUnnecessaries($item->vendorCode));
            $partnumberNormalized = strtolower($partnumber);

            $isDirectMatch = $vendorCodeNormalized === $partnumberNormalized;
            $isCrossMatch  = false;

            if (!$isDirectMatch) {
                $cross_numbers = explode(', ', (string) $item->description);
                foreach ($cross_numbers as $cross_number) {
                    if (strtolower($cross_number) === $partnumberNormalized) {
                        $isCrossMatch = true;
                        break; // одного совпадения достаточно, не плодим дубли
                    }
                }
            }

            if (!$isDirectMatch && !$isCrossMatch) {
                continue;
            }

            $dedupeKey = $vendorCodeNormalized . '|' . (string) $item->price . '|' . $qty;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            // цена закупа с поправкой +20% сразу на входе:
            // API поставщика отдаёт заниженную цену относительно их же сайта — баг на их стороне
            $purchasePrice = round(((float) $item->price) * 1.2, self::ROUND_LIMIT);

            array_push($this->finalArr['brands'], $item->vendor);

            if ($isDirectMatch) {
                array_push($this->finalArr['searchedNumber'], [
                    'brand'            => $item->vendor,
                    'article'          => $item->vendorCode,
                    'name'             => substr($item->model, 0, 60),
                    'price'            => $purchasePrice,
                    'priceWithMargine' => round($this->setPrice($purchasePrice), self::ROUND_LIMIT),
                    'qty'              => $qty,
                    'supplier_name'    => 'grt',
                    'supplier_city'    => 'Астана',
                    'supplier_color'   => '#7bafcf',
                    'deliveryStart'    => '1.5-2 часа',
                    'info' => [
                        'pictures' => $item->picture ?? '',
                        'params' => count($item->param) <= 3 ? [] : [
                            'OEM'         => explode(',', $item->param[3]),
                            'suitable_to' => '',
                            'tech_info'   => '',
                            'sizes'       => [
                                'width'  => $item->param[6] ?? 'нет информации',
                                'height' => $item->param[5] ?? 'нет информации',
                                'depth'  => $item->param[4] ?? 'нет информации',
                            ],
                        ],
                    ],
                ]);
            } else {
                $params     = $item->param ?? [];
                $infoParams = [];

                if (count($params) >= 4 && isset($params[3])) {
                    $infoParams = [
                        'OEM'         => explode(',', $params[3]),
                        'suitable_to' => '',
                        'tech_info'   => '',
                        'sizes' => [
                            'width'  => $params[6] ?? 'нет информации',
                            'height' => $params[5] ?? 'нет информации',
                            'depth'  => $params[4] ?? 'нет информации',
                        ],
                    ];
                }

                array_push($this->finalArr['crosses_on_stock'], [
                    'brand'          => $item->vendor,
                    'article'        => $item->vendorCode,
                    'name'           => substr($item->model, 0, 60),
                    'qty'            => $qty,
                    'price'          => $purchasePrice,
                    'priceWithMargine' => round($this->setPrice($purchasePrice), self::ROUND_LIMIT),
                    'delivery_time'  => '1.5-2 часа',
                    'info' => [
                        'pictures' => $item->picture ?? 0,
                        'params'   => $infoParams,
                    ],
                    'stocks' => [
                        [
                            'qty'              => $qty,
                            'price'            => $purchasePrice,
                            'priceWithMargine' => round($this->setPrice($purchasePrice), self::ROUND_LIMIT),
                        ],
                    ],
                    'supplier_name'  => 'grt',
                    'supplier_city'  => 'Астана',
                    'supplier_color' => '#feed00',
                ]);
            }
        }

        return;
    }
    public function searchAvtozakup(String $brand, String $partnumber)
    {
        try {
            $response = Http::timeout(15)->post('https://service.tradesoft.ru/3/provider/get-price-list/', [
                'user'      => env('TRADESOFT_USER'),
                'password'  => env('TRADESOFT_PASSWORD'),
                'service'   => 'provider',
                'action'    => 'getPriceList',
                'timelimit' => 10,
                'container' => [[
                    'provider' => 'avto_zakup',
                    'login'    => env('TRADESOFT_PROVIDER_LOGIN'),
                    'password' => env('TRADESOFT_PROVIDER_PASSWORD'),
                    'code'     => $partnumber,
                    'producer' => $brand,
                ]],
            ]);

            \Log::info('Avtozakup response', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            if (!$response->ok()) {
                \Log::warning('Avtozakup not ok', ['status' => $response->status()]);
                return;
            }

            $data = $response->json();

            if (!empty($data['error']) || empty($data['container'][0]['data'])) {
                \Log::warning('Avtozakup empty or error', [
                    'error' => $data['error'] ?? null,
                    'container' => $data['container'][0] ?? null,
                ]);
                return;
            }

            // Конвертация RUB → KZT (пока заглушка, потом заменишь на реальный курс)
            $convertPrice = function(float $priceRub): float {
                $rate = 1; // TODO: заменить на env('RUB_TO_KZT_RATE') или API курса
                return $priceRub * $rate;
            };

            $searchArticleClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $partnumber));

            foreach ($data['container'][0]['data'] as $item) {
                $itemArticleClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $item['code'] ?? ''));
                $isExact = $itemArticleClean === $searchArticleClean;

                $priceKzt = $convertPrice((float)($item['price'] ?? 0));

                $deliveryDaysMin = isset($item['deliverydays_min']) ? (int)$item['deliverydays_min'] : 0;
                $deliveryDaysMax = isset($item['deliverydays_max']) ? (int)$item['deliverydays_max'] : $deliveryDaysMin;

                // +5 дней доставки до Астаны от Уральска
                $deliveryDaysMin += 5;
                $deliveryDaysMax += 5;

                $deliveryDate = date('Y-m-d', strtotime("+{$deliveryDaysMax} days"));
                $deliveryText  = $deliveryDaysMin . '-' . $deliveryDaysMax . ' дн.';

                $entry = [
                    'brand'            => $item['producer'] ?? '',
                    'article'          => $item['code'] ?? '',
                    'name'             => $item['caption'] ?? '',
                    'price'            => $priceKzt,
                    'priceWithMargine' => round($this->setPrice($priceKzt), self::ROUND_LIMIT),
                    'qty'              => $item['rest'] ?? 0,
                    'delivery_time'    => $deliveryDate,
                    'deliveryStart'    => $deliveryDate,
                    'deliverydays_min' => $deliveryDaysMin,
                    'supplier_name'    => 'vtzkp',
                    'supplier_city'    => 'msk',
                    'supplier_color'   => 'linear-gradient(135deg, #1a1a1a, #cc0000)',
                    'stocks'           => [[
                        'qty'              => $item['rest'] ?? 0,
                        'price'            => $priceKzt,
                        'priceWithMargine' => round($this->setPrice($priceKzt), self::ROUND_LIMIT),
                        'delivery_time'    => $deliveryDate,
                        'supplier_city'    => 'msk',
                    ]],
                ];

                array_push($this->finalArr['brands'], $item['producer'] ?? '');

                if ($isExact) {
                    array_push($this->finalArr['searchedNumber'], $entry);
                } else {
                    array_push($this->finalArr['crosses_to_order'], $entry);
                }
            }

            // Фильтрация аналогов ПОСЛЕ цикла
            if (count($this->finalArr['crosses_to_order']) > 20) {
                $this->finalArr['crosses_to_order'] = array_values(array_filter(
                    $this->finalArr['crosses_to_order'],
                    function($analog) {
                        $days = $analog['deliverydays_min'] ?? $this->extractDaysFromText($analog['delivery_time'] ?? '');
                        return (int)$days <= 14;
                    }
                ));
            }

        } catch (\Exception $e) {
            \Log::error('Avtozakup exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
    
    public function searchPhaeton(String $brand, String $partnumber) 
    {
        $ch = curl_init();

        $params = [
            'Article' => $partnumber,
            'Brand' => $brand,
            'Sources[]' => '1',
            'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
            'ApiKey' => '0UKIrpU3W3AnAfDf97Nr',
            'includeAnalogs' => 'true'
        ];

        $url = 'https://api.phaeton.kz/api/Search?' . http_build_query($params);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $rawResponse = curl_exec($ch);
        $curlErrno   = curl_errno($ch);
        $curlError   = curl_error($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        \Log::channel('phaeton')->info('Phaeton search (source 1, Астана)', [
            'article'    => $partnumber,
            'brand'      => $brand,
            'url'        => $url,
            'http_code'  => $httpCode,
            'curl_errno' => $curlErrno,
            'curl_error' => $curlError,
            'raw_body'   => substr((string) $rawResponse, 0, 1000),
        ]);

        try {
            $response = json_decode($rawResponse);
        } catch (\Throwable $th) {
            \Log::channel('phaeton')->error('Phaeton json_decode exception (source 1)', [
                'article' => $partnumber,
                'message' => $th->getMessage(),
            ]);
            return;
        }
        //dd($response);
        if (!$response || $response->IsError) {
            \Log::channel('phaeton')->warning('Phaeton empty or IsError (source 1)', [
                'article'   => $partnumber,
                'brand'     => $brand,
                'is_error'  => $response->IsError ?? null,
                'error_msg' => $response->ErrorMessage ?? null,
            ]);
            return;
        }

        \Log::channel('phaeton')->info('Phaeton items count (source 1)', [
            'article' => $partnumber,
            'count'   => count($response->Items ?? []),
        ]);

        foreach ($response->Items as $item) {
            if ($item->Warehouse == 'Астана') {
                if ($item->Article == $partnumber) {
                    array_push($this->finalArr['brands'], $item->Brand);

                    array_push($this->finalArr['searchedNumber'], [
                        'brand' => $item->Brand,
                        'article' => $item->Article,
                        'name' => substr($item->Name, 0, 60),
                        'price' => $item->Price,
                        'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                        'qty' => $item->AvailableCount,
                        'multiplicity' => '',
                        'type' => '',
                        'delivery' => '',
                        'extra' => '',
                        'description' => 'phtn',
                        'deliveryStart' => date('d.m.Y'),
                        'deliveryEnd' => date('d.m.Y'),
                        'supplier_name' => 'phtn',
                        'supplier_city' => 'ast',
                        'supplier_color' => '#feed00'
                    ]); 
                } else {
                    array_push( $this->finalArr['brands'], $item->Brand);

                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand' => $item->Brand,
                        'article' => $item->Article,
                        'name' => substr($item->Name, 0, 60),
                        'qty' => $item->AvailableCount,
                        'stocks' => [
                            'qty' => $item->AvailableCount,
                            'price' => $item->Price,
                            'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                        ],
                        'price' => $item->Price,
                        'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                        'supplier_name' => 'phtn',
                        'delivery_date' => '',
                        'delivery_time' => '1.5-2 часа',
                        'supplier_city' => $item->Warehouse,
                        'supplier_color' => '#feed00'
                    ]);
                }           
            } else {
                array_push($this->finalArr['brands'], $item->Brand);

                array_push($this->finalArr['crosses_to_order'], [
                    'brand' => $item->Brand,
                    'article' => $item->Article,
                    'name' => substr($item->Name, 0, 60),
                    'qty' => $item->AvailableCount,
                    'price' => $item->Price,
                    'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                    'delivery_time' => date('d.m.Y', strtotime('+' . $item->GuaranteedDelivery .'day')),
                    'stocks' => [
                        [
                            'qty' => $item->AvailableCount,
                            'price' => $item->Price,
                            'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                        ]
                    ],
                    'supplier_name' => 'phtn',
                    'supplier_city' => $item->Warehouse,
                    'supplier_color' => '#feed00'
                ]); 
            }
        }

        //поиск товара у локальных поставщиков
        $ch1 = curl_init();

        $params1 = [
            'Article' => $partnumber,
            'Brand' => $brand,
            'Sources[]' => '2',
            'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
            'ApiKey' => 'LnxrDfpQVZz1ncuoI14e',
            'includeAnalogs' => 'true'
        ];

        $url1 = 'https://api.phaeton.kz/api/Search?' . http_build_query($params1);

        curl_setopt($ch1, CURLOPT_URL, $url1);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $rawResponse1 = curl_exec($ch1);
        $curlErrno1   = curl_errno($ch1);
        $curlError1   = curl_error($ch1);
        $httpCode1    = curl_getinfo($ch1, CURLINFO_HTTP_CODE);

        \Log::channel('phaeton')->info('Phaeton search (source 2, локальные поставщики)', [
            'article'    => $partnumber,
            'brand'      => $brand,
            'url'        => $url1,
            'http_code'  => $httpCode1,
            'curl_errno' => $curlErrno1,
            'curl_error' => $curlError1,
            'raw_body'   => substr((string) $rawResponse1, 0, 1000),
        ]);

        try {
            $response1 = json_decode($rawResponse1);
        } catch (\Throwable $th) {
            \Log::channel('phaeton')->error('Phaeton json_decode exception (source 2)', [
                'article' => $partnumber,
                'message' => $th->getMessage(),
            ]);
            return;
        }

        if (!$response1 || $response1->IsError) {
            \Log::channel('phaeton')->warning('Phaeton empty or IsError (source 2)', [
                'article'   => $partnumber,
                'brand'     => $brand,
                'is_error'  => $response1->IsError ?? null,
                'error_msg' => $response1->ErrorMessage ?? null,
            ]);
            return;
        }

        \Log::channel('phaeton')->info('Phaeton items count (source 2)', [
            'article' => $partnumber,
            'count'   => count($response1->Items ?? []),
        ]);

        foreach ($response1->Items as $item) {
            array_push($this->finalArr['brands'], $item->Brand);

            array_push($this->finalArr['crosses_to_order'], [
                'brand' => $item->Brand,
                'article' => $item->Article,
                'name' => substr($item->Name, 0, 60),
                'qty' => $item->AvailableCount,
                'price' => $item->Price,
                'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                'delivery_time' => date('d.m.Y', strtotime('+' . $item->GuaranteedDelivery .'day')),
                'stocks' => [
                    [
                        'qty' => $item->AvailableCount,
                        'price' => $item->Price,
                        'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                    ]
                ],
                'supplier_name' => 'phtn',
                'supplier_city' => $item->Warehouse,
                'supplier_color' => '#feed00'
            ]);
        }

        return;
    }

    public function searchForumAuto(String $brand, String $partnumber)
    {
        //$start = microtime(true);
        //поиск товара в наличии в астане
        $ch = curl_init();

        $params = [
            'login' => '432537_popadinets_roman',
            'pass' => '0xJcsnuE69xI',
            'art' => $partnumber,
            'cross' => 1,
            'br' => $brand,
        ];
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.forum-auto.kz/v2/listGoods?login=432537_popadinets_roman&pass=0xJcsnuE69xI' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response = json_decode(curl_exec($ch));
            
        } catch (\Throwable $th) {
            return;
        }
        
		if (!$response || gettype($response) == 'object' && property_exists($response, 'errors')) {
            return;
        }
		//dd($response);
        foreach ($response as $item) {
            if ($item->whse == 'AST') {
                if ($item->art == $partnumber) {
                    array_push($this->finalArr['brands'], $item->brand);

                    array_push($this->finalArr['searchedNumber'], [
                        'brand' => $item->brand,
                        'article' => $item->art,
                        'name' => substr($item->name, 0, 60),
                        'price' => $item->price,
                        'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                        'qty' => $item->num,
                        'multiplicity' => '',
                        'type' => '',
                        'delivery' => '',
                        'extra' => '',
                        'description' => 'frmt',
                        'deliveryStart' => date('d.m.Y'),
                        'deliveryEnd' => date('d.m.Y'),
                        'supplier_name' => 'frmt',
                        'supplier_city' => 'Астана',
                        'supplier_color' => '#333'
                    ]); 
                } else {
                    array_push($this->finalArr['brands'], $item->brand);

                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand' => $item->brand,
                        'article' => $item->art,
                        'name' => substr($item->name, 0, 60),
                        'qty' => $item->num,
                        'price' => $item->price,
                        'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                        'supplier_name' => 'frmt',
                        'delivery_date' => '',
                        'delivery_time' => '2-2.5 часа',
                        'supplier_city' => 'Астана',
                        'supplier_color' => '#333'
                    ]);
                }           
            }
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. frmt';
        return;
    }

    public function searchTreid (String $brand, String $partnumber) 
    {
        //$start = microtime(true);
        if ($brand == 'Hyundai/Kia') {
            $brand = 'Hyundai';
        } else if ($brand == 'Peugeot/Citroen') {
            $brand = 'Peugeot';
        } else if ($brand == 'TOYOTA/LEXUS') {
            $brand = 'Toyota';
        } else if ($brand == 'NISSAN/INFINITI') {
            $brand = 'Nissan';
        }

        $url = "https://api2.autotrade.su/?json";

        //поиск по конкретно запрошенному номеру
        $request_data_searched_number = array(
            "auth_key" => self::API_KEY_TREID,
            "method" => "getStocksAndPrices",
            'params' => array(
                "storages" => self::TREID_STORAGE_IDs,
                "items" => array (
                    $partnumber => array(
                        $brand => 1
                    )
                )
            )
        );
        
        $request_data_searched_number = 'data=' . json_encode($request_data_searched_number);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data_searched_number);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $html = curl_exec($ch);
        curl_close($ch);
        
        try {
            $result = json_decode($html, true);
        } catch (\Throwable $th) {
            return;
        }
        
        //помещаем найденные позиции в итоговый массив
        if ($result && strlen($result['message']) <= 2 && !empty($result)) {
            foreach ($result['items'] as $key => $item) {
                if (strlen($result['message']) <= 2) {
                    if ($item['price']) {
                        $searched_number_stocks = 0;
                            foreach ($item['stocks'] as $key => $stock) {
                                if ($stock['quantity_unpacked'] > 0) {
                                    $searched_number_stocks += 1;
                                }
                            }
                            if(!empty($searched_number_stocks)) {
                                array_push($this->finalArr['brands'], $item['brand']);
                                
                                array_push($this->finalArr['searchedNumber'], [
                                    'guid' => '',
                                    'brand' => $item['brand'],
                                    'article' => $item['article'],
                                    'name' => substr($item['name'], 0, 60),
                                    'item_id' => $item['id'],
                                    'price' => $item['price'],
                                    'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                                    'qty' => $searched_number_stocks,
                                    'multiplicity' => '',
                                    'type' => '',
                                    'delivery' => '',
                                    'extra' => '',
                                    'description' => 'trd',
                                    'deliveryStart' => date('d.m.Y'),
                                    'deliveryEnd' => date('d.m.Y'),
                                    'supplier_name' => 'trd',
                                    'supplier_city' => 'ast',
                                    'supplier_color' => '#0c529c'
                                ]);
                            }
                        }
                    }
            }
        }
        
        $request_data_search_crosses = array(
            "auth_key" => self::API_KEY_TREID,
            "method" => "getReplacesAndCrosses",
            'params' => array(
                "article" => $partnumber,
                "brand" => ''
            )
        );
        $request_data_search_crosses = 'data=' . json_encode($request_data_search_crosses);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data_search_crosses);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $html = curl_exec($ch);
        curl_close($ch);

        try {
            $result = json_decode($html, true);
        } catch (\Throwable $th) {
            return;
        }
        
        if (empty($result) || !$result) {
            return;
        } else if (array_key_exists('message', $result) && $result['message'] != 'Ok') {
            return;
        }
        
        //проверка остатков кросс-номеров на складе 
        $crossArr = [];
        
        foreach ($result['items'] as $resultItem) {
            $crossArr[$resultItem['article']] = 1;
        }
        
        $request_data = array(
            "auth_key" => self::API_KEY_TREID,
            "method" => "getStocksAndPrices",
            "params" => array(
                "storages" => self::TREID_STORAGE_IDs,
                "items" => $crossArr   
            )
        );

        $request_data = 'data=' . json_encode($request_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        $html = curl_exec($ch);
        curl_close($ch);

        try {
            $result = json_decode($html, true);
        } catch (\Throwable $th) {
            return;
        }
        if(!$result) {
			return;
		}
        if (!array_key_exists('items', $result) || empty($result['items'] || array_key_exists('message', $result))) {
            return;
        } 
       
        //помещаем кроссы в наличии в итоговый массив
        foreach ($result['items'] as $item) {
            if (array_key_exists('price', $item)) {
                $crosses_stocks = 0;
                foreach ($item['stocks'] as $key => $stock) {
                    if ($stock['quantity_unpacked'] > 0 ) {
                        if ($key == 168102 || $key == 247102 || $key == 262102) {
                            $crosses_stocks += $stock['quantity_unpacked'];
                        }
                    }
                }
                if (!empty($crosses_stocks)) {
                    if ($this->removeAllUnnecessaries($item['article']) != $partnumber) {
                        array_push( $this->finalArr['brands'], $item['brand']);

                        array_push($this->finalArr['crosses_on_stock'], [
                            'id' => $item['id'],
                            'brand' => $item['brand'],
                            'article' => $item['article'],
                            'name' => substr($item['name'], 0, 60),
                            'qty' => $crosses_stocks,
                            'price' => round($item['price']),
                            'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                            'supplier_name' => 'trd',
                            'extra' => [
                                'photo' => ''
                            ],
                            'delivery_date' => '',
                            'delivery_time' => '1.5-2 часа',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#34689e'
                        ]); 
                    }
                } 
            }
        }

        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. trd';
        return;
    }

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

    public function getRosskoPricesOnly(String $brand, String $partNumber)
    {
        $connect = [
            'wsdl'    => 'http://api.rossko.ru/service/v2.1/GetSearch',
            'options' => ['connection_timeout' => 5, 'trace' => true]
        ];
        
        try {
            $query = new \SoapClient($connect['wsdl'], $connect['options']);
            
            // 1. Сначала ищем GUID (точно так же)
            $paramSearch = [
                'KEY1' => self::API_KEY1_ROSSKO,
                'KEY2' => self::API_KEY2_ROSSKO,
                'text' => $partNumber,
                'delivery_id' => '000000001',
                'address_id'  => '229881'
            ];

            $searchResult = $query->GetSearch($paramSearch);
            $searchData = json_decode(json_encode($searchResult), true);
            
            if (!isset($searchData['SearchResult']['PartsList']['Part'])) return [];

            $partsFound = $searchData['SearchResult']['PartsList']['Part'];
            $targetGuid = null;

            // Определяем GUID основного товара (сверяем бренд)
            if (isset($partsFound['guid'])) {
                if (strtoupper($partsFound['brand']) === strtoupper($brand)) $targetGuid = $partsFound['guid'];
            } else {
                foreach ($partsFound as $p) {
                    if (strtoupper($p['brand']) === strtoupper($brand)) {
                        $targetGuid = $p['guid'];
                        break;
                    }
                }
            }

            if (!$targetGuid) return [];

            // 2. Запрос за полным списком (товары + кроссы) по GUID
            $paramFull = [
                'KEY1' => self::API_KEY1_ROSSKO,
                'KEY2' => self::API_KEY2_ROSSKO,
                'text' => $targetGuid,
                'delivery_id' => '000000001',
                'address_id'  => '229881'
            ];

            $finalResult = $query->GetSearch($paramFull);
            $finalData = json_decode(json_encode($finalResult), true);
            
            $allOffers = [];
            $mainPart = $finalData['SearchResult']['PartsList']['Part'] ?? null;

            if (!$mainPart) return [];

            // --- Внутренняя функция для сбора стоков (чтобы не дублировать код) ---
            $collectFromPart = function($part) use (&$allOffers) {
                if (!isset($part['stocks']['stock'])) return;
                
                $stocks = $part['stocks']['stock'];
                if (isset($stocks['id'])) $stocks = [$stocks]; // Если один склад

                foreach ($stocks as $stock) {
                    $isAstana = str_contains($stock['description'] ?? '', 'Астана') || str_contains($stock['description'] ?? '', 'Акжол');
                    
                    $allOffers[] = [
                        'brand'   => (string)$part['brand'],
                        'article' => (string)$part['partnumber'],
                        'name'    => (string)($part['name'] ?? 'Запчасть'),
                        'qty'     => (int)($stock['count'] ?? 0),
                        'price'   => $stock['price'],
                        'priceWithMargine' => $this->setPrice($stock['price']),
                        'delivery_time'    => $isAstana ? '1.5-2 часа' : ($stock['deliveryEnd'] ?? '3-5 дней'),
                        'supplier_city'    => $isAstana ? 'ast' : ($stock['description'] ?? 'РФ/Склад')
                    ];
                }
            };

            // 3. Собираем основной товар
            $collectFromPart($mainPart);

            // 4. Собираем кроссы (аналоги)
            if (isset($mainPart['crosses']['Part'])) {
                $crosses = $mainPart['crosses']['Part'];
                // Если кросс всего один — превращаем в массив
                if (isset($crosses['guid'])) $crosses = [$crosses];

                foreach ($crosses as $crossPart) {
                    $collectFromPart($crossPart);
                }
            }

            return $allOffers;

        } catch (\Throwable $th) {
            return [];
        }
    }

    public function searchArmtek(String $brand, String $partnumber)
    {
        //$start = microtime(true);
        require_once '../config.php';
        require_once '../autoloader.php';

        try {
            // init configuration 
            $armtek_client_config = new ArmtekRestClientConfig($user_settings);  

            // init client
            $armtek_client = new ArmtekRestClient($armtek_client_config);


            $params = [
                'VKORG'         => '8800'       
                ,'KUNNR_RG'     => '43387356'
                ,'PIN'          => $partnumber
                ,'BRAND'        => $brand
                ,'QUERY_TYPE'   => ''
                ,'KUNNR_ZA'     => ''
                ,'INCOTERMS'    => ''
                ,'VBELN'        => ''
            ];

            // requeest params for send
            $request_params = [
                'url' => 'search/search',
                'params' => [
                    'VKORG'         => !empty($params['VKORG'])?$params['VKORG']:(isset($ws_default_settings['VKORG'])?$ws_default_settings['VKORG']:'')       
                    ,'KUNNR_RG'     => isset($params['KUNNR_RG'])?$params['KUNNR_RG']:(isset($ws_default_settings['KUNNR_RG'])?$ws_default_settings['KUNNR_RG']:'')
                    ,'PIN'          => isset($params['PIN'])?$params['PIN']:''
                    ,'BRAND'        => isset($params['BRAND'])?$params['BRAND']:''
                    ,'QUERY_TYPE'   => isset($params['QUERY_TYPE'])?$params['QUERY_TYPE']:''
                    ,'KUNNR_ZA'     => isset($params['KUNNR_ZA'])?$params['KUNNR_ZA']:(isset($ws_default_settings['KUNNR_ZA'])?$ws_default_settings['KUNNR_ZA']:'')
                    ,'INCOTERMS'    => isset($params['INCOTERMS'])?$params['INCOTERMS']:(isset($ws_default_settings['INCOTERMS'])?$ws_default_settings['INCOTERMS']:'')
                    ,'VBELN'        => isset($params['VBELN'])?$params['VBELN']:(isset($ws_default_settings['VBELN'])?$ws_default_settings['VBELN']:'')
                    ,'format'       => 'json'
                ]
            ];

            // send data
            $response = $armtek_client->post($request_params);
            
            // in case of json
            $json_responce_data = $response->json();
            
            if (!$json_responce_data) {
                return;
            }

            if(property_exists($json_responce_data, 'MESSAGES') && !empty($json_responce_data->MESSAGES)) {
                return;
            }
            if(gettype($json_responce_data->RESP) == 'object') {
                if(property_exists($json_responce_data->RESP, 'MSG') || property_exists($json_responce_data->RESP, 'ERROR')) {
                    return;
                }
            }
            if(gettype($json_responce_data->RESP) == 'array'){
                if(array_key_exists('MSG', $json_responce_data->RESP)) {
                    return;
                }
            }
            
            
            foreach ($json_responce_data->RESP as $key => $crossItem) {
                if ($crossItem->KEYZAK == 'MOV0071371' || $crossItem->KEYZAK == 'MOV0009026') {
                    array_push($this->finalArr['brands'], $crossItem->BRAND);
                    
                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand' => $crossItem->BRAND,
                        'article' => $crossItem->PIN,
                        'name' => $crossItem->NAME,
                        'stock_legend' => 'armtek_ast',
                        'qty' => $crossItem->RVALUE,
                        'price' => round($crossItem->PRICE),
                        'priceWithMargine' => round($this->setPrice($crossItem->PRICE), self::ROUND_LIMIT),
                        'delivery_time' => '1.5-2 часа',
                        'stocks' => [
                            [
                                'qty' => $crossItem->RVALUE,
                                'price' => $crossItem->PRICE,
                                'priceWithMargine' => round($this->setPrice($crossItem->PRICE), self::ROUND_LIMIT),
                            ]
                        ],
                        'supplier_name' => 'rmtk',
                        'supplier_city' => 'ast'
                    ]);
                } else {
                    break;
                }
            }
        } catch (ArmtekException $e) {
            $json_responce_data = $e -> getMessage(); 
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. armtek';
        return;
    }

    public function searchShatem(String $brand, String $partnumber)
    {
        //$start = microtime(true);
        if ($brand == 'Citroen/Peugeot') {
            $brand = 'PSA';
        } else if ($brand == 'HYUNDAI/KIA' || $brand == 'Hyndai/Kia') {
            $brand = 'HYUNDAI-KIA';
        } else if ($brand == 'GM') {
            $brand = 'General Motors';
        } else if ($brand == 'nissan/infiniti') {
            $brand = 'nissan';
        }
        
        //получение токена
        $request_params = [
            'ApiKey' => '{3f3b6eeb-709c-4dcb-be59-147ce8f9cb87}',
        ];
        $ch = curl_init('https://api.shate-m.kz/api/v1/auth/loginByapiKey');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        try {
            $response = json_decode(curl_exec($ch));
            
        } catch (\Throwable $th) {
            return;
        }
        
        if(!$response || !property_exists($response, 'access_token')) {
            return;
        }
        
        $access_token = $response->access_token;
        
        //получение внутреннего id товара
        $params = [
            'SearchString' => $partnumber,
            'TradeMarkNames' => $brand
        ];
       
        $ch1 = curl_init();
        $resUrl = 'https://api.shate-m.kz/api/v1/articles/search?' . http_build_query($params);
        curl_setopt($ch1, CURLOPT_URL, $resUrl);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true); 
        $headers = [
            'Authorization:Bearer ' . $access_token,
        ];
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch1, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $html = json_decode(curl_exec($ch1));
        } catch (\Throwable $th) {
            return;
        }

        if (empty($html)) {
            return ;
        }
        $articleId = $html[0]->article->id;

        //получение ценового предложения
        $headers1 = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
        ];
        $request_params1 = [
            array(
                'articleId' => $articleId,
                'includeAnalogs' => true
            )
        ];
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, "https://api.shate-m.kz/api/v1/prices/search/with_article_info");
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($request_params1));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers1);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch2, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch2, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $priceOffer = json_decode(curl_exec($ch2));
        } catch (\Throwable $th) {
            return;
        }

        if (empty($priceOffer) || isset($priceOffer->messages)) {
            return;
        }
        
        curl_close($ch2);
        
        foreach ($priceOffer as $key => $priceEntity) {
            array_push($this->finalArr['brands'], $priceEntity->article->tradeMarkName);

            if ($priceEntity->article->code == $partnumber) {
                foreach ($priceEntity->prices as $priceItem) {
                    if (
                        $priceItem->addInfo->city == 'Шымкент' || $priceItem->addInfo->city == 'Екатеринбург' || $priceItem->addInfo->city == 'Алматы'||
                        $priceItem->addInfo->city == 'Подольск' || $priceItem->addInfo->city == 'Костанай' || $priceItem->addInfo->city == 'Караганда'
                    ) {
                        array_push($this->finalArr['searchedNumber'], [
                            'brand' => $priceEntity->article->tradeMarkName,
                            'article' => $priceEntity->article->code,
                            'name' => $priceEntity->article->name,
                            'price' => $priceItem->price->value,
                            'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                            'qty' => $priceItem->quantity->available,
                            'supplier_city' => 'ast',
                            'supplier_name' => 'shtm',
                            'supplier_color' => '#6b6b6b',
                            'deliveryStart' => date('d.m.Y', strtotime(stristr($priceItem->shippingDateTime, 'T', true))),
                        ]);
                    } else if ($priceItem->addInfo->city  == 'Астана') {
                        array_push($this->finalArr['searchedNumber'], [
                            'brand' => $priceEntity->article->tradeMarkName,
                            'article' => $priceEntity->article->code,
                            'name' => $priceEntity->article->name,
                            'price' => $priceItem->price->value,
                            'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                            'qty' => $priceItem->quantity->available,
                            'supplier_city' => 'ast',
                            'supplier_name' => 'shtm',
                            'supplier_color' => '#6b6b6b',
                            'delivery_time' => '1.5-2 часа',
                        ]);
                    }
                }
                
            } else {
                foreach ($priceEntity->prices as $priceItem) {
                    if($priceItem->addInfo->city == 'Астана') {
                        array_push($this->finalArr['crosses_on_stock'], [
                            'brand' => $priceEntity->article->tradeMarkName,
                            'article' => $priceEntity->article->code,
                            'name' => $priceEntity->article->name,
                            'stock_legend' => $priceItem->addInfo->city,
                            'qty' => $priceItem->quantity->available,
                            'price' => $priceItem->price->value,
                            'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                            'delivery_time' => '1.5-2 часа',
                            'stocks' => [
                                [
                                    'qty' => $priceItem->quantity->available,
                                    'price' => $priceItem->price->value,
                                    'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'shtm',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#6b6b6b',
                        ]);
                    } else if (
                        $priceItem->addInfo->city == 'Шымкент' || $priceItem->addInfo->city == 'Екатеринбург' || $priceItem->addInfo->city == 'Алматы'||
                        $priceItem->addInfo->city == 'Подольск' || $priceItem->addInfo->city == 'Костанай' || $priceItem->addInfo->city == 'Караганда'
                    ) {
                        array_push($this->finalArr['crosses_to_order'], [
                            'brand' => $priceEntity->article->tradeMarkName,
                            'article' => $priceEntity->article->code,
                            'name' => $priceEntity->article->name,
                            'qty' => $priceItem->quantity->available,
                            'price' => $priceItem->price->value,
                            'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                            'delivery_time' => date('d.m.Y', strtotime(stristr($priceItem->shippingDateTime, 'T', true))),
                            'stocks' => [
                                [
                                    'qty' => $priceItem->quantity->available,
                                    'price' => $priceItem->price->value,
                                    'priceWithMargine' => round($this->setPrice($priceItem->price->value), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'shtm',
                            'supplier_city' => $priceItem->addInfo->city,
                            'supplier_color' => '#6b6b6b',
                        ]);
                    }
                }
            }
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. shtm';
        return;
    }

    public function searchKulan(String $brand, String $partnumber)
    {
        $start = microtime(true);
        //получение остатков искомого номера
        $ch = curl_init();

        $headers = [
            'token: ' . self::KULAN_API_KEY,
            'Content-Type: application/json'
        ];

        $params = [
            'article' => $partnumber,
            'brand' => $brand
        ];
        
        curl_setopt($ch, CURLOPT_URL, 'https://connect.adkulan.kz/api/request/api/v2/catalog/article/productCart?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response = json_decode(curl_exec($ch));
        } catch (\Throwable $th) {
            return;
        }
        
        if (!$response || property_exists($response, 'messages') || empty($response)) {
            return;
        }

        foreach ($response->data as $key => $item) {
            foreach ($item->remains as $store) {
                if($store->store_id == self::KULAN_ASTSTORE_ID) {
                    array_push($this->finalArr['brands'], $item->manufacturer);

                    array_push($this->finalArr['searchedNumber'], [
                        'brand' => $item->manufacturer,
                        'article' => $item->article,
                        'name' => $item->name,
                        'price' => $store->price,
                        'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                        'qty' => $store->quantity,
                        'supplier_city' => 'ast',
                        'supplier_name' => 'kln',
                        'supplier_color' => '#0000ff',
                        'deliveryStart' => date('d-m-Y'),
                    ]);
                }
            }
        }

        //получение остатков аналогов
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, 'https://connect.adkulan.kz/api/request/api/v2/catalog/article/analogues?' . http_build_query($params) . '&order_by=price_asc');
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch1, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        try {
            $response1 = json_decode(curl_exec($ch1));
        } catch (\Throwable $th) {
            return;
        }

        curl_close($ch1);

        if (gettype($response1) == 'object' && property_exists($response1, 'messages')) {
            return;
        }
        
        if (empty($response1) || !$response1) {
            return;
        }

        foreach ($response1 as $item) {
            foreach ($item->remains as $store) {
                if($store->id == self::KULAN_ASTSTORE_ID) {
                    array_push($this->finalArr['brands'], $item->manufacturer);

                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand' => $item->manufacturer,
                        'article' => $item->article,
                        'name' => $item->name,
                        'stock_legend' => $store->store,
                        'qty' => $store->quantity,
                        'price' => $store->price,
                        'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                        'delivery_time' => '1.5-2 часа',
                        'stocks' => [
                            [
                                'qty' => $store->quantity,
                                'price' => $store->price,
                                'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                            ]
                        ],
                        'supplier_name' => 'kln',
                        'supplier_city' => 'ast',
                        'supplier_color' => '#0000ff',
                    ]);
                }
            }
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. kulan';
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

    public function searchTiss(string $brand, string $partnumber)
    {
        $apiUrl = 'https://api.tabys.parts/external/v1/product-offers/by-brand-and-product-code';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Accept'              => 'application/json',
                    'Accept-Language'     => 'ru-RU',
                    'Content-Type'        => 'application/json',
                    'X-External-Api-Key'  => env('TISS_API_KEY'),
                ])
                ->timeout(self::TIMEOUT)
                ->post($apiUrl, [
                    'products' => [
                        [
                            'productCode' => $partnumber,
                            'brandName'   => $brand,
                        ],
                    ],
                    'contractId'                    => env('TISS_CONTRACT_ID'),
                    'outletId'                      => env('TISS_OUTLET_ID'),
                    'priceFrom'                     => 0,
                    'priceTo'                       => 0,
                    'deliveryMinDays'               => 0,
                    'deliveryMaxDays'               => 0,
                    'offersMaxNum'                  => 0,
                    'orderByPrice'                  => true,
                    'enableAnalog'                  => true,
                    'warehouses'                    => [env('TISS_WAREHOUSE_ID')],
                    'isInStockInHomeWarehousesOnly' => false,
                ]);
        } catch (\Throwable $th) {
            return;
        }

        if (!$response->successful()) {
            \Illuminate\Support\Facades\Log::warning('TISS API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return;
        }

        $data = $response->json();
        //dd($data);
        if (empty($data) || !is_array($data)) {
            return;
        }

        $cleanPartnumber = $this->removeAllUnnecessaries($partnumber);

        // Верхний уровень ответа — объект с динамическими ключами (по каждому запрошенному товару)
        foreach ($data as $group) {
            if (empty($group['items']) || !is_array($group['items'])) {
                continue;
            }

            foreach ($group['items'] as $item) {
                $itemBrand        = (string) ($item['brandName'] ?? '');
                $itemArticle      = (string) ($item['productCode'] ?? $item['displayProductCode'] ?? '');
                $cleanItemArticle = $this->removeAllUnnecessaries($itemArticle);
                $name             = substr((string) ($item['productName'] ?? 'Запчасть'), 0, 60);

                if (empty($item['offers']) || !is_array($item['offers'])) {
                    continue;
                }

                // Точное совпадение — тот же артикул и бренд, что искали
                $isExactMatch = ($cleanItemArticle === $cleanPartnumber)
                    && (strtoupper($itemBrand) === strtoupper($brand));

                foreach ($item['offers'] as $offer) {
                    $price = (float) ($offer['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }

                    $warehouseName = (string) ($offer['warehouseName'] ?? '');
                    $isAstana      = str_contains($warehouseName, 'Астана') || str_contains($warehouseName, 'Маскеу');

                    $qtyNow      = (int) ($offer['amount'] ?? 0);
                    $qtyExpected = (int) ($offer['expectedAmount'] ?? 0);

                    $deliveryInfo = $offer['deliveryInfo'] ?? [];
                    $deliveryText = $isAstana
                        ? '1.5-2 часа'
                        : (!empty($deliveryInfo['workDays'])
                            ? $deliveryInfo['workDays'] . ' дн.'
                            : ($deliveryInfo['timeFrame'] ?? '3-5 дней'));

                    $supplierCity = $isAstana ? 'ast' : ($warehouseName ?: 'РФ/Склад');

                    if ($isExactMatch) {
                        // Искомый номер — только если реально есть в наличии сейчас
                        if ($qtyNow > 0) {
                            array_push($this->finalArr['brands'], $itemBrand);

                            array_push($this->finalArr['searchedNumber'], [
                                'guid'             => '',
                                'brand'            => $itemBrand,
                                'article'          => $itemArticle,
                                'name'             => $name,
                                'item_id'          => $item['productId'] ?? '',
                                'price'            => $price,
                                'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                                'qty'              => $qtyNow,
                                'multiplicity'     => $offer['minPackSize'] ?? '',
                                'type'             => '',
                                'delivery'         => '',
                                'extra'            => '',
                                'description'      => 'tiss',
                                'deliveryStart'    => date('d.m.Y'),
                                'deliveryEnd'      => date('d.m.Y'),
                                'supplier_name'    => 'tiss',
                                'supplier_city'    => $supplierCity,
                                'supplier_color'   => '#7a3ea1',
                            ]);
                        }
                        continue;
                    }

                    // Кросс/аналог — в наличии сейчас
                    if ($qtyNow > 0) {
                        array_push($this->finalArr['brands'], $itemBrand);

                        array_push($this->finalArr['crosses_on_stock'], [
                            'id'             => $item['productId'] ?? '',
                            'brand'          => $itemBrand,
                            'article'        => $itemArticle,
                            'name'           => $name,
                            'qty'            => $qtyNow,
                            'price'          => round($price),
                            'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                            'supplier_name'  => 'tiss',
                            'extra'          => ['photo' => ''],
                            'delivery_date'  => '',
                            'delivery_time'  => $deliveryText,
                            'supplier_city'  => $supplierCity,
                            'supplier_color' => '#9c63c2',
                        ]);
                        continue;
                    }

                    // Кросс/аналог — нет сейчас, но есть ожидаемая поставка ("под заказ")
                    if ($qtyExpected > 0) {
                        array_push($this->finalArr['brands'], $itemBrand);

                        array_push($this->finalArr['crosses_to_order'], [
                            'id'             => $item['productId'] ?? '',
                            'brand'          => $itemBrand,
                            'article'        => $itemArticle,
                            'name'           => $name,
                            'qty'            => $qtyExpected,
                            'price'          => round($price),
                            'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                            'supplier_name'  => 'tiss',
                            'extra'          => ['photo' => ''],
                            'delivery_date'  => $offer['expectedArrivalDate'] ?? '',
                            'delivery_time'  => $deliveryText,
                            'supplier_city'  => $supplierCity,
                            'supplier_color' => '#9c63c2',
                        ]);
                    }
                }
            }
        }

        return;
    }

    public function searchAutopiter(String $brand, String $partnumber)
    {
        //$start = microtime(true);
        $connect = array(
            'options' => array(
                'connection_timeout' => 1,
                'trace' => true
            )
        );
        $brand = strtolower($brand);

        $client = new SoapClient("http://service.autopiter.ru/v2/price?WSDL", $connect['options']);
        
        try {
            if (!($client->IsAuthorization()->IsAuthorizationResult)) {
                $client->Authorization(array("UserID"=>"1440698", "Password"=>"B_RH019rAk", "Save"=> "true"));
            }
        } catch (\Throwable $th) {
            return view('components.hostError');
        }
        
        $noAnalogsResult = $client->FindCatalog (array("Number"=>$partnumber));
        
        if(!$noAnalogsResult || empty($noAnalogsResult)) {
            return;
        }
        
        if($brand == 'hyundai-kia' || $brand == 'hyundai/kia') {
            $brand = 'Hyundai-Kia';
        } else if($brand == 'kyb') {
            $brand = 'kayaba';
        } else if ($brand == 'toyota/lexus') {
            $brand = 'toyota';
        } else if ($brand == 'citroen/peugeot' || $brand == 'citroen-peugeot') {
            $brand = 'peugeot';
        } else if ($brand == 'gm') {
            $brand = 'General Motors';
        } else if ($brand == 'nissan/infiniti') {
            $brand = 'nissan';
        }

        //получаем внутренний артикул детали
        $articleId = '';
        
        if (is_countable($noAnalogsResult->FindCatalogResult->SearchCatalogModel)) {
            foreach ($noAnalogsResult->FindCatalogResult->SearchCatalogModel as $key => $item) {
                if(trim(strtolower($item->CatalogName)) == trim(strtolower($brand)) ||
                str_contains(trim(strtolower($item->CatalogName)), trim(strtolower($brand)))
                ) {
                    $articleId = $item->ArticleId;
                }
            }
        } else {
            if(
                trim(strtolower($noAnalogsResult->FindCatalogResult->SearchCatalogModel->CatalogName)) == trim(strtolower($brand)) ||
                str_contains(trim(strtolower($noAnalogsResult->FindCatalogResult->SearchCatalogModel->CatalogName)), trim(strtolower($brand)))
            ) {
                $articleId = $noAnalogsResult->FindCatalogResult->SearchCatalogModel->ArticleId;
            }
        } 
        
        //получаем цены оригинального артикула
        try {
            $result = $client->GetPriceId(array("ArticleId"=> $articleId, "Currency" => 'РУБ', "SearchCross"=> 0, "DetailUid"=>null));
        } catch (\Throwable $th) {
            return 'error';
        }

        $result2 = (json_decode(json_encode($result), true));
		
		if (!empty($result2)) {
            if (is_array(array_shift($result2['GetPriceIdResult']['PriceSearchModel']))) {
                foreach ($result2['GetPriceIdResult']['PriceSearchModel'] as $item) {
                    array_push($this->finalArr['searchedNumber'], [
                        'brand' => $item['CatalogName'],
                        'article' => $item['Number'],
                        'name' => $item['Name'],
                        'price' => round($item['SalePrice']),
                        'priceWithMargine' => round($this->setPrice($item['SalePrice']), self::ROUND_LIMIT),
                        'qty' => $item['NumberOfAvailable'],
                        'deliveryStart' => $item['DeliveryDate'],
                        'deliveryEnd' => '',
                        'supplier_name' => 'atptr',
                        "supplier_city" => $item['Region'],
                        'supplier_color' => '#f2123b'
                    ]);
                }
            } else {
                array_push($this->finalArr['searchedNumber'], [
                    'brand' => $result2['GetPriceIdResult']['PriceSearchModel']['CatalogName'],
                    'article' => $result2['GetPriceIdResult']['PriceSearchModel']['Number'],
                    'name' => $result2['GetPriceIdResult']['PriceSearchModel']['Name'],
                    'price' => round($result2['GetPriceIdResult']['PriceSearchModel']['SalePrice']),
                    'priceWithMargine' => round($this->setPrice($result2['GetPriceIdResult']['PriceSearchModel']['SalePrice']), self::ROUND_LIMIT),
                    'qty' => $result2['GetPriceIdResult']['PriceSearchModel']['NumberOfAvailable'],
                    'supplier_color' => '#f2123b',
                    'deliveryStart' => $result2['GetPriceIdResult']['PriceSearchModel']['DeliveryDate'],
                    'deliveryEnd' => '',
                    'supplier_name' => 'atptr',
                    'supplier_city' => $result2['GetPriceIdResult']['PriceSearchModel']['Region']
                ]);
            } 
        }
        
        //получаем цены аналогов
        try {
            $resultWithAnalogs = $client->GetPriceId(array("ArticleId"=> $articleId, "Currency" => 'РУБ', "SearchCross"=> 2, "DetailUid"=>null));
        } catch (\Throwable $th) {
            return 'error';
        }  

        if (empty($resultWithAnalogs)) {
            return 'error';
        } 
        $result3 = (json_decode(json_encode($resultWithAnalogs), true));
    
        if (!$result3 || empty($result3)) {
            return;
        }
        
        if (is_array(array_shift($result3['GetPriceIdResult']['PriceSearchModel']))) {
            foreach ($result3['GetPriceIdResult']['PriceSearchModel'] as $item) {
                if(
                    !str_contains(trim(strtolower($partnumber)), $this->removeAllUnnecessaries(trim(strtolower($item['Number']))))
                ) {
                    array_push($this->finalArr['brands'], $item['CatalogName']);
                    
                    array_push($this->finalArr['crosses_to_order'], [
                        'brand' => $item['CatalogName'],
                        'article' => $item['Number'],
                        'name' => $item['Name'],
                        'price' => $item['SalePrice'],
                        'priceWithMargine' => round($this->setPrice($item['SalePrice']), self::ROUND_LIMIT),
                        "qty" =>$item['NumberOfAvailable'],
                        'stocks' => [
                            [
                                "stock_id" => $item['SellerId'],
                                "stock_name" => $item['Region'],
                                "stock_legend" => "",
                                "qty" =>$item['NumberOfAvailable'],
                                "price" => $item['SalePrice'],
                                'priceWithMargine' => round($this->setPrice($item['SalePrice']), self::ROUND_LIMIT),
                                "delivery_time" => $item['DeliveryDate'],
                                "SuccessfulOrdersProcent" => $item['SuccessfulOrdersProcent'],
                                "supplier_city" => $item['Region']
                            ]
                        ],
                        "delivery_time" => $item['DeliveryDate'],
                        "supplier_name" => 'atptr',
                        "supplier_city" => $item['Region'],
                        'supplier_color' => '#f2123b'
                    ]);
                }
            }
        } else {
            if(
                !str_contains(trim(strtolower($partnumber)), $this->removeAllUnnecessaries(trim(strtolower($result3['GetPriceIdResult']['PriceSearchModel']['Number']))))
            ) {
                array_push($this->finalArr['brands'], $result3['GetPriceIdResult']['PriceSearchModel']['CatalogName']);
                
                array_push($this->finalArr['crosses_to_order'], [
                    'brand' => $result3['GetPriceIdResult']['PriceSearchModel']['CatalogName'],
                    'article' => $result3['GetPriceIdResult']['PriceSearchModel']['Number'],
                    'name' => $result3['GetPriceIdResult']['PriceSearchModel']['Name'],
                    'price' => $result3['GetPriceIdResult']['PriceSearchModel']['SalePrice'],
                    'priceWithMargine' => round($this->setPrice($result3['GetPriceIdResult']['PriceSearchModel']['SalePrice']), self::ROUND_LIMIT),
                    "qty" =>$result3['GetPriceIdResult']['PriceSearchModel']['NumberOfAvailable'],
                    'stocks' => [
                        [
                            "stock_id" => $result3['GetPriceIdResult']['PriceSearchModel']['SellerId'],
                            "stock_name" => 'atptr',
                            "stock_legend" => "",
                            "qty" =>$result3['GetPriceIdResult']['PriceSearchModel']['NumberOfAvailable'],
                            "price" => $result3['GetPriceIdResult']['PriceSearchModel']['SalePrice'],
                            'priceWithMargine' => round($this->setPrice($result3['GetPriceIdResult']['PriceSearchModel']['SalePrice']), self::ROUND_LIMIT),
                            "delivery_time" => $result3['GetPriceIdResult']['PriceSearchModel']['DeliveryDate'],
                            "SuccessfulOrdersProcent" => $result3['GetPriceIdResult']['PriceSearchModel']['SuccessfulOrdersProcent'],
                            "supplier_city" => $result3['GetPriceIdResult']['PriceSearchModel']['Region']
                        ]
                    ],
                    "delivery_time" => $result3['GetPriceIdResult']['PriceSearchModel']['DeliveryDate'],
                    "supplier_name" => 'atptr',
                    'supplier_color' => '#f2123b',
                    'supplier_city' => $result3['GetPriceIdResult']['PriceSearchModel']['Region']
                ]);
            }
        }       
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. atptr';
        return;
    }

    public function searchXuiPoimi(String $brand, String $partnumber)
    {
        $searchedPart = XuiPoimiPrice::where('oem', $partnumber)
            ->get()
            ->toArray();
        
        if (empty($searchedPart)) {
            return;
        }
        
        foreach ($searchedPart as $item) {
            array_push($this->finalArr['brands'], $item['brand']);

            array_push($this->finalArr['crosses_on_stock'], [
                'brand' => $item['brand'],
                'article' => $item['oem'],
                'name' => $item['article'] . ' ' . $item['name'],
                'stock_legend' => '',
                'qty' => $item['qty'],
                'price' => $item['price'],
                'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                'delivery_time' => '1 день',
                'stocks' => [
                    [
                        'qty' => $item['qty'],
                        'price' =>$item['price'],
                        'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                    ]
                ],
                'supplier_name' => 'Хуйпойми',
                'supplier_city' => 'Астана',
                'supplier_color' => 'yellow',
            ]);  
        }
        
        return;
    }

    public function searchZakazauto_kst(String $brand, String $partnumber)
    {
        // Очищаем номер от лишних символов для поиска по clean_article
        $cleanPartnumber = preg_replace('/[^A-Za-z0-9]/', '', $partnumber);

        // Последовательный поиск: oem -> article -> clean_article
        $searchedPart = ZakazautoPrice::where('oem', $partnumber)
            ->orWhere('article', $partnumber)
            ->orWhere('clean_article', $cleanPartnumber)
            ->get();

        if ($searchedPart->isEmpty()) {
            return;
        }

        foreach ($searchedPart as $item) {
            // Добавляем бренд в общий список брендов
            if (!in_array($item->brand, $this->finalArr['brands'])) {
                array_push($this->finalArr['brands'], $item->brand);
            }

            array_push($this->finalArr['crosses_to_order'], [
                'brand' => $item->brand,
                'article' => $item->oem ?? $item->article, // Берем OEM, если пусто - артикул
                'name' => $item->name,
                'stock_legend' => '',
                'qty' => $item->qty,
                'price' => $item->price,
                'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                'delivery_time' => Carbon::now()->addDays(4),
                'stocks' => [
                    [
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                    ]
                ],
                'supplier_name' => 'zkzt_kst',
                'supplier_city' => 'Костанай',
                'supplier_color' => '#00cceb', // Желтый цвет (bootstrap warning)
            ]);
        }

        return;
    }

    public function searchStockInOffice(String $brand, String $partnumber)
    {
        //поиск по ОЕМ номеру
        $all = OfficePrice::all()->toArray();
        
        $searchedNumberId = '';

        foreach ($all as $item) {
            $oemsArr = explode('|', $item['oem']);
            foreach ($oemsArr as $uniqueOem) {
                if (strToLower($uniqueOem) == $partnumber) {
                    $searchedNumberId = $item['id'];
                    break;
                }
            }
        }

        if ($searchedNumberId) {
            $searchedPart = OfficePrice::find($searchedNumberId);
            
            if ($searchedPart->article == $partnumber) {
                array_push($this->finalArr['brands'], $searchedPart->brand);
                
                array_push($this->finalArr['searchedNumber'], [
                    'brand' => $searchedPart->brand,
                    'article' => $searchedPart->article,
                    'name' => $searchedPart->name,
                    'price' => $searchedPart->price,
                    'priceWithMargine' => round($this->setPrice($searchedPart->price), self::ROUND_LIMIT),
                    'qty' => $searchedPart->qty,
                    'supplier_city' => 'Астана',
                    'supplier_name' => 'в офисе',
                    'supplier_color' => 'lightgreen',
                    'deliveryStart' => 'в офисе'
                ]);
            } else {
                array_push($this->finalArr['brands'], $searchedPart->brand);

                array_push($this->finalArr['crosses_in_office'], [
                    'brand' => $searchedPart->brand,
                    'article' => $searchedPart->article,
                    'name' => $searchedPart->name,
                    'stock_legend' => 'в офисе',
                    'qty' => $searchedPart->qty,
                    'price' => $searchedPart->price,
                    'priceWithMargine' => round($this->setPrice($searchedPart->price), self::ROUND_LIMIT),
                    'delivery_time' => 'в офисе',
                    'supplier_name' => 'в офисе',
                    'supplier_city' => 'Астана',
                    'supplier_color' => 'lightgreen',
                ]);
            }

            return;
        }

        //поиск по артикулу аналога
        $searchedArticle = OfficePrice::where('article', strToLower($partnumber))
            ->orWhere('article', strToUpper($partnumber))
            ->get();


        foreach ($searchedArticle as $item) {
            array_push($this->finalArr['brands'], $item->brand);

            array_push($this->finalArr['searchedNumber'], [
                'brand' => $item->brand,
                'article' => $item->article,
                'name' => $item->name,
                'price' => $item->price,
                'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                'qty' => $item->qty,
                'supplier_city' => 'Астана',
                'supplier_name' => 'в офисе',
                'supplier_color' => 'lightgreen',
                'deliveryStart' => 'в офисе'
            ]);
        }
        
        return;
    }

    public function searchIngvar(String $brand, String $partnumber)
    {
        $searchedPart = IngvarPrice::where('oem', '=', $partnumber)
            ->orWhere('article', '=', $partnumber)
            ->get()
            ->toArray();
    
            
        if (empty($searchedPart)) {
            return;
        }
        
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
                'supplier_name' => 'Ingvar',
                'supplier_color' => '#77942e',
                'deliveryStart' => '1 день',
            ]);    
        }

        return;
    }

    public function searchVoltage(String $brand, String $partnumber)
    {
        $searchedPart = VoltagePrice::where('oem', '=', $partnumber)
            ->orWhere('article', '=', $partnumber)
            ->get()
            ->toArray();
        
        if (empty($searchedPart)) {
            return;
        }
        
        foreach ($searchedPart as $item) {
            
            if (strtolower($partnumber) == strtolower($item['article'])) {
                array_push($this->finalArr['brands'], $item['brand']);

                array_push($this->finalArr['searchedNumber'], [
                    'brand' => $item['brand'],
                    'article' => $item['article'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                    'qty' => $item['qty'],
                    'supplier_city' => 'Астана',
                    'supplier_name' => 'vltg',
                    'supplier_color' => '#77942e',
                    'deliveryStart' => \Carbon::today()->toDateString(),
                ]);
            } else {
                array_push($this->finalArr['crosses_on_stock'], [
                    'brand' => $item['brand'],
                    'article' => $item['article'],
                    'name' => $item['name'],
                    'stock_legend' => '',
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                    'delivery_time' => '1.5-2 часа',
                    'stocks' => [
                        [
                            'qty' => $item['qty'],
                            'price' =>$item['price'],
                            'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                        ]
                    ],
                    'supplier_name' => 'vltg',
                    'supplier_city' => 'Астана',
                    'supplier_color' => 'yellow',
                ]);
            }
        }

        return;
    }

    public function searchBlueStar(String $brand, String $partnumber)
    {
        $searchedPart = BlueStarPrice::where('oem', $partnumber)
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
                'supplier_name' => 'blstr',
                'supplier_color' => 'green',
                'deliveryStart' => date('d.m.Y'),
            ]);    
        }
        
        return;
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

    public function searchAdilPhaeton(String $brand, String $partnumber)
    {
        $searchedPart = AdilPhaetonPrice::where('oem', $partnumber)
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
                'supplier_name' => 'adil',
                'supplier_color' => 'green',
                'deliveryStart' => date('d.m.Y'),
            ]);    
        }
        
        return;
    }

    public function getCheckoutDetails () 
    {
        $connect = array(
            'wsdl'    => 'http://api.rossko.ru/service/v2.1/GetDeliveryDetails',
            'options' => array(
                'connection_timeout' => 1,
                'trace' => true
            )
        );
        
        $param = array(
            'KEY1'       => 'you_key_1',
            'KEY2'       => 'you_key_2',
            'date'       => '2020-01-30',
            'address_id' => '112233'
        );
        
        $query  = new SoapClient($connect['wsdl'], $connect['options']);
        $result = $query->GetDeliveryDetails($param);
        dd($result);
    }

    public function getStoragesList()
    {
        $url = "https://api2.autotrade.su/?json";

        $data = array(
            "auth_key" => self::API_KEY_TREID,
            "method" => "getStoragesList",
            
        );
        $request = 'data=' . json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        $html = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($html, true);
        dd($result);
    }

    function removeAllUnnecessaries(String $text)
    {
        $arr = str_split($text);

        foreach($arr as $key => $sign) {
            if($sign == ' ' || $sign == '-' || $sign == '/') {
                unset($arr[$key]);
            }
        }

        return strtolower(implode('', $arr));
    }

    function setPrice($price)
    {
        $priceWithMargin = 0;

        if ($price > 0 && $price <= 900) {
            $priceWithMargin = $price * 3.385;
        } else if ($price > 900 && $price <= 3000) {
            $priceWithMargin = $price * 2.344;
        } else if ($price > 3000 && $price <= 6000) {
            $priceWithMargin = $price * 1.991;
        } else if ($price > 6000 && $price <= 10000) {
            $priceWithMargin = $price * 1.637;
        } else if ($price > 10000 && $price <= 15000) {
            $priceWithMargin = $price * 1.485;
        } else if ($price > 15000 && $price <= 20000) {
            $priceWithMargin = $price * 1.445;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.384;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.394;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.374;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.354;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.334;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.304;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.284;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.263;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.253;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.243;
        }

        if (Auth()->user() && Auth()->user()->user_role == 'common') {
            return $priceWithMargin;
        } else if (Auth()->user() && Auth()->user()->user_role == 'opt') {
            return $priceWithMargin - ($priceWithMargin * 0.07);
        } elseif (Auth()->user() && Auth()->user()->user_role == 'admin') {
            return SetPrice::setPriceForAdmin($price);
        } else {
            return $priceWithMargin;
        }
    }

    private function getShatemToken()
    {
        return cache()->remember('shatem_token', 3600, function () {
            $response = Http::asForm()->post('https://api.shate-m.kz/api/v1/auth/loginByapiKey', [
                'ApiKey' => '{3f3b6eeb-709c-4dcb-be59-147ce8f9cb87}',
            ]);
            return $response->json()['access_token'] ?? null;
        });
    }

    private function extractDaysFromText(string $text): int
    {
        preg_match('/(\d+)/', $text, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 999;
    }

    public function getBrandsByArticle(string $partnumber): array
    {
        try {
            $response = Http::timeout(15)->post('https://service.tradesoft.ru/3/info/get-brands-by-article', [
                'user'     => env('TRADESOFT_USER'),
                'password' => env('TRADESOFT_PASSWORD'),
                'service'  => 'info',
                'action'   => 'getBrandsByArticle',
                'param'    => [
                    'code' => $partnumber,
                    'lang' => 'ru',
                ],
            ]);

            \Log::info('Avtozakup getBrandsByArticle response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            if (!$response->ok()) {
                \Log::warning('Avtozakup getBrandsByArticle not ok', ['status' => $response->status()]);
                return [];
            }

            $data = $response->json();

            if (!empty($data['error']) || empty($data['result'][0])) {
                \Log::warning('Avtozakup getBrandsByArticle empty or error', [
                    'error'  => $data['error'] ?? null,
                    'result' => $data['result'] ?? null,
                ]);
                return [];
            }

            $brands = [];

            foreach ($data['result'][0] as $item) {
                if (empty($item['brand'])) {
                    continue;
                }

                $brands[] = [
                    'name'  => $item['name']  ?? '',
                    'brand' => $item['brand'] ?? '',
                ];
            }

            return $brands;

        } catch (\Exception $e) {
            \Log::error('Avtozakup getBrandsByArticle exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return [];
        }
    }

    private function xmlValueToString($value): string
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            $first = reset($value);
            return $first !== false ? $this->xmlValueToString($first) : '';
        }

        return (string) $value;
    }
} 