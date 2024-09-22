<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SoapClient;
use Illuminate\Support\Facades\View;

class SparePartController extends Controller
{
    const API_KEY1_ROSSKO = '4adcbb9794b8e537bd2aa6272b36bdb0';
    const API_KEY2_ROSSKO = '5fcc040a8188a51baf5a6f36ca15ce05';
    const API_KEY_TREID = '73daf78112373b8326bea5558b0b2ec0';
    const TREID_STORAGE_IDs = [
        168102, 247102, 262102, 48102,
        50102, 79102, 95102, 122102,
        198102,
    ];

    public $finalArr = [

        'searchedNumber' => [
            
        ],
        'crosses' => [
            
        ]
    ];
    

    public function catalogSearch(Request $request) 
    {
        $partNumber = trim($request->partNumber);   
       
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
        
        
        if (!is_countable($result->SearchResult->PartsList->Part)) {
            $finalArr = [];

            array_push($finalArr,[
                'brand' => $result->SearchResult->PartsList->Part->brand,
                'partnumber' => $result->SearchResult->PartsList->Part->partnumber,
                'name' => $result->SearchResult->PartsList->Part->name,
                'guid' => $result->SearchResult->PartsList->Part->guid
            ]);

            return view('catalogSearchRes', ['finalArr' => $finalArr]);
        } else {
            $finalArr = [];

            foreach ($result->SearchResult->PartsList->Part as $part) {
                array_push($finalArr,[
                    'brand' => $part->brand,
                    'partnumber' => $part->partnumber,
                    'name' => $part->name,
                    'guid' => $part->guid
                ]);
            }
            
            return view('catalogSearchRes')->with(['finalArr' => $finalArr]);
        }
        
    }

    public function getSearchedPartAndCrosses (Request $request)
    {
        $this->searchTreid($request->brand, $request->partnumber);
        $this->searchRossko($request->brand, $request->partnumber, $request->guid);
        //dd($this->finalArr);
        return view('partSearchRes', [
            'finalArr' => $this->finalArr
        ]);
    }

    public function searchTreid (String $brand, String $partnumber) 
    {
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
                    $searched_number_stocks = [];
    
                    foreach ($item['stocks'] as $key => $stock) {
                        if ($stock['quantity_unpacked'] > 0) {
                            array_push($searched_number_stocks, $stock);
                        }
                    }
                    array_push($this->finalArr['searchedNumber'], [
                        'guid' => '',
                        'brand' => $item['brand'],
                        'article' => $item['article'],
                        'name' => $item['name'],
                        'item_id' => $item['id'],
                        'price' => $item['price'],
                        'stocks' => $searched_number_stocks,
                        'multiplicity' => '',
                        'type' => '',
                        'delivery' => '',
                        'extra' => '',
                        'description' => '',
                        'deliveryStart' => '',
                        'deliveryEnd' => ''
                    ]);
                }
            }
        }
        //dd($this->finalArr);


        //поиск кроссов по номеру
        $request_data_search_crosses = array(
            "auth_key" => self::API_KEY_TREID,
            "method" => "getReplacesAndCrosses",
            'params' => array(
                "article" => $partnumber,
                "brand" => explode('/', $brand)[0]
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
       
        
        foreach ($result['items'] as $item) {
            if ($item['price']) {
                $crosses_stocks = [];
                foreach ($item['stocks'] as $key => $stock) {
                    if ($stock['quantity_unpacked'] > 0) {
                        array_push($crosses_stocks, $stock);
                    }
                }
                
                if (!empty($crosses_stocks)) {
                        array_push($this->finalArr['crosses'], [
                            'id' => $item['id'],
                            'brand' => $item['brand'],
                            'article' => $item['article'],
                            'name' => $item['name'],
                            'stocks' => $crosses_stocks,
                            'price' => $item['price'],
                            'supplier_name' => '',
                            'delivery_date' => ''  
                        ]);
                    
                    
                }
            }
        }
        //dd($this->finalArr);
        return;
    }


    public function searchRossko(String $brand, String $partNumber, String $guid)
    {   
        $partGuid = $guid;
        
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
        $result = $query->GetSearch($param);
        
        //dd($result);
        //добавляем данные по искомому номеру в итоговый массив
        if ($result->SearchResult->success == true) {
            if ($result->SearchResult->PartsList->Part->stocks) {
                foreach ($result->SearchResult->PartsList->Part->stocks as $key => $stock) {
                    array_push($this->finalArr['searchedNumber'], [
                        'guid' => $result->SearchResult->PartsList->Part->guid,
                        'brand' => $result->SearchResult->PartsList->Part->brand,
                        'article' => $result->SearchResult->PartsList->Part->partnumber,
                        'name' => $result->SearchResult->PartsList->Part->name,
                        'item_id' => $stock->id,
                        'price' => $stock->price,
                        'stocks' => $stock->count,
                        'multiplicity' => $stock->multiplicity,
                        'type' => '',
                        'delivery' => '',
                        'extra' => '',
                        'description' => $stock->description,
                        'deliveryStart' => $stock->deliveryStart,
                        'deliveryEnd' => $stock->deliveryEnd
                    ]);
                }
            }
        }

        //добавляем данные по кроссам в итоговый массив
        if ($result->SearchResult->PartsList->Part->crosses) {
            foreach ($result->SearchResult->PartsList->Part->crosses->Part as $key => $part_stock) {
                array_push($this->finalArr['crosses'], [
                    'id' => $part_stock->guid,
                    'brand' => $part_stock->brand,
                    'article' => $part_stock->partnumber,
                    'name' => $part_stock->name,
                    'stocks' => $part_stock->stocks,
                    'price' => '',
                    'supplier_name' => '',
                    'delivery_date' => ''  
                ]);
            }
        }

        foreach ($result->SearchResult->PartsList->Part->crosses->Part->stocks as $stockItem) {
            # code...
        }

        dd($this->finalArr);
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
}
