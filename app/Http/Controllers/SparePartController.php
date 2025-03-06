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
        
        //поиск брэндлиста по каталогам
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
            'text' => $partNumber,
            'delivery_id' => '000000001',
            'address_id'  => '229881'
        );
        
        $query  = new SoapClient($connect['wsdl'], $connect['options']);
        
        try {
            $result = $query->GetSearch($param);
            
        } catch (\Throwable $th) {
            return view('components.hostError');
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
            $ch1 = curl_init(); 
        
            $fields = array("JSONparameter" => "{'Article': '".$partNumber."'}");
            
            curl_setopt($ch1, CURLOPT_URL, "api.tmparts.ru/api/ArticleBrandList?".http_build_query($fields)); 
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1); 
            
            $headers = array(         
                'Authorization: Bearer '. self::TISS_API_KEY
            ); 
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
            
            try {
                $response = json_decode(curl_exec($ch1),true);
            } catch (\Throwable $th) {
                return view('components.hostError');
            }
            
            if (!array_key_exists('BrandList', $response)) {
                return view('components.nothingFound');
            }
            $catalog = [];
            
            foreach ($response['BrandList'] as $item) {
                array_push($catalog,[
                    'brand' => $item['BrandName'],
                    'partnumber' => $response['Article'],
                    'name' => '',
                    'guid' => '',
                    'rossko_need_to_search' => false
                ]);
            }
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

    public function getSearchedPartAndCrosses (Request $request)
    {
        
        $this->finalArr['originNumber'] = $request->partnumber;
        $partNumber = $this->removeAllUnnecessaries(trim($request->partnumber));
        
        if($request->rossko_need_to_search) {
            $this->searchRossko($request->brand,  $partNumber, $request->guid);
        }
        //$this->searchArmtek($request->brand, $partNumber);
        $this->searchAdilsGM($request->brand, $partNumber);
        $this->searchStockInOffice($request->brand, $partNumber);
        $this->searchGerat($request->brand, $partNumber);
        $this->searchShatem($request->brand, $partNumber);
        $this->searchPhaeton($request->brand, $partNumber);
        $this->searchTreid($request->brand, $partNumber);
        $this->searchTiss($request->brand, $partNumber);
        $this->searchKulan($request->brand, $partNumber);
        $this->searchFebest($request->brand, $partNumber);
        $this->searchXuiPoimi($request->brand, $partNumber);
        
        if (!$request->only_on_stock) {
            $this->searchAutopiter($request->brand, $request->partnumber);
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
        
        return view('partSearchRes', [
            'finalArr' => $this->finalArr,
            'searchedPartNumber' => $this->partNumber,
            'brands' => $arr
        ]);
    }

    public function searchPhaeton(String $brand, String $partnumber)
    {
        //поиск товара в наличии в астане
        $ch = curl_init();

        $params = [
            'Article' => $partnumber,
            'Brand' => $brand,
            'Sources[]' => '1',
            'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
            'ApiKey' => 'LnxrDfpQVZz1ncuoI14e',
			'includeAnalogs' => 'true'
        ];
        
        curl_setopt($ch, CURLOPT_URL, 'https://api.phaeton.kz/api/Search?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response = json_decode(curl_exec($ch));
        } catch (\Throwable $th) {
            return;
        }
        
		if (!$response || $response->IsError) {
            return;
        }
		
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
                        'stocks' => $item->AvailableCount,
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
                        'supplier_color' => '#34689e'
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
        
        curl_setopt($ch1, CURLOPT_URL, 'https://api.phaeton.kz/api/Search?' . http_build_query($params1));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response1 = json_decode(curl_exec($ch1));
        } catch (\Throwable $th) {
            return;
        }
        
		if (!$response1 || $response1->IsError) {
            return;
        }

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

    public function searchTreid (String $brand, String $partnumber) 
    {
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
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $html = curl_exec($ch);
        curl_close($ch);
        
        try {
            $result = json_decode($html, true);
        } catch (\Throwable $th) {
            return;
        }
        //dd($result);
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
                                    'stocks' => $searched_number_stocks,
                                    'multiplicity' => '',
                                    'type' => '',
                                    'delivery' => '',
                                    'extra' => '',
                                    'description' => 'trd',
                                    'deliveryStart' => date('d.m.Y'),
                                    'deliveryEnd' => date('d.m.Y'),
                                    'supplier_name' => 'trd',
                                    'supplier_city' => 'ast',
                                    'supplier_color' => '#34689e'
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $html = curl_exec($ch);
        curl_close($ch);

        try {
            $result = json_decode($html, true);
            
        } catch (\Throwable $th) {
            return;
        }
        //dd($result);
        if (empty($result) ) {
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8'));
        $html = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($html, true);
        
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
                            'stocks' => $crosses_stocks,
                            'price' => round($item['price']),
                            'priceWithMargine' => round($this->setPrice($item['price']), self::ROUND_LIMIT),
                            'supplier_name' => 'trd',
                            'delivery_date' => '',
                            'delivery_time' => '1.5-2 часа',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#34689e'
                        ]); 
                    }
                } 
            }
        }
        if (set_time_limit(5)) {
            return;
        }
    }

    public function searchRossko(String $brand, String $partNumber, String $guid)
    {   
        
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
                            'stocks' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['count'],
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
                            'stocks' => $stockItem['count'],
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
        
        return;
    }

    public function searchArmtek(String $brand, String $partnumber)
    {
        require_once '../config.php';
        require_once '../autoloader.php';

        try {
            // init configuration 
            $armtek_client_config = new ArmtekRestClientConfig($user_settings);  

            // init client
            $armtek_client = new ArmtekRestClient($armtek_client_config);


            $params = [
                'VKORG'         => '8000'       
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
            
            if(property_exists($json_responce_data, 'MESSAGES')) {
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
                if ($crossItem->KEYZAK == 'MOV0005505' || $crossItem->KEYZAK == 'MOV0009026') {
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

        return;
    }

    public function searchShatem(String $brand, String $partnumber)
    {
        $start = microtime(true);
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
            'ApiKey' => '{a9000264-381b-4c69-9af4-51fdd93b8eda}',
        ];
        $ch = curl_init('https://api.shate-m.kz/api/v1/auth/loginbyapikey/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
        //dd($params);
        $ch1 = curl_init();
        $resUrl = 'https://api.shate-m.kz/api/v1/articles/search?' . http_build_query($params);
        curl_setopt($ch1, CURLOPT_URL, $resUrl);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true); 
        $headers = [
            'Authorization:Bearer ' . $access_token,
        ];
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
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
            'Authorization:Bearer ' . $access_token,
            'Content-Type: application/json',
        ];
        $request_params2 = [
            array(
                'articleId' => $articleId,
                'includeAnalogs' => true
            )
        ];
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, "https://api.shate-m.kz/api/v1/prices/search/with_article_info/");
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($request_params2));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers1);
        curl_setopt($ch2, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $response = json_decode(curl_exec($ch2));
        
        try {
            $response = json_decode(curl_exec($ch2));
        } catch (\Throwable $th) {
            return;
        }
        if (empty($response) || isset($response->messages)) {
            return;
        }
        
        curl_close($ch2);
        
        foreach ($response as $key => $item) {
            if ($item->article->code == $partnumber) {
                foreach ($item->prices as $price) {
                    if (
                        $price->addInfo->city == 'Шымкент' || $price->addInfo->city == 'Екатеринбург' || 
                        $price->addInfo->city == 'Подольск' || $price->addInfo->city == 'Костанай' || $price->addInfo->city == 'Караганда'
                    ) {
                        array_push($this->finalArr['brands'], $item->article->tradeMarkName);
                        
                        array_push($this->finalArr['searchedNumber'], [
                            'brand' => $item->article->tradeMarkName,
                            'article' => $item->article->code,
                            'name' => $item->article->name,
                            'price' => $price->price->value,
                            'priceWithMargine' => round($this->setPrice($price->price->value), self::ROUND_LIMIT),
                            'stocks' => $price->quantity->available,
                            'supplier_city' => 'ast',
                            'supplier_name' => 'shtm',
                            'supplier_color' => '#6b6b6b',
                            'deliveryStart' => date('d.m.Y', strtotime(stristr($price->shippingDateTime, 'T', true))),
                        ]);
                    }
                }
            } else {
                foreach ($item->prices as $key => $price) {
                    if($price->addInfo->city == 'Астана') {
                        array_push($this->finalArr['brands'], $item->article->tradeMarkName);
                        
                        array_push($this->finalArr['crosses_on_stock'], [
                            'brand' => $item->article->tradeMarkName,
                            'article' => $item->article->code,
                            'name' => $item->article->name,
                            'stock_legend' => $price->addInfo->city,
                            'qty' => $price->quantity->available,
                            'price' => $price->price->value,
                            'priceWithMargine' => round($this->setPrice($price->price->value), self::ROUND_LIMIT),
                            'delivery_time' => '1.5-2 часа',
                            'stocks' => [
                                [
                                    'qty' => $price->quantity->available,
                                    'price' => $price->price->value,
                                    'priceWithMargine' => round($this->setPrice($price->price->value), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'shtm',
                            'supplier_city' => 'ast',
                            'supplier_color' => '#6b6b6b',
                        ]);
                    } else if (
                        $price->addInfo->city == 'Екатеринбург' || $price->addInfo->city == 'Караганда' ||
                        $price->addInfo->city == 'Подольск' || $price->addInfo->city == 'Костанай' || $price->addInfo->city == 'Шымкент'
                    ) {
                        array_push($this->finalArr['brands'], $item->article->tradeMarkName);
                        
                        array_push($this->finalArr['crosses_to_order'], [
                            'brand' => $item->article->tradeMarkName,
                            'article' => $item->article->code,
                            'name' => $item->article->name,
                            'qty' => $price->quantity->available,
                            'price' => $price->price->value,
                            'priceWithMargine' => round($this->setPrice($price->price->value), self::ROUND_LIMIT),
                            'delivery_time' => date('d.m.Y', strtotime(stristr($price->shippingDateTime, 'T', true))),
                            'stocks' => [
                                [
                                    'qty' => $price->quantity->available,
                                    'price' => $price->price->value,
                                    'priceWithMargine' => round($this->setPrice($price->price->value), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'shtm',
                            'supplier_city' => $price->addInfo->city,
                            'supplier_color' => '#6b6b6b',
                        ]);
                    }
                }
            }
        }
        return;
    }

    public function searchTiss(String $brand, String $partnumber)
    {
        
        $ch1 = curl_init(); 
        
        $fields = array("JSONparameter" => "{'Brand': '".$brand."', 'Article': '".$partnumber."', 'is_main_warehouse': ".'1'." }" );
        
        $headers = array(         
            'Authorization: Bearer '. self::TISS_API_KEY
        );
        curl_setopt($ch1, CURLOPT_URL, "api.tiss.parts/api/StockByArticle?". http_build_query($fields));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $result = json_decode(curl_exec($ch1)); 
        } catch (\Throwable $th) {
            return;
        }
        
        foreach ($result as $key => $item) {
            if (strtolower($item->article) == strtolower($this->finalArr['originNumber']) ) {
                array_push($this->finalArr['searchedNumber'], [
                    'brand' => $item->brand,
                    'article' => $item->article,
                    'name' => $item->article_name,
                    'price' => $item->min_price,
                    'priceWithMargine' => round($this->setPrice($item->min_price), self::ROUND_LIMIT),
                    'stocks' => $item->warehouse_offers[0]->quantity,
                    'supplier_name' => 'tss',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#7bafcf',
                    'deliveryStart' => date('d.m.Y'),
                ]);
            } else {
                $stocks = [];
                foreach ($item->warehouse_offers as $key => $offer) {
                    array_push($stocks, [
                        'qty' => $offer->quantity,
                        'price' => $offer->price,
                        'priceWithMargine' => round($this->setPrice($offer->price), self::ROUND_LIMIT)
                    ]);
                }
                array_push($this->finalArr['crosses_on_stock'], [
                    'brand' => $item->brand,
                    'article' => $item->article,
                    'name' => $item->article_name,
                    'price' => $item->min_price,
                    'priceWithMargine' => round($this->setPrice($item->min_price), self::ROUND_LIMIT),
                    'stocks' => $stocks,
                    'supplier_name' => 'tss',
                    'stock_legend' => $item->warehouse_offers[0]->warehouse_name,
                    'delivery_time' => '1.5-2 часа',
                    'supplier_city' => 'ast',
                    'supplier_color' => '#7bafcf',
                ]);
            }
        }
        
    }

    public function searchKulan(String $brand, String $partnumber)
    {
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
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response = json_decode(curl_exec($ch));
        } catch (\Throwable $th) {
            return;
        }
        
        if (!$response || property_exists($response, 'messages')) {
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
                        'stocks' => $store->quantity,
                        'supplier_city' => 'ast',
                        'supplier_name' => 'kln',
                        'supplier_color' => 'green',
                        'deliveryStart' => date('d-m-Y'),
                    ]);
                }
            }
        }

        //получение остатков аналогов
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, 'https://connect.adkulan.kz/api/request/api/v2/catalog/article/analogues?' . http_build_query($params) . '&order_by=price_asc');
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch1, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        
        try {
            $response1 = json_decode(curl_exec($ch1));
            curl_close($ch1);
        } catch (\Throwable $th) {
            return;
        }
        
        if (gettype($response1) == 'object' && property_exists($response1, 'messages')) {
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
                        'supplier_color' => 'green',
                    ]);
                }
            }
        }
        
        return;
    }

    public function searchFebest(String $brand, String $partnumber)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://febest.kz/api/v1/search/{pHgK46xXxD3pxbeyTtWJ}/' . $partnumber);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
       
        foreach ($result as $item) {
            array_push($this->finalArr['crosses_on_stock'], [
                'brand' => $item->manufacturer,
                'article' => $item->code,
                'name' => $item->name,
                'price' => $item->price,
                'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                'stocks' => $item->amount,
                'supplier_name' => 'fbst',
                'stock_legend' => 'Астана',
                'delivery_time' => '2-2.5 часа',
                'supplier_city' => 'ast',
                'supplier_color' => '#a27745',
            ]);
        }
    }

    public function searchGerat(String $brand, String $partnumber)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://gerat.kz/bitrix/catalog_export/dealer_opt.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $result = curl_exec($ch);
        $xml_snippet = simplexml_load_string( $result );
        $json_convert = json_encode( $xml_snippet );
        $json = json_decode( $json_convert );
        
        foreach ($json->shop->offers->offer as $item) {
            $cross_numbers = explode(', ', $item->description);
            
            foreach ($cross_numbers as $cross_number) {
                if (strtolower($cross_number) == strtolower($partnumber) || strtolower($partnumber) == strtolower($this->removeAllUnnecessaries($item->vendorCode))) {
                    if (strtolower($partnumber) == strtolower($this->removeAllUnnecessaries($item->vendorCode))) {
                        array_push($this->finalArr['brands'], $item->vendor);

                        array_push($this->finalArr['searchedNumber'], [
                            'brand' => $item->vendor,
                            'article' => $item->vendorCode,
                            'name' => substr($item->model, 0, 60),
                            'price' => $item->price,
                            'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                            'stocks' => $item->count,
                            'supplier_name' => 'grt',
                            'supplier_city' => 'Алмата',
                            'supplier_color' => '#7bafcf',
                            'deliveryStart' => date('d.m.Y', strtotime('+4day')),
                        ]);
                    } else {
                        array_push($this->finalArr['brands'], $item->vendor);

                        array_push($this->finalArr['crosses_to_order'], [
                            'brand' => $item->vendor,
                            'article' => $item->vendorCode,
                            'name' => substr($item->model, 0, 60),
                            'qty' => $item->count,
                            'price' => $item->price,
                            'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                            'delivery_time' => date('d.m.Y', strtotime('+4day')),
                            'stocks' => [
                                [
                                    'qty' => $item->count,
                                    'price' => $item->price,
                                    'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'grt',
                            'supplier_city' => 'Алмата',
                            'supplier_color' => '#feed00'
                        ]); 
                    }
                }
            }
        }
        
        return;
    }

    public function searchAutopiter(String $brand, String $partnumber)
    {
        $client = new SoapClient("http://service.autopiter.ru/v2/price?WSDL");
        $brand = strtolower($brand);
        if (!($client->IsAuthorization()->IsAuthorizationResult)) {
            $client->Authorization(array("UserID"=>"1440698", "Password"=>"B_RH019rAk", "Save"=> "true"));
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
                        'stocks' => $item['NumberOfAvailable'],
                        'deliveryStart' => $item['DeliveryDate'],
                        'deliveryEnd' => '',
                        'supplier_name' => 'atptr',
                        "supplier_city" => $item['Region'],
                        'supplier_color' => '#111'
                    ]);
                }
            } else {
                array_push($this->finalArr['searchedNumber'], [
                    'brand' => $result2['GetPriceIdResult']['PriceSearchModel']['CatalogName'],
                    'article' => $result2['GetPriceIdResult']['PriceSearchModel']['Number'],
                    'name' => $result2['GetPriceIdResult']['PriceSearchModel']['Name'],
                    'price' => round($result2['GetPriceIdResult']['PriceSearchModel']['SalePrice']),
                    'priceWithMargine' => round($this->setPrice($result2['GetPriceIdResult']['PriceSearchModel']['SalePrice']), self::ROUND_LIMIT),
                    'stocks' => $result2['GetPriceIdResult']['PriceSearchModel']['NumberOfAvailable'],
                    'supplier_color' => '#111',
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
                        'supplier_color' => '#111'
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
                            "city" => $result3['GetPriceIdResult']['PriceSearchModel']['Region']
                        ]
                    ],
                    "delivery_time" => $result3['GetPriceIdResult']['PriceSearchModel']['DeliveryDate'],
                    "supplier_name" => 'atptr',
                    'supplier_color' => '#111'
                ]);
            }
        }       

        return;
    }

    public function searchAdilsGM(String $brand, String $partnumber)
    {
        $searchedPart = gm_pricelist_from_adil::where('oem', $partnumber)
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
                'stocks' => $item['qty'],
                'supplier_city' => 'Алматы',
                'supplier_name' => 'Adil`s GM',
                'supplier_color' => 'green',
                'deliveryStart' => date('d.m.Y', strtotime('+10 day', strtotime(date('d.m.y')))),
            ]);    
        }
        
        return;
    }

    public function searchXuiPoimi(String $brand, String $partnumber)
    {
        //dd($partnumber);
        $searchedPart = XuiPoimiPrice::where('oem', $partnumber)
            ->get()
            ->toArray();
        
        if (empty($searchedPart)) {
            return;
        }
        //dd($searchedPart);
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

    public function searchStockInOffice(String $brand, String $partnumber)
    {
        //поиск по ОЕМ номеру
        $oemNumbers = OfficePrice::pluck('oem');

        $searchedNumberId = '';

        foreach ($oemNumbers as $id => $oems) {
            $oemsArr = explode('|', $oems);
            
            foreach ($oemsArr as $uniqueOem) {
                if (strToLower($uniqueOem) == $partnumber) {
                    $searchedNumberId = ++$id;
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
                    'stocks' => $searchedPart->qty,
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
                'stocks' => $item->qty,
                'supplier_city' => 'Астана',
                'supplier_name' => 'в офисе',
                'supplier_color' => 'lightgreen',
                'deliveryStart' => 'в офисе'
            ]);
        }
        
            
        

        return;
    }

    public function getCheckoutDetails () {
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
        
        if ($price > 0 && $price <= 600) {
            $priceWithMargin = $price * 3; 
        } else if ($price > 600 && $price <= 2000) {
            $priceWithMargin = $price * 2;
        } else if ($price > 2000 && $price <= 4000) {
            $priceWithMargin = $price * 1.63;
        } else if ($price > 4000 && $price <= 10000) {
            $priceWithMargin = $price * 1.40;
        } else if ($price > 10000 && $price <= 20000) {
            $priceWithMargin = $price * 1.34;
        } else if ($price > 20000 && $price <= 30000) {
            $priceWithMargin = $price * 1.33;
        } else if ($price > 30000 && $price <= 40000) {
            $priceWithMargin = $price * 1.29;
        } else if ($price > 40000 && $price <= 50000) {
            $priceWithMargin = $price * 1.27;
        } else if ($price > 50000 && $price <= 60000) {
            $priceWithMargin = $price * 1.26;
        } else if ($price > 60000 && $price <= 70000) {
            $priceWithMargin = $price * 1.25;
        } else if ($price > 70000 && $price <= 80000) {
            $priceWithMargin = $price * 1.24;
        } else if ($price > 80000 && $price <= 90000) {
            $priceWithMargin = $price * 1.23;
        } else if ($price > 90000 && $price <= 100000) {
            $priceWithMargin = $price * 1.22;
        } else if ($price > 100000 && $price <= 120000) {
            $priceWithMargin = $price * 1.21;
        } else if ($price > 120000) {
            $priceWithMargin = $price * 1.20;
        }
        
        if (Auth()->user() && Auth()->user()->user_role == 'common') {
            return $priceWithMargin;
        } else if(Auth()->user() && Auth()->user()->user_role == 'opt') {
            return $priceWithMargin - ($priceWithMargin * 0.08);
        } elseif(Auth()->user() && Auth()->user()->user_role == 'admin') {
            $priceWithMargin = SetPrice::setPriceForAdmin($price);
            return $priceWithMargin;
        } else {
            return $priceWithMargin;
        }
    }
}