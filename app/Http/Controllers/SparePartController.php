<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SoapClient;
use Illuminate\Support\Facades\View;
use ArmtekRestClient\Http\Exception\ArmtekException as ArmtekException; 
use ArmtekRestClient\Http\Config\Config as ArmtekRestClientConfig;
use ArmtekRestClient\Http\ArmtekRestClient as ArmtekRestClient; 

class SparePartController extends Controller
{
    const API_KEY1_ROSSKO = '4adcbb9794b8e537bd2aa6272b36bdb0';
    const API_KEY2_ROSSKO = '5fcc040a8188a51baf5a6f36ca15ce05';
    const API_KEY_TREID = '73daf78112373b8326bea5558b0b2ec0';
    const TREID_STORAGE_IDs = [
        168102, 247102, 262102,
    ];
    const ARMTEK_LOGIN = 'ROMAN_PLANETA@MAIL.RU';
    const ARMTEK_PASSWORD = 'rimma240609';
    const ARMTEK_SBIT_ORG = '8000';
    const ARMTEK_CUSTOMER = '43387356'; 
    const ARMTEK_STOCK_ASTANA = 'MOV0005505';
    const TISS_API_KEY = 'QXO7oqkH1_aifhVdi8W1GiMx4SEwzkPMTdwYjgcktOjW70aX_ve_xGDC7bTRBmQ37rH1k2ETsA3ZdCIfja0yHosRNNGwaYGGXuXFR6U4TADCRZF6lLvyjfKcg-zS5y4xQT4SNpi86vVPN5zOEFdhiZfRaKGh_U1MfHJz9IpAsyuc0ZHDHRaw0dO1tDHgQw2N4uPP0sq0kStch43q9zfZKhMsqTSNtgGVBnGRzaCkJzzuaXmfrL4Ot5ODBJ3x1tXnyVGW-p5IeZXOtIfeRWZMSnw3luiMztyY1m7p84r_qWJeVvr1J_3rR0R1EP7qAHjvX_QEnud83oqMCJppN4RCnD4sb5_fkylpyrEyuXRVvqviPx2-xiNhBwwLLkt67cNaZYBbtcaLcaZT5apXtVFW4B0IcwMHyqt_Oy3USMl3bkiBiJ7fGW6bOBidnoRCE6OqS1JTWKCkAZEoqY8rOX4A7p8YZTkamldmGbzf7sveBYhPSJvwmaUVWvzju6iEr7cB';
    const SHATEM_API_KEY = '{a9000264-381b-4c69-9af4-51fdd93b8eda}';

    public $partNumber = '';

    public $finalArr = [

        'searchedNumber' => [
            
        ],
        'crosses_on_stock' => [
            
        ],
        'crosses_to_order' => [

        ]
    ];
    

    public function catalogSearch(Request $request) 
    {
        $partNumber = trim($request->partNumber); 

        //поиск брэндлиста по каталогам росско
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
        //dd($result->SearchResult);
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
            
            return view('catalogSearchRes')->with(['finalArr' => $catalog]);
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
            return view('catalogSearchRes')->with(['finalArr' => $catalog]);
        }
    }

    public function getSearchedPartAndCrosses (Request $request)
    {
        /*if($request->rossko_need_to_search) {
            $this->searchRossko($request->brand, $request->partnumber, $request->guid);
        }
        $this->searchArmtek($request->brand, $request->partnumber);
        $this->searchTreid($request->brand, $request->partnumber);*/
        $this->searchTiss($request->brand, $request->partnumber);
        //$this->searchShatem($request->brand, $request->partnumber);
        
        return view('partSearchRes', [
            'finalArr' => $this->finalArr,
            'searchedPartNumber' => $this->partNumber
        ]);
    }

    public function searchTreid (String $brand, String $partnumber) 
    {
        $this->partNumber = $partnumber;
        //dd([$brand, $partnumber]);
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
        $html = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($html, true);
        //dd($result);
        //помещаем найденные позиции в итоговый массив
        if (strlen($result['message']) <= 2) {
            foreach ($result['items'] as $key => $item) {
                if (strlen($result['message']) <= 2) {
                    if ($item['price']) {
                        $searched_number_stocks = [];
                            foreach ($item['stocks'] as $key => $stock) {
                                if ($stock['quantity_unpacked'] > 0) {
                                    array_push($searched_number_stocks, $stock);
                                }
                            }
                            if(!empty($searched_number_stocks)) {
                                array_push($this->finalArr['searchedNumber'], [
                                    'guid' => '',
                                    'brand' => $item['brand'],
                                    'article' => $item['article'],
                                    'name' => substr($item['name'], 0, 60),
                                    'item_id' => $item['id'],
                                    'price' => $item['price'],
                                    'stocks' => $searched_number_stocks,
                                    'multiplicity' => '',
                                    'type' => '',
                                    'delivery' => '',
                                    'extra' => '',
                                    'description' => 'trd',
                                    'deliveryStart' => '',
                                    'deliveryEnd' => '',
                                    'supplier_name' => 'trd'
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
        $html = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($html, true);
        //dd($result);
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
            //dd($result);

            if (empty($result['items'])) {
                return;
            }
            //помещаем кроссы в наличии в итоговый массив
            foreach ($result['items'] as $item) {
                if (array_key_exists('price', $item)) {
                    $crosses_stocks = [];
                    foreach ($item['stocks'] as $key => $stock) {
                        if ($stock['quantity_unpacked'] > 0 ) {
                            if ($key == 168102 || $key == 247102 || $key == 262102) {
                                $crosses_stocks[] = [
                                    'stock_id' => $stock['id'],
                                    'stock_name' => substr($stock['name'], 0, 60),
                                    'stock_legend' => $stock['legend'],
                                    'qty' => $stock['quantity_unpacked'],
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'trd'
                                ];
                            }
                        }
                    }
                    if (!empty($crosses_stocks)) {
                        array_push($this->finalArr['crosses_on_stock'], [
                            'id' => $item['id'],
                            'brand' => $item['brand'],
                            'article' => $item['article'],
                            'name' => substr($item['name'], 0, 60),
                            'stocks' => $crosses_stocks,
                            'price' => round($item['price']),
                            'supplier_name' => 'trd',
                            'delivery_date' => '',
                            'delivery_time' => '1.5-2 часа'
                        ]);   
                    } 
                }
            }
        //dd($this->finalArr);
        return;
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
        //dd($result);

        if (!$result['SearchResult']['success']) {
            return;
        }

        //добавляем данные по искомому номеру в итоговый массив
        if ($result['SearchResult']['success'] == true) {
            if (isset($result['SearchResult']['PartsList']['Part']['stocks'])) {
                if (count($result['SearchResult']['PartsList']['Part']['stocks']['stock']) == 10) {
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($result['SearchResult']['PartsList']['Part']['stocks']['stock']['price']),
                            'stocks' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['count'],
                            'multiplicity' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['multiplicity'],
                            'type' => '',
                            'delivery' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['delivery'],
                            'extra' => '',
                            'description' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['description'],
                            'deliveryStart' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryStart'],
                            'deliveryEnd' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryEnd'],
                            'supplier_name' => 'rssk',
                        ]);
                } else {
                    foreach ($result['SearchResult']['PartsList']['Part']['stocks']['stock'] as $stockItem) {
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($stockItem['price']),
                            'stocks' => $stockItem['count'],
                            'multiplicity' => $stockItem['multiplicity'],
                            'type' => '',
                            'delivery' => $stockItem['delivery'],
                            'extra' => '',
                            'description' => $stockItem['description'],
                            'deliveryStart' => $stockItem['deliveryStart'],
                            'deliveryEnd' => $stockItem['deliveryEnd'],
                            'supplier_name' => 'rssk'
                        ]);
                    }
                }
            }
        }
        
        //добавляем данные по кроссам в итоговый массив
        if (array_key_exists('crosses',$result['SearchResult']['PartsList']['Part'])) {
            //dd($result['SearchResult']['PartsList']['Part']['crosses']);
            $firstKey = array_key_first($result['SearchResult']['PartsList']['Part']['crosses']['Part']);
            $firstElem = $result['SearchResult']['PartsList']['Part']['crosses']['Part'][$firstKey];
            
            //dd($firstElem);
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
                                    'delivery_time' => '1.5-2 часа',
                                ];
    
                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'delivery_time' => $innerArr['deliveryEnd']
                                ];
    
                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
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
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'delivery_time' => $item['deliveryEnd']
                                    ];
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        'supplier_name' => 'rssk',
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
                                    'delivery_time' => '1.5-2 часа'
                                ];
    
                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'delivery_time' => $innerArr['deliveryEnd']
                                ];
    
                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
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
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'delivery_time' => $item['deliveryEnd']
                                    ];
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        '   ' => 'rssk',
                                    ]);
                                }
                            }
                        }
                    }
                
            }
            
            
        }
        //dd($this->finalArr);
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
                'VKORG'         => self::ARMTEK_SBIT_ORG       
                ,'KUNNR_RG'     => self::ARMTEK_CUSTOMER
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
            //dd($json_responce_data);
            if(gettype($json_responce_data) == 'array' && property_exists($json_responce_data->RESP, 'MSG')){
                return;
            }
            foreach ($json_responce_data->RESP as $key => $crossItem) {
                if ($crossItem->KEYZAK == 'MOV0005505' || $crossItem->KEYZAK == 'MOV0009026') {
                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand' => $crossItem->BRAND,
                        'article' => $crossItem->PIN,
                        'name' => $crossItem->NAME,
                        'stock_legend' => 'armtek_ast',
                        'qty' => $crossItem->RVALUE,
                        'price' => round($crossItem->PRICE),
                        'delivery_time' => '1.5-2 часа',
                        'stocks' => [
                            [
                                'qty' => $crossItem->RVALUE,
                                'price' => $crossItem->PRICE
                            ]
                            ],
                        'supplier_name' => 'rmtk',
                    ]);
                } else {
                    break;
                }
            }
            //dd($this->finalArr);

        } catch (ArmtekException $e) {

            $json_responce_data = $e -> getMessage(); 

        }
        return;
    }

    public function searchShatem(String $brand, String $partnumber)
    {
        $request_params = [
            'ApiKey' => '{a9000264-381b-4c69-9af4-51fdd93b8eda}'
        ];
        $ch = curl_init('https://api.shate-m.kz/api/v1/auth/loginbyapikey/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        
        $access_token = json_decode($response)->access_token;
        //dd($access_token);
        $request_params1 = [
            'Authorization: Bearer' => $access_token,
            'articleId' => $partnumber
        ];
        //dd(http_build_query($request_params1));
        $ch1 = curl_init('https://api.shate-m.kz/api/v1/articles/search/Authorization: Bearer'.$access_token);
        curl_setopt($ch1, CURLOPT_POST, true);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($request_params1));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);

        
        curl_close($ch1);
        $response = curl_exec($ch1);
        dd($response);
    }

    public function searchTiss(String $brand, String $partnumber)
    {
        $ch1 = curl_init(); 
        
        $fields = array("JSONparameter" => "{'Brand': '".$brand."', 'Article': '".$partnumber."', 'is_main_warehouse': ".'1'." }" );
        
        curl_setopt($ch1, CURLOPT_URL, "api.tmparts.ru/api/StockByArticle?".http_build_query($fields));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1); 
       
        $headers = array(         
        'Authorization: Bearer '. self::TISS_API_KEY 
        ); 
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        
        $Art_List_With_Prices = json_decode(curl_exec($ch1),true);   

        dd($Art_List_With_Prices);
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
}






/**/
/*
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
        
        if (!$result->SearchResult->success) {
            return view('components.nothingFound');
        }
        $catalog = [];
        if (!is_countable($result->SearchResult->PartsList->Part)) {
            array_push($catalog,[
                'brand' => $result->SearchResult->PartsList->Part->brand,
                'partnumber' => $result->SearchResult->PartsList->Part->partnumber,
                'name' => $result->SearchResult->PartsList->Part->name,
                'guid' => $result->SearchResult->PartsList->Part->guid
            ]);
        } else {
            foreach ($result->SearchResult->PartsList->Part as $part) {
                array_push($catalog,[
                    'brand' => $part->brand,
                    'partnumber' => $part->partnumber,
                    'name' => $part->name,
                    'guid' => $part->guid
                ]);
            }
        }
*/

/*
public function searchRossko(String $brand, String $partNumber)
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
            'text' => $partNumber,
            'delivery_id' => '000000001',
            'address_id'  => '229881'
        );
        
        $query  = new SoapClient($connect['wsdl'], $connect['options']);
        $result = $query->GetSearch($param);
        
        $result = (json_decode(json_encode($result), true));
        //dd($result);
        //добавляем данные по искомому номеру в итоговый массив
        if ($result['SearchResult']['success'] == true) {
            if (isset($result['SearchResult']['PartsList']['Part']['stocks'])) {
                if (count($result['SearchResult']['PartsList']['Part']['stocks']['stock']) == 10) {
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($result['SearchResult']['PartsList']['Part']['stocks']['stock']['price']),
                            'stocks' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['count'],
                            'multiplicity' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['multiplicity'],
                            'type' => '',
                            'delivery' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['delivery'],
                            'extra' => '',
                            'description' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['description'],
                            'deliveryStart' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryStart'],
                            'deliveryEnd' => $result['SearchResult']['PartsList']['Part']['stocks']['stock']['deliveryEnd'],
                            'supplier_name' => 'rssk',
                        ]);
                } else {
                    foreach ($result['SearchResult']['PartsList']['Part']['stocks']['stock'] as $stockItem) {
                        array_push($this->finalArr['searchedNumber'], [
                            'guid' => $result['SearchResult']['PartsList']['Part']['guid'],
                            'brand' => $result['SearchResult']['PartsList']['Part']['brand'],
                            'article' => $result['SearchResult']['PartsList']['Part']['partnumber'],
                            'name' => $result['SearchResult']['PartsList']['Part']['name'],
                            'price' => round($stockItem['price']),
                            'stocks' => $stockItem['count'],
                            'multiplicity' => $stockItem['multiplicity'],
                            'type' => '',
                            'delivery' => $stockItem['delivery'],
                            'extra' => '',
                            'description' => $stockItem['description'],
                            'deliveryStart' => $stockItem['deliveryStart'],
                            'deliveryEnd' => $stockItem['deliveryEnd'],
                            'supplier_name' => 'rssk'
                        ]);
                    }
                }
            }
        }
        
        //добавляем данные по кроссам в итоговый массив
        if (array_key_exists('crosses',$result['SearchResult']['PartsList']['Part'])) {
            //dd($result['SearchResult']['PartsList']['Part']['crosses']);
            $firstKey = array_key_first($result['SearchResult']['PartsList']['Part']['crosses']['Part']);
            $firstElem = $result['SearchResult']['PartsList']['Part']['crosses']['Part'][$firstKey];
            
            //dd($firstElem);
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
                                    'delivery_time' => '1.5-2 часа',
                                ];
    
                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'delivery_time' => $innerArr['deliveryEnd']
                                ];
    
                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $part_stock['guid'],
                                    'brand' => $part_stock['brand'],
                                    'article' => $part_stock['partnumber'],
                                    'name' => $part_stock['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
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
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'delivery_time' => $item['deliveryEnd']
                                    ];
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $part_stock['guid'],
                                        'brand' => $part_stock['brand'],
                                        'article' => $part_stock['partnumber'],
                                        'name' => $part_stock['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        'supplier_name' => 'rssk',
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
                                    'delivery_time' => '1.5-2 часа'
                                ];
    
                                array_push($this->finalArr['crosses_on_stock'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => '1.5-2 часа',
                                    'supplier_name' => 'rssk',
                                ]);
                            } elseif (str_contains($innerArr['description'], 'Павлодар') || str_contains($innerArr['description'], 'Караганда') ) {
                                $crosses_stocks[] = [
                                    'stock_id' => $innerArr['id'],
                                    'stock_name' => $innerArr['description'],
                                    'stock_legend' => '',
                                    'qty' => $innerArr['count'],
                                    'price' => round($innerArr['price']),
                                    'delivery_time' => $innerArr['deliveryEnd']
                                ];
    
                                array_push($this->finalArr['crosses_to_order'], [
                                    'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                    'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                    'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                    'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                    'price' => round($innerArr['price']),
                                    'stocks' => $crosses_stocks,
                                    'delivery_time' => $innerArr['deliveryEnd'],
                                    'supplier_name' => 'rssk',
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
                                        'delivery_time' => '1.5-2 часа'
                                    ];
                                    array_push($this->finalArr['crosses_on_stock'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => '1.5-2 часа',
                                        'supplier_name' => 'rssk',
                                    ]);
                                } elseif (str_contains($item['description'], 'Павлодар') || str_contains($item['description'], 'Караганда') ) {
                                    $crosses_stocks[] = [
                                        'stock_id' => $item['id'],
                                        'stock_name' => $item['description'],
                                        'stock_legend' => '',
                                        'qty' => $item['count'],
                                        'price' => round($item['price']),
                                        'delivery_time' => $item['deliveryEnd']
                                    ];
                                    array_push($this->finalArr['crosses_to_order'], [
                                        'guid' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['guid'],
                                        'brand' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['brand'],
                                        'article' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['partnumber'],
                                        'name' => $result['SearchResult']['PartsList']['Part']['crosses']['Part']['name'],
                                        'price' => round($item['price']),
                                        'stocks' => $crosses_stocks,
                                        'delivery_time' => $item['deliveryEnd'],
                                        '   ' => 'rssk',
                                    ]);
                                }
                            }
                        }
                    }
                
            }
            
            
        }
        //dd($this->finalArr);
        return;
    }
*/