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
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon;
use Collator;

// ВАЖНО: имя класса намеренно отличается от SparePartController — при
// совпадении имени в том же неймспейсе Composer-автозагрузчик всё равно
// маппит "SparePartController" на SparePartController.php (PSR-4 по имени
// класса, не по имени файла), и этот файл никогда бы реально не
// подключился под собственным классом.
class SparePartControllerTest extends Controller
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
    const SHATEM_API_KEY = '{a9000264-381b-4c69-9af4-51fdd93b8eda}';
    const ROUND_LIMIT = -1;
    const KULAN_API_KEY='UYWUVoxme116qJlmeSzl7uCsI7Mrlv0D4symnBbR0tyVjMdOMnzkhys5hOvvRoEhcOJYc8Ntcf9sM9tDpUvpz60HTFcMcnJ1mpVU5PNbxuDxJR4DyLhf10y317musSOo';
    const KULAN_ASTSTORE_ID = '2198d63c-35f3-11eb-925f-00155d20f705';
    const CONNECTION_TIMEOUT = 2;
    const TIMEOUT = 3;

    // См. searchAvtozakup() и stratifyByPrice() — защита от раздутого
    // ответа при нестрогом подборе аналогов у Автозакупа.
    const PRICE_STRATIFY_BUCKETS = 10;
    const PRICE_STRATIFY_PER_BUCKET = 15;

    // См. searchRadle() — Radle агрегирует несколько поставщиков сразу, и
    // даже строгое совпадение по артикулу+бренду может дать десятки
    // предложений одного и того же товара с разных складов (замер:
    // 186 шт.). Партиал searchedNumber, в отличие от crosses_to_order, не
    // группирует их в stocks[] (как и у всех остальных поставщиков в этом
    // файле — там searchedNumber всегда плоский список), поэтому такой
    // наплыв заваливал клиентскую пагинацию "первые 10 по цене" — самые
    // дешёвые из десятков предложений Radle вытесняли уже показанные
    // Phaeton/Автозакуп со страницы (баг, найденный живьём 2026-08-23).
    const SEARCHED_NUMBER_RADLE_CAP = 5;

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

    /**
     * ФАЗА 1 прогрессивного поиска: только Rossko (SOAP), отвечает за 1-2
     * сек — самый быстрый источник. Фронт рисует эти строки сразу, не
     * дожидаясь остальных поставщиков (см. searchRestOfSuppliers ниже).
     * Возвращает плоский список офферов той же формы, что ожидает
     * renderOfferRow() в global_product.blade.php, отсортированный по
     * цене по возрастанию.
     */
    public function searchRosskoFast(Request $request)
    {
        $brand      = (string) $request->brand;
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));

        $offers = $this->getRosskoPricesOnly($brand, $partnumber);

        usort($offers, fn($a, $b) => $a['priceWithMargine'] <=> $b['priceWithMargine']);

        return response()->json([
            'offers' => $this->utf8ize($offers),
        ]);
    }

    /**
     * ФАЗА 2 прогрессивного поиска: все остальные поставщики, кроме
     * Rossko — Armtek, Autopiter отдельно (у них своя специфика), плюс
     * runParallelSuppliers() (curl_multi-пул простых REST-источников +
     * Shatem/Treid последовательно следом, см. комментарий в
     * runParallelSuppliers). Фронт вызывает этот эндпоинт ПАРАЛЛЕЛЬНО с
     * searchRosskoFast (не дожидаясь его ответа) и домешивает результат в
     * уже отрисованную таблицу, пересортировывая по цене.
     *
     * Раньше (getSearchedPartAndCrossesOtherJson) в JSON терялся
     * searchedNumber (точное совпадение по артикулу) — отдавались только
     * кроссы. Точное совпадение — самое важное для покупателя, поэтому
     * возвращаем и его тоже, единым списком вместе с кроссами (фронту всё
     * равно, откуда строка — он просто рисует таблицу офферов).
     *
     * Плюс 8 поставщиков, которых не было в изначальном прототипе (сверено
     * с боевым SparePartController::getSearchedPartAndCrosses()):
     * StockInOffice/Zakazauto_kst/Ingvar/Voltage/BlueStar/Interkom/
     * AdilPhaeton — локальные Eloquent-запросы, миллисекунды, зовём
     * синхронно первыми же. Avtozakup — единственный из "хвоста", кто
     * реально ходит в сеть (Http::post) — зовём последовательно рядом с
     * Shatem/Treid.
     */
    public function searchRestOfSuppliers(Request $request)
    {
        $brand      = (string) $request->brand;
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));

        $this->runRestOfSuppliers($brand, $partnumber, (bool) $request->only_on_stock);

        $offers = array_merge(
            $this->finalArr['searchedNumber'],
            $this->finalArr['crosses_on_stock'],
            $this->finalArr['crosses_to_order']
        );

        usort($offers, fn($a, $b) => ($a['priceWithMargine'] ?? 0) <=> ($b['priceWithMargine'] ?? 0));

        return response()->json([
            'offers' => $this->utf8ize($offers),
            'brands' => $this->utf8ize(array_values(array_unique($this->finalArr['brands'] ?? []))),
        ]);
    }

    /**
     * Вынесено из searchRestOfSuppliers() — та же выборка нужна ещё и для
     * HTML-фрагмента partSearchRes (searchRestFragment ниже), чтобы не
     * дублировать список из 11 вызовов дважды.
     */
    private function runRestOfSuppliers(string $brand, string $partnumber, bool $onlyOnStock = false): void
    {
        $this->finalArr['originNumber'] = $partnumber;

        // Локальные БД — быстро, без try/catch по одному не нужно, но на
        // всякий случай оборачиваем группой, чтобы падение одной таблицы
        // не обрушило остальные.
        try {
            $this->searchStockInOffice($brand, $partnumber);
            $this->searchZakazauto_kst($brand, $partnumber);
            $this->searchIngvar($brand, $partnumber);
            $this->searchVoltage($brand, $partnumber);
            $this->searchBlueStar($brand, $partnumber);
            $this->searchInterkom($brand, $partnumber);
            $this->searchAdilPhaeton($brand, $partnumber);
        } catch (\Throwable $e) {}

        try { $this->searchArmtek($brand, $partnumber); } catch (\Throwable $e) {}

        // Как и в боевом getSearchedPartAndCrosses() — Autopiter/Avtozakup
        // пропускаем, если просили только то, что реально на складе.
        if (!$onlyOnStock) {
            try { $this->searchAutopiter($brand, $partnumber); } catch (\Throwable $e) {}
            try { $this->searchAvtozakup($brand, $partnumber); } catch (\Throwable $e) {}
        }

        $this->runParallelSuppliers($brand, $partnumber);

        // Shatem/Treid раньше звались из конца runParallelSuppliers() —
        // вынесены сюда явно, чтобы сам curl_multi-пул можно было гонять
        // отдельно (с фильтром по ключу) для пошаговой подгрузки, не
        // утаскивая за собой эти два последовательных вызова каждый раз.
        try { $this->searchShatem($brand, $partnumber); } catch (\Throwable $e) {}
        try { $this->searchTreid($brand, $partnumber); } catch (\Throwable $e) {}
    }

    /**
     * Рендерит 4 маленьких partial'а (partials/items/*.blade.php — только
     * строки, без шапки секции) и отдаёт их отдельно в JSON. Так и
     * searchRosskoFragment, и searchRestFragment досыпают строки в ТЕ ЖЕ
     * персистентные контейнеры секций (см. partials.searchResultsBody),
     * а не рендерят секцию с шапкой заново — иначе при двух фрагментах
     * заголовок каждой секции задваивался.
     */
    private function renderItemsEnvelope(): array
    {
        usort($this->finalArr['crosses_on_stock'], fn($a, $b) => ($a['priceWithMargine'] ?? 0) <=> ($b['priceWithMargine'] ?? 0));
        usort($this->finalArr['crosses_to_order'], fn($a, $b) => ($a['priceWithMargine'] ?? 0) <=> ($b['priceWithMargine'] ?? 0));

        return [
            'searchedNumber'  => (string) view('partials.items.searchedNumber', ['items' => $this->finalArr['searchedNumber']]),
            'crossesInOffice' => (string) view('partials.items.crossesInOffice', ['items' => $this->finalArr['crosses_in_office']]),
            'crossesOnStock'  => (string) view('partials.items.crossesOnStock', ['items' => $this->finalArr['crosses_on_stock']]),
            'crossesToOrder'  => (string) view('partials.items.crossesToOrder', ['items' => $this->finalArr['crosses_to_order']]),
        ];
    }

    /**
     * ФАЗА 1 прогрессивного partSearchRes: строки от Rossko —
     * используется вместе с searchRestFragment() ниже.
     */
    public function searchRosskoFragment(Request $request)
    {
        $brand      = (string) $request->brand;
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));
        $guid       = (string) $request->guid;

        $this->finalArr['originNumber'] = $partnumber;

        if ($request->rossko_need_to_search && $guid !== '') {
            try { $this->searchRossko($brand, $partnumber, $guid); } catch (\Throwable $e) {}
        }

        return response()->json($this->renderItemsEnvelope());
    }

    /**
     * ФАЗА 2 прогрессивного partSearchRes: строки от всех остальных
     * поставщиков (см. runRestOfSuppliers выше).
     */
    public function searchRestFragment(Request $request)
    {
        $brand      = (string) $request->brand;
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));

        $this->runRestOfSuppliers($brand, $partnumber, (bool) $request->only_on_stock);

        return response()->json($this->renderItemsEnvelope());
    }

    /**
     * Один шаг пошаговой подгрузки — один поставщик (или маленькая группа,
     * см. 'locals') за один вызов. Фронт вызывает это ПОСЛЕДОВАТЕЛЬНО, шаг
     * за шагом (см. STEP_ORDER в partSearchRes.blade.php) — каждый следующий
     * запрос уходит только после того, как предыдущий отрисовался. Так и
     * получается "ощущение живости", которое просил Роман — Rossko сразу,
     * через 2 сек сел Shatem, через 2 сек сел Armtek и т.д., а не одно
     * общее ожидание 15-20 сек на "остальное" разом.
     *
     * 'locals' — единственная группа из нескольких поставщиков в одном
     * шаге: 7 обращений к локальным Eloquent-таблицам, миллисекунды на
     * все сразу, дробить их на отдельные шаги для "живости" смысла нет —
     * посетитель всё равно не увидит между ними паузы.
     *
     * Пул REST-поставщиков (phaeton/forumauto/tiss/kulan/febest/gerat) —
     * тот же runParallelSuppliers(), что и в runRestOfSuppliers(), просто
     * с $onlyKeys, отфильтрованным до одного шага — сам curl-запрос
     * остаётся тем же самым, меняется только то, сколько задач летит за
     * один вызов (1 вместо 8).
     */
    public function searchSupplierStepFragment(Request $request)
    {
        $brand      = (string) $request->brand;
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));
        $step       = (string) $request->step;

        // По умолчанию true — для шагов без runParallelSuppliers (locals/
        // armtek/shatem/treid/autopiter/avtozakup) сохраняем старое
        // поведение ("не бросило исключение — считаем ответившим").
        // Для шести шагов через runParallelSuppliers ниже перезаписываем
        // honest-статусом из её возврата (см. докблок метода) — раньше
        // "N из 13 ответили" в partSearchRes.blade.php всегда засчитывал
        // эти шаги как ответившие, даже если поставщик молча ничего не
        // прислал (пустой ответ / битый JSON / бизнес-ошибка).
        $supplierOk = true;

        try {
            switch ($step) {
                case 'locals':
                    $this->searchStockInOffice($brand, $partnumber);
                    $this->searchZakazauto_kst($brand, $partnumber);
                    $this->searchIngvar($brand, $partnumber);
                    $this->searchVoltage($brand, $partnumber);
                    $this->searchBlueStar($brand, $partnumber);
                    $this->searchInterkom($brand, $partnumber);
                    $this->searchAdilPhaeton($brand, $partnumber);
                    break;
                case 'armtek':
                    $this->searchArmtek($brand, $partnumber);
                    break;
                case 'shatem':
                    $this->searchShatem($brand, $partnumber);
                    break;
                case 'treid':
                    $this->searchTreid($brand, $partnumber);
                    break;
                case 'phaeton_ast':
                    // Разделено 2026-08-23 (было одним шагом 'phaeton' с
                    // объединённым supplierOk через ИЛИ) — Роман: phaeton_local
                    // стабильно падает по IP-вайтлисту (см. переписку с
                    // Фаэтоном, ждут понедельника), из-за чего в общем ИЛИ
                    // это маскировалось, но ему критично отдельно видеть,
                    // не глючит ли именно АСТ-склад — он ищет позиции,
                    // заказанные на Kaspi, напрямую у Фаэтона, когда сайт
                    // говорит "не найдено", и должен отличать "реально нет"
                    // от "АСТ тихо не ответил".
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['phaeton_ast']);
                    $supplierOk = $status['phaeton_ast'] ?? false;
                    break;
                case 'phaeton_local':
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['phaeton_local']);
                    $supplierOk = $status['phaeton_local'] ?? false;
                    break;
                case 'forumauto':
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['forumauto']);
                    $supplierOk = in_array(true, $status, true);
                    break;
                case 'tiss':
                    // v2, не через runParallelSuppliers — см. докблок
                    // searchTiss(). isInStockInHomeWarehousesOnly передаём
                    // их же родным параметром, а не пропускаем шаг целиком.
                    $this->searchTiss($brand, $partnumber, (bool) $request->only_on_stock);
                    break;
                case 'kulan':
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['kulan_main', 'kulan_analogs']);
                    $supplierOk = in_array(true, $status, true);
                    break;
                case 'febest':
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['febest']);
                    $supplierOk = in_array(true, $status, true);
                    break;
                case 'gerat':
                    $status = $this->runParallelSuppliers($brand, $partnumber, ['gerat']);
                    $supplierOk = in_array(true, $status, true);
                    break;
                case 'autopiter':
                    // Как и в боевом getSearchedPartAndCrosses() — пропускаем,
                    // если просили только то, что реально на складе.
                    if (!$request->only_on_stock) {
                        $this->searchAutopiter($brand, $partnumber);
                    }
                    break;
                case 'avtozakup':
                    if (!$request->only_on_stock) {
                        $this->searchAvtozakup($brand, $partnumber);
                    }
                    break;
                case 'radle':
                    if (!$request->only_on_stock) {
                        $this->searchRadle($brand, $partnumber);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            $supplierOk = false;
        }

        return response()->json(array_merge(
            ['supplierOk' => $supplierOk],
            $this->renderItemsEnvelope()
        ));
    }

    /**
     * Шелл-рендер partSearchRes — раньше это был кусок
     * getSearchedPartAndCrosses(), синхронно опрашивающий ВСЕ 19
     * поставщиков перед рендером. Теперь страница рендерится сразу же,
     * без единого обращения к поставщику, а сами данные подгружаются
     * двумя фрагментами через JS в конце partSearchRes.blade.php
     * (searchRosskoFragment + searchRestFragment выше).
     *
     * $brands (список для фильтра слева) тут пустой — тот список раньше
     * тоже строился только ПОСЛЕ того, как все поставщики отвечали,
     * так что при прогрессивной загрузке его настоящее место — тоже в
     * JS, отдельным улучшением, а не блокировать им первый рендер.
     */
    public function getSearchedPartAndCrossesShell(Request $request)
    {
        $partnumber = $this->removeAllUnnecessaries(trim((string) $request->partnumber));

        return view('partSearchRes', [
            'finalArr' => [
                'originNumber' => $partnumber,
                'searchedNumber' => [],
                'crosses_in_office' => [],
                'crosses_on_stock' => [],
                'crosses_to_order' => [],
                'brands' => [],
            ],
            'searchedNumber' => $partnumber,
            'chosenBrand' => $request->brand,
            'brands' => [],
            'guid' => $request->guid,
            'rosskoNeedToSearch' => (bool) $request->rossko_need_to_search,
            'onlyOnStock' => (bool) $request->only_on_stock,
        ]);
    }

    private function utf8ize(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map(fn($v) => $this->utf8ize($v), $data);
        }
        if (is_string($data)) {
            // Если уже валидный UTF-8 — не трогаем
            if (mb_check_encoding($data, 'UTF-8')) return $data;
            // Пробуем конвертировать из windows-1251
            $converted = mb_convert_encoding($data, 'UTF-8', 'windows-1251');
            return mb_check_encoding($converted, 'UTF-8') ? $converted : mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
        }
        return $data;
    }

    /**
     * $onlyKeys — если передан, curl_multi-пул гоняет ТОЛЬКО задачи с этими
     * key (см. 'key' => ... у каждой задачи ниже). Нужно для пошаговой
     * подгрузки (searchSupplierStepFragment) — один и тот же список задач,
     * просто на каждый шаг фильтруем до одного поставщика вместо разом
     * восьми. При $onlyKeys=null (по умолчанию) ведёт себя как раньше —
     * весь пул разом, это путь runRestOfSuppliers()/старого 2-фазного JSON.
     *
     * @return array<string,bool> key задачи => реально ли что-то получили
     *         (не просто "curl не упал", а именно валидный непустой ответ
     *         без бизнес-ошибки). runRestOfSuppliers() этот результат
     *         игнорирует (там нет пошагового индикатора), а
     *         searchSupplierStepFragment передаёт его в JSON как supplierOk.
     */
    private function runParallelSuppliers(string $brand, string $partnumber, ?array $onlyKeys = null): array
    {
        // Определяем все HTTP-запросы которые надо выполнить параллельно
        // Каждый элемент: ['url' => ..., 'method' => 'GET'|'POST', 'data' => [...], 'parser' => 'methodName']
        $tasks = [];

        // --- Phaeton (Sources=1, Астана склад) ---
        $tasks[] = [
            'key'    => 'phaeton_ast',
            'url'    => 'https://api.phaeton.kz/api/Search?' . http_build_query([
                'Article'        => $partnumber,
                'Brand'          => $brand,
                'Sources[]'      => '1',
                'UserGuid'       => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
                'ApiKey'         => '0UKIrpU3W3AnAfDf97Nr',
                'includeAnalogs' => 'true',
            ]),
            'method' => 'GET',
            'parser' => 'parsePhaeton',
        ];

        // --- Phaeton (Sources=2, локальные поставщики) ---
        $tasks[] = [
            'key'    => 'phaeton_local',
            'url'    => 'https://api.phaeton.kz/api/Search?' . http_build_query([
                'Article'        => $partnumber,
                'Brand'          => $brand,
                'Sources[]'      => '2',
                'UserGuid'       => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
                // Второй заход 2026-08-24 — на этот раз по прямому
                // подтверждению программиста Фаэтона: один и тот же ApiKey
                // должен использоваться для обоих запросов (Sources=1 и
                // Sources=2). Первая попытка (до подтверждения от них)
                // временно ломала и AST тоже — возможно, на их стороне
                // тогда ещё не было донастроено под общий ключ. Если снова
                // отвалится AST — откатить на 'LnxrDfpQVZz1ncuoI14e'.
                'ApiKey'         => '0UKIrpU3W3AnAfDf97Nr',
                'includeAnalogs' => 'true',
            ]),
            'method' => 'GET',
            'parser' => 'parsePhaetonLocal',
        ];

        // --- ForumAuto ---
        $tasks[] = [
            'key'    => 'forumauto',
            'url'    => 'https://api.forum-auto.kz/v2/listGoods?' . http_build_query([
                'login' => '432537_popadinets_roman',
                'pass'  => '0xJcsnuE69xI',
                'art'   => $partnumber,
                'cross' => 1,
                'br'    => $brand,
            ]),
            'method' => 'GET',
            'parser' => 'parseForumAuto',
        ];

        // Tiss убран отсюда 2026-08-23 — v2 требует POST+JSON-тело, этот
        // curl_multi-пул поддерживает только GET (см. searchTiss() выше,
        // теперь отдельный шаг через Http::, как Radle/Avtozakup).

        // --- Kulan (productCart) ---
        $tasks[] = [
            'key'    => 'kulan_main',
            'url'    => 'https://connect.adkulan.kz/api/request/api/v2/catalog/article/productCart?' . http_build_query([
                'article' => $partnumber,
                'brand'   => $brand,
            ]),
            'method'  => 'GET',
            'parser'  => 'parseKulanMain',
            'headers' => ['token: ' . self::KULAN_API_KEY, 'Content-Type: application/json'],
        ];

        // --- Kulan (analogues) ---
        $tasks[] = [
            'key'    => 'kulan_analogs',
            'url'    => 'https://connect.adkulan.kz/api/request/api/v2/catalog/article/analogues?' . http_build_query([
                'article'  => $partnumber,
                'brand'    => $brand,
                'order_by' => 'price_asc',
            ]),
            'method'  => 'GET',
            'parser'  => 'parseKulanAnalogs',
            'headers' => ['token: ' . self::KULAN_API_KEY, 'Content-Type: application/json'],
        ];

        // --- Febest ---
        $tasks[] = [
            'key'    => 'febest',
            'url'    => 'https://febest.kz/api/v1/search/{pHgK46xXxD3pxbeyTtWJ}/' . $partnumber,
            'method' => 'GET',
            'parser' => 'parseFebest',
        ];

        // --- Gerat ---
        $tasks[] = [
            'key'    => 'gerat',
            'url'    => 'https://gerat.kz/bitrix/catalog_export/dealer_opt.php',
            'method' => 'GET',
            'parser' => 'parseGerat',
            'partnumber_for_parser' => $partnumber, // Gerat нужно фильтровать по артикулу на нашей стороне
        ];

        if ($onlyKeys !== null) {
            $tasks = array_values(array_filter($tasks, fn($task) => in_array($task['key'], $onlyKeys, true)));
        }

        if (empty($tasks)) {
            return [];
        }

        // ── curl_multi — запускаем все параллельно ──────────────────────────────
        $mh      = curl_multi_init();
        $handles = [];

        foreach ($tasks as $i => $task) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $task['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            if (!empty($task['headers'])) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $task['headers']);
            }

            curl_multi_add_handle($mh, $ch);
            $handles[$i] = ['ch' => $ch, 'task' => $task];
        }

        // Выполняем все запросы параллельно
        $running = null;
        $startTotal = microtime(true);
do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
\Log::info('curl_multi pool выполнен за: ' . round(microtime(true) - $startTotal, 2) . 's');

        // Собираем результаты. $status — по одному булю на key задачи:
        // раньше эта функция была void и ЛЮБОЙ из 3 видов молчаливого
        // провала (пустой $raw / невалидный JSON / бизнес-ошибка вроде
        // IsError у Phaeton) не отражался никак — вызывающий код (и в
        // итоге бейдж "N из 13 ответили" в partSearchRes.blade.php) не
        // мог отличить "поставщик реально ответил" от "тихо ничего не
        // прислал". См. searchSupplierStepFragment ниже — использует
        // это, чтобы передать честный supplierOk во фронт.
        $status = [];

        // Детальное логирование — только для задач из этой карты (key
        // задачи => имя канала в config/logging.php), чтобы не сорить в
        // общий лог для стабильных поставщиков. Расширять сюда же, когда
        // понадобится присмотреться к ещё какому-то поставщику.
        $detailedLogChannels = [
            'phaeton_ast'   => 'phaeton',
            'phaeton_local' => 'phaeton',
        ];

        foreach ($handles as $i => $item) {
            $ch   = $item['ch'];
            $task = $item['task'];

            $raw       = curl_multi_getcontent($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $isPhaeton   = $task['key'] === 'phaeton_ast' || $task['key'] === 'phaeton_local';
            $logChannel  = $detailedLogChannels[$task['key']] ?? null;

            if (!$raw) {
                $status[$task['key']] = false;
                if ($logChannel) {
                    \Log::channel($logChannel)->warning('Пустой ответ (curl)', [
                        'key' => $task['key'], 'brand' => $brand, 'article' => $partnumber,
                        'http_code' => $httpCode, 'curl_errno' => $curlErrno, 'curl_error' => $curlError,
                    ]);
                }
                continue;
            }

            try {
                $data = json_decode($raw);
                // ВАЖНО: json_last_error(), а не голая проверка !$data —
                // валидный пустой массив "[]" (например, ТИСС честно
                // отвечает "предложений нет") в PHP falsy, !$data на нём
                // тоже true, и такой легитимный "пусто" ответ раньше
                // ошибочно считался "не распарсился"/провалом наравне с
                // реально битым JSON. Настоящий провал парсинга —
                // отдельная, узнаваемая по коду ошибка json_decode.
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $status[$task['key']] = false;
                    if ($logChannel) {
                        \Log::channel($logChannel)->warning('Ответ пришёл, но не распарсился как JSON', [
                            'key' => $task['key'], 'brand' => $brand, 'article' => $partnumber,
                            'http_code' => $httpCode, 'raw_head' => mb_substr($raw, 0, 500),
                        ]);
                    }
                    continue;
                }

                if ($logChannel === 'phaeton') {
                    \Log::channel('phaeton')->info('Ответ получен', [
                        'key' => $task['key'], 'brand' => $brand, 'article' => $partnumber,
                        'http_code' => $httpCode,
                        'is_error' => $data->IsError ?? null,
                        'error_message' => $data->ErrorMessage ?? ($data->Message ?? null),
                        'items_count' => isset($data->Items) && is_array($data->Items) ? count($data->Items) : null,
                    ]);
                } elseif ($logChannel === 'tiss') {
                    // ТИСС отвечает голым массивом предложений (не объектом
                    // с флагом ошибки, как Phaeton) — items_count=0 тут
                    // может означать и реальный сбой, и честное "нет
                    // предложений", по одному этому логу не отличить, зато
                    // видно http_code и сам факт валидного/невалидного ответа.
                    \Log::channel('tiss')->info('Ответ получен', [
                        'key' => $task['key'], 'brand' => $brand, 'article' => $partnumber,
                        'http_code' => $httpCode,
                        // (array) безопасно работает и для массива, и для
                        // stdClass — на случай, если ТИСС когда-то отдаст
                        // объект вместо массива.
                        'items_count' => count((array) $data),
                    ]);
                }

                // Phaeton может вернуть валидный JSON с флагом IsError=true
                // (бизнес-ошибка на их стороне) — это тоже провал, просто
                // не curl-уровня и не JSON-уровня. Раньше parsePhaeton/
                // parsePhaetonLocal тихо делали return в этом случае, и
                // задача считалась "выполненной".
                if ($isPhaeton && !empty($data->IsError)) {
                    $status[$task['key']] = false;
                    continue;
                }

                // Вызываем нужный парсер
                $parser = $task['parser'];
                $extraArg = $task['partnumber_for_parser'] ?? null;
                $this->$parser($data, $brand, $partnumber, $extraArg);
                // Пустой (но валидный) ответ ставим success=true — это
                // честное "поставщик ответил, предложений нет", а не сбой
                // (см. комментарий про json_last_error() выше). Иначе для
                // ТИСС каждый обычный "нет в наличии" результат считался
                // бы "не ответил", раздувая счётчик отказов вхолостую.
                $status[$task['key']] = true;

            } catch (\Throwable $e) {
                $status[$task['key']] = false;
                \Log::error("Parser {$task['parser']} failed: " . $e->getMessage());
                if ($logChannel) {
                    \Log::channel($logChannel)->error('Исключение в парсере', [
                        'key' => $task['key'], 'brand' => $brand, 'article' => $partnumber,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        curl_multi_close($mh);

        return $status;
    }

    private function parsePhaeton($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || $data->IsError) return;

        foreach ($data->Items as $item) {
            if ($item->Warehouse !== 'Астана') continue;

            if (strtolower($this->removeAllUnnecessaries($item->Article)) === strtolower($partnumber)) {
                array_push($this->finalArr['brands'], $item->Brand);
                array_push($this->finalArr['searchedNumber'], [
                    'brand'          => $item->Brand,
                    'article'        => $item->Article,
                    'name'           => substr($item->Name, 0, 60),
                    'price'          => $item->Price,
                    'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                    'qty'            => $item->AvailableCount,
                    'multiplicity'   => '',
                    'type'           => '',
                    'delivery'       => '',
                    'extra'          => '',
                    'description'    => 'phtn',
                    'deliveryStart'  => date('d.m.Y'),
                    'deliveryEnd'    => date('d.m.Y'),
                    'supplier_name'  => 'phtn',
                    'supplier_city'  => 'ast',
                    'supplier_color' => '#feed00',
                ]);
            } else {
                array_push($this->finalArr['brands'], $item->Brand);
                array_push($this->finalArr['crosses_on_stock'], [
                    'brand'          => $item->Brand,
                    'article'        => $item->Article,
                    'name'           => substr($item->Name, 0, 60),
                    'qty'            => $item->AvailableCount,
                    'stocks'         => [[
                        'qty'              => $item->AvailableCount,
                        'price'            => $item->Price,
                        'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                    ]],
                    'price'          => $item->Price,
                    'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                    'supplier_name'  => 'phtn',
                    'delivery_date'  => '',
                    'delivery_time'  => '2 часа',
                    'supplier_city'  => $item->Warehouse,
                    'supplier_color' => '#feed00',
                ]);
            }
        }
    }

    private function parsePhaetonLocal($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || $data->IsError) return;

        foreach ($data->Items as $item) {
            array_push($this->finalArr['brands'], $item->Brand);
            array_push($this->finalArr['crosses_to_order'], [
                'brand'          => $item->Brand,
                'article'        => $item->Article,
                'name'           => substr($item->Name, 0, 60),
                'qty'            => $item->AvailableCount,
                'price'          => $item->Price,
                'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                'delivery_time'  => date('d.m.Y', strtotime('+' . $item->GuaranteedDelivery . 'day')),
                // Фаэтон Локал (Sources=2) — по просьбе Романа 2026-08-25,
                // как и Автозакуп/Radle, международный возврат невозможен.
                'returnable'     => false,
                'stocks'         => [[
                    'qty'              => $item->AvailableCount,
                    'price'            => $item->Price,
                    'priceWithMargine' => round($this->setPrice($item->Price), self::ROUND_LIMIT),
                ]],
                'supplier_name'  => 'phtn',
                'supplier_city'  => $item->Warehouse,
                'supplier_color' => '#feed00',
            ]);
        }
    }

    private function parseForumAuto($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || (gettype($data) === 'object' && property_exists($data, 'errors'))) return;

        foreach ($data as $item) {
            if ($item->whse !== 'AST') continue;

            if (strtolower($this->removeAllUnnecessaries($item->art)) === strtolower($partnumber)) {
                array_push($this->finalArr['brands'], $item->brand);
                array_push($this->finalArr['searchedNumber'], [
                    'brand'          => $item->brand,
                    'article'        => $item->art,
                    'name'           => substr($item->name, 0, 60),
                    'price'          => $item->price,
                    'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                    'qty'            => $item->num,
                    'multiplicity'   => '',
                    'type'           => '',
                    'delivery'       => '',
                    'extra'          => '',
                    'description'    => 'frmt',
                    'deliveryStart'  => date('d.m.Y'),
                    'deliveryEnd'    => date('d.m.Y'),
                    'supplier_name'  => 'frmt',
                    'supplier_city'  => 'Астана',
                    'supplier_color' => '#333',
                ]);
            } else {
                array_push($this->finalArr['brands'], $item->brand);
                array_push($this->finalArr['crosses_on_stock'], [
                    'brand'          => $item->brand,
                    'article'        => $item->art,
                    'name'           => substr($item->name, 0, 60),
                    'qty'            => $item->num,
                    'price'          => $item->price,
                    'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                    'supplier_name'  => 'frmt',
                    'delivery_date'  => '',
                    'delivery_time'  => '2 часа',
                    'supplier_city'  => 'Астана',
                    'supplier_color' => '#333',
                ]);
            }
        }
    }

    private function parseKulanMain($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || property_exists($data, 'messages') || empty($data->data)) return;

        foreach ($data->data as $item) {
            foreach ($item->remains as $store) {
                if ($store->store_id !== self::KULAN_ASTSTORE_ID) continue;

                array_push($this->finalArr['brands'], $item->manufacturer);
                array_push($this->finalArr['searchedNumber'], [
                    'brand'            => $item->manufacturer,
                    'article'          => $item->article,
                    'name'             => $item->name,
                    'price'            => $store->price,
                    'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                    'qty'              => $store->quantity,
                    'supplier_city'    => 'ast',
                    'supplier_name'    => 'kln',
                    'supplier_color'   => 'green',
                    'deliveryStart'    => date('d-m-Y'),
                ]);
            }
        }
    }

    private function parseKulanAnalogs($data, string $brand, string $partnumber, $extra = null): void
    {
        if (empty($data) || !$data || (gettype($data) === 'object' && property_exists($data, 'messages'))) return;

        foreach ($data as $item) {
            foreach ($item->remains as $store) {
                if ($store->id !== self::KULAN_ASTSTORE_ID) continue;

                array_push($this->finalArr['brands'], $item->manufacturer);
                array_push($this->finalArr['crosses_on_stock'], [
                    'brand'            => $item->manufacturer,
                    'article'          => $item->article,
                    'name'             => $item->name,
                    'stock_legend'     => $store->store,
                    'qty'              => $store->quantity,
                    'price'            => $store->price,
                    'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                    'delivery_time'    => '2 часа',
                    'stocks'           => [[
                        'qty'              => $store->quantity,
                        'price'            => $store->price,
                        'priceWithMargine' => round($this->setPrice($store->price), self::ROUND_LIMIT),
                    ]],
                    'supplier_name'    => 'kln',
                    'supplier_city'    => 'ast',
                    'supplier_color'   => '#0000ff',
                ]);
            }
        }
    }

    private function parseFebest($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || (gettype($data) === 'object' && property_exists($data, 'error'))) return;

        foreach ($data as $item) {
            array_push($this->finalArr['crosses_on_stock'], [
                'brand'            => $item->manufacturer,
                'article'          => $item->code,
                'name'             => $item->name,
                'price'            => $item->price,
                'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                'qty'              => $item->amount,
                'supplier_name'    => 'fbst',
                'stock_legend'     => 'Астана',
                'delivery_time'    => '2 часа',
                'supplier_city'    => 'ast',
                'supplier_color'   => '#a27745',
            ]);
        }
    }

    private function parseGerat($data, string $brand, string $partnumber, $extra = null): void
    {
        if (!$data || !isset($data->shop->offers->offer)) return;

        foreach ($data->shop->offers->offer as $item) {
            $cross_numbers = explode(', ', $item->description);

            foreach ($cross_numbers as $cross_number) {
                $articleMatch = strtolower($this->removeAllUnnecessaries((string)$item->vendorCode)) === strtolower($partnumber);
                $crossMatch   = strtolower($cross_number) === strtolower($partnumber);

                if (!$articleMatch && !$crossMatch) continue;

                $params     = $item->param ?? [];
                $infoParams = [];
                if (count($params) >= 4 && isset($params[3])) {
                    $infoParams = [
                        'OEM'        => explode(',', $params[3]),
                        'suitable_to'=> '',
                        'tech_info'  => '',
                        'sizes'      => [
                            'width'  => $params[6] ?? 'нет информации',
                            'height' => $params[5] ?? 'нет информации',
                            'depth'  => $params[4] ?? 'нет информации',
                        ],
                    ];
                }

                array_push($this->finalArr['brands'], $item->vendor);

                if ($articleMatch) {
                    array_push($this->finalArr['searchedNumber'], [
                        'brand'            => $item->vendor,
                        'article'          => $item->vendorCode,
                        'name'             => substr($item->model, 0, 60),
                        'price'            => $item->price,
                        'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                        'qty'              => $item->count,
                        'supplier_name'    => 'grt',
                        'supplier_city'    => 'Астана',
                        'supplier_color'   => '#7bafcf',
                        'deliveryStart'    => '2 часа',
                        'info'             => [
                            'pictures' => $item->picture ?? '',
                            'params'   => $infoParams,
                        ],
                    ]);
                } else {
                    array_push($this->finalArr['crosses_on_stock'], [
                        'brand'            => $item->vendor,
                        'article'          => $item->vendorCode,
                        'name'             => substr($item->model, 0, 60),
                        'qty'              => $item->count,
                        'price'            => $item->price,
                        'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                        'delivery_time'    => '2 часа',
                        'info'             => [
                            'pictures' => $item->picture ?? 0,
                            'params'   => $infoParams,
                        ],
                        'stocks'           => [[
                            'qty'              => $item->count,
                            'price'            => $item->price,
                            'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                        ]],
                        'supplier_name'    => 'grt',
                        'supplier_city'    => 'Астана',
                        'supplier_color'   => '#feed00',
                    ]);
                }
                break; // нашли совпадение по этому item — дальше не ищем
            }
        }
    }



    //старые методы
    public function searchPhaeton(String $brand, String $partnumber) 
    {
        //$start = microtime(true);

        $ch = curl_init();
 
        $params = [
            'Article' => $partnumber,
            'Brand' => $brand,
            'Sources[]' => '1',
            'UserGuid' => '9F6414C4-9683-11EF-BBBC-F8F21E092C7D',
            'ApiKey' => '0UKIrpU3W3AnAfDf97Nr',
            'includeAnalogs' => 'true'
        ];

        curl_setopt($ch, CURLOPT_URL, 'https://api.phaeton.kz/api/Search?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        try {
            $response = json_decode(curl_exec($ch));
        } catch (\Throwable $th) {
            return;
        }
        //dd($response);
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
                        'delivery_time' => '2 часа',
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
            'ApiKey' => '0UKIrpU3W3AnAfDf97Nr',
            'includeAnalogs' => 'true'
        ];

        curl_setopt($ch1, CURLOPT_URL, 'https://api.phaeton.kz/api/Search?' . http_build_query($params1));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
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
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. phtn';
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
                        'supplier_color' => '#feed00'
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
                        'delivery_time' => '2 часа',
                        'supplier_city' => 'Астана',
                        'supplier_color' => '#34689e'
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
                            'delivery_time' => '2 часа',
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
                                    'delivery_time' => '2 часа',
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
                                    'delivery_time' => '2 часа',
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
                                        'delivery_time' => '2 часа'
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
                                        'delivery_time' => '2 часа',
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
                                    'delivery_time' => '2 часа'
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
                                    'delivery_time' => '2 часа',
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
                                        'delivery_time' => '2 часа'
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
                                        'delivery_time' => '2 часа',
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
                        'delivery_time'    => $isAstana ? '2 часа' : ($stock['deliveryEnd'] ?? '3-5 дней'),
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
                        'delivery_time' => '2 часа',
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
        
        // Токен — из кеша (см. getShatemToken(): Cache::lock() защищает от
        // гонки при параллельном опросе поставщиков). Раньше тут был
        // свежий логин на каждый вызов — лишний медленный HTTP-хоп плюс
        // источник как раз того "отваливания" при параллелизации.
        $access_token = $this->getShatemToken();

        if (!$access_token) {
            return;
        }

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
                            'delivery_time' => '2 часа',
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
                            'delivery_time' => '2 часа',
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

    /**
     * TISS (tabys.parts) API v2 — заменяет устаревший v1
     * (api.tiss.parts/StockByArticle через $tasks в runParallelSuppliers +
     * parseTiss(), оба удалены). TISS в переписке 2026-08-23 сам сказал,
     * что v1 "уже не поддерживается" и рекомендовал v2 — на этот же v1
     * ссылалась открытая проблема в CLAUDE.md ("сайт показывает остаток,
     * API — пустой offers").
     *
     * v2 требует POST с JSON-телом — общий curl_multi-пул в
     * runParallelSuppliers физически поддерживает только GET (в его коде
     * просто нет CURLOPT_POST/POSTFIELDS, комментарий про 'method' =>
     * 'GET'|'POST' там был давно неактуален), поэтому TISS теперь
     * отдельный шаг через Http::, как Radle/Avtozakup.
     *
     * Хост/метод/формат тела подтверждены ЖИВЫМ запросом 2026-08-23 — их
     * официальная дока даёт путь без хоста и ошибочно помечает метод как
     * "Get" (реальный GET отвечает 405, нужен POST на api.tabys.parts).
     * Там же выяснилось: поля priceFrom/priceTo/deliveryMinDays/
     * deliveryMaxDays обязаны физически присутствовать в теле (пусть и
     * null) — без них 400 REQUEST_FORMAT_ERROR, хотя вводный текст доки
     * говорит, что они опциональны. Живой ответ также показал два поля,
     * которых нет в доке вообще: offeringBlockType имеет третье значение
     * "AnalogOnOrderProduct" (аналог под заказ, не с физического склада —
     * offers[].warehouseId тогда null), и deliveryInfo несёт ещё
     * guaranteedDate/allowed.
     *
     * isInStockInHomeWarehousesOnly — родной параметр их API под ровно ту
     * же семантику, что и наш $onlyOnStock, поэтому TISS (в отличие от
     * Autopiter/Avtozakup/Radle) не пропускается целиком при
     * only_on_stock — просто передаём флаг дальше, пусть TISS сам
     * отфильтрует на своей стороне.
     */
    public function searchTiss(String $brand, String $partnumber, bool $onlyOnStock = false)
    {
        $apiKey     = env('TISS_API_KEY');
        $contractId = env('TISS_CONTRACT_ID');
        $outletId   = env('TISS_OUTLET_ID');

        if (empty($apiKey) || empty($contractId) || empty($outletId)) {
            \Log::warning('TISS v2: не заданы TISS_API_KEY/TISS_CONTRACT_ID/TISS_OUTLET_ID в .env');
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-External-Api-Key' => $apiKey])
                ->post('https://api.tabys.parts/v1/product-offers/by-brand-and-product-code', [
                    'products' => [[
                        'productCode' => $partnumber,
                        'brandName'   => $brand,
                    ]],
                    'contractId'                     => $contractId,
                    'outletId'                        => $outletId,
                    'priceFrom'                       => null,
                    'priceTo'                          => null,
                    'deliveryMinDays'                 => null,
                    'deliveryMaxDays'                 => null,
                    'offersMaxNum'                     => 50,
                    'orderByPrice'                     => true,
                    'enableAnalog'                     => true,
                    'warehouses'                       => [],
                    'isInStockInHomeWarehousesOnly'    => $onlyOnStock,
                ]);

            if (!$response->successful()) {
                \Log::warning('TISS v2 non-2xx response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return;
            }

            $groups = $response->json();
            if (empty($groups)) {
                return;
            }

            foreach ($groups as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    // Только реально доступные прямо сейчас предложения —
                    // тот же принцип, что и у остальных поставщиков в этом
                    // файле (price<=0 пропускаем). expectedAmount/
                    // expectedArrivalDate (товар в пути) есть в ответе, но
                    // пока не используются — см. докблок метода.
                    $offers = array_values(array_filter($item['offers'] ?? [], function ($offer) {
                        return (float) ($offer['price'] ?? 0) > 0 && (int) ($offer['amount'] ?? 0) > 0;
                    }));

                    if (empty($offers)) {
                        continue;
                    }

                    usort($offers, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
                    $cheapest = $offers[0];
                    $price = (float) $cheapest['price'];

                    $stocks = array_map(function ($offer) {
                        $offerPrice = (float) $offer['price'];
                        return [
                            'qty'              => (int) ($offer['amount'] ?? 0),
                            'price'            => $offerPrice,
                            'priceWithMargine' => round($this->setPrice($offerPrice), self::ROUND_LIMIT),
                            'delivery_time'    => $offer['deliveryInfo']['date'] ?? null,
                            'supplier_city'    => $this->tissCityFromWarehouseName($offer['warehouseName'] ?? ''),
                        ];
                    }, $offers);

                    $entry = [
                        'brand'            => $item['brandName'] ?? '',
                        'article'          => $item['displayProductCode'] ?? ($item['productCode'] ?? ''),
                        'name'             => $item['productName'] ?? '',
                        'price'            => $price,
                        'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                        'qty'              => (int) ($cheapest['amount'] ?? 0),
                        'delivery_time'    => $cheapest['deliveryInfo']['date'] ?? null,
                        'deliveryStart'    => $cheapest['deliveryInfo']['date'] ?? null,
                        'supplier_name'    => 'tss',
                        'supplier_city'    => $this->tissCityFromWarehouseName($cheapest['warehouseName'] ?? ''),
                        'supplier_color'   => '#7bafcf',
                        'stocks'           => $stocks,
                    ];

                    array_push($this->finalArr['brands'], $item['brandName'] ?? '');

                    if (($item['offeringBlockType'] ?? '') === 'RequestedProduct') {
                        array_push($this->finalArr['searchedNumber'], $entry);
                    } else {
                        array_push($this->finalArr['crosses_to_order'], $entry);
                    }
                }
            }

        } catch (\Exception $e) {
            \Log::error('TISS v2 exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }

    // "Алматы, Микрорайон Алгабас, улица 7, дом 130/13" → "Алматы" — их v2
    // отдаёт реальный адрес склада, а не только город, для supplier_city
    // (видно всем посетителям, как и у остальных поставщиков) хватает
    // первого сегмента до запятой.
    private function tissCityFromWarehouseName(?string $warehouseName): string
    {
        if (empty($warehouseName)) {
            return 'ast';
        }
        $parts = explode(',', $warehouseName);
        $city = trim($parts[0]);
        return $city !== '' ? $city : 'ast';
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
                        'delivery_time' => '2 часа',
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
                'delivery_time' => '2 часа',
                'supplier_city' => 'ast',
                'supplier_color' => '#a27745',
            ]);
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. fbst';

        return;
    }

    public function searchGerat(String $brand, String $partnumber)
    {
        //$start = microtime(true);old anchor 'https://gerat.kz/bitrix/catalog_export/dealer_opt.php'
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://gerat.kz/bitrix/catalog_export/dealer_opt.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $result = curl_exec($ch);
        $xml_snippet = simplexml_load_string( $result );
        $json_convert = json_encode( $xml_snippet );
        $json = json_decode( $json_convert );
        if (!$json) {
			return;
		}
        //dd($json);
        foreach ($json->shop->offers->offer as $item) {
            //dd($item);
            $cross_numbers = explode(', ', $item->description);
            
            foreach ($cross_numbers as $cross_number) {
                if (strtolower($cross_number) == strtolower($partnumber) || strtolower($partnumber) == strtolower($this->removeAllUnnecessaries($item->vendorCode))) {
                    if (strtolower($partnumber) == strtolower($this->removeAllUnnecessaries($item->vendorCode))) {
                        array_push($this->finalArr['brands'], $item->vendor);
                        //dd($item);
                        array_push($this->finalArr['searchedNumber'], [
                            'brand' => $item->vendor,
                            'article' => $item->vendorCode,
                            'name' => substr($item->model, 0, 60),
                            'price' => $item->price,
                            'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                            'qty' => $item->count,
                            'supplier_name' => 'grt',
                            'supplier_city' => 'Астана',
                            'supplier_color' => '#7bafcf',
                            'deliveryStart' => '2 часа',
                            'info' => [
                                'pictures' => $item->picture ?? '',
                                'params' => count($item->param) <=3 ? [] : [
                                    'OEM' => explode(',', $item->param[3]),
                                    'suitable_to' => '',
                                    'tech_info' => '',
                                    'sizes' => count($item->param) > 4 ?[
                                        'width' => $item->param[6],
                                        'height' => $item->param[5],
                                        'depth' => $item->param[4]
                                    ] : [
                                        'width' => 'нет информации',
                                        'height' => 'нет информации',
                                        'depth' => 'нет информации'
                                    ]
                                ],
                            ],
                        ]);
                    } else {
                        array_push($this->finalArr['brands'], $item->vendor);
                        //dd($item);
                        // 1. Сначала готовим безопасные параметры
                        $params = $item->param ?? [];
                        $infoParams = [];

                        // Проверяем, что в массиве хотя бы 4 элемента для OEM
                        if (count($params) >= 4 && isset($params[3])) {
                            $infoParams = [
                                'OEM' => explode(',', $params[3]),
                                'suitable_to' => '',
                                'tech_info' => '',
                                'sizes' => [
                                    // Проверяем наличие каждого индекса отдельно, чтобы не поймать Undefined Key
                                    'width' => isset($params[6]) ? $params[6] : 'нет информации',
                                    'height' => isset($params[5]) ? $params[5] : 'нет информации',
                                    'depth' => isset($params[4]) ? $params[4] : 'нет информации',
                                ]
                            ];
                        }

                        // 2. Теперь вставляем это в твой основной массив
                        array_push($this->finalArr['crosses_on_stock'], [
                            'brand' => $item->vendor,
                            'article' => $item->vendorCode,
                            'name' => substr($item->model, 0, 60),
                            'qty' => $item->count,
                            'price' => $item->price,
                            'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                            'delivery_time' => "2 часа",
                            'info' => [
                                'pictures' => $item->picture ?? 0,
                                'params' => $infoParams, // Вставляем уже подготовленный массив
                            ],
                            'stocks' => [
                                [
                                    'qty' => $item->count,
                                    'price' => $item->price,
                                    'priceWithMargine' => round($this->setPrice($item->price), self::ROUND_LIMIT),
                                ]
                            ],
                            'supplier_name' => 'grt',
                            'supplier_city' => 'Астана',
                            'supplier_color' => '#feed00'
                        ]);
                    }
                }
            }
        }
        //echo 'Время выполнения скрипта: '.round(microtime(true) - $start, 4).' сек. grt';
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
                        'supplier_color' => '255, 193, 7'
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
                    'supplier_color' => '255, 193, 7',
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
                        'supplier_color' => '255, 193, 7'
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
                    'supplier_color' => '255, 193, 7',
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
                    'delivery_time' => '2 часа',
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

    /**
     * Скопировано без изменений из боевого SparePartController.php —
     * единственный поставщик из "хвоста" последовательного списка (кроме
     * Shatem/Treid), который реально ходит в сеть (Http::post), а не
     * читает локальную БД. Остальные 7 (searchStockInOffice/
     * searchZakazauto_kst/searchIngvar/searchVoltage/searchBlueStar/
     * searchInterkom/searchAdilPhaeton) — локальные Eloquent-запросы,
     * миллисекунды, им curl_multi/последовательный вызов не нужен, они уже
     * достаточно быстрые сами по себе.
     */
    public function searchAvtozakup(String $brand, String $partnumber)
    {
        try {
            // 'timelimit' — сколько секунд САМ Tradesoft вправе ждать ответ
            // от апстрима (avto_zakup). Раньше стояло 10 — ровно столько же,
            // сколько браузер ждёт весь шаг целиком (SUPPLIER_TIMEOUT_MS в
            // partSearchRes.blade.php), т.е. без единого запаса на наш
            // собственный сетевой круг + рендер Blade + сериализацию JSON.
            // Как только апстрим Автозакупа отвечал медленно (обычное дело
            // для реального дропшип-поставщика на заказные позиции), браузер
            // обрывал запрос раньше, чем Tradesoft вообще успевал прислать
            // данные — и никакой ошибки при этом не было: AbortSignal рвёт
            // соединение на клиенте, сервер продолжает работать молча.
            // Первая попытка снизить до 6 сек оказалась СЛИШКОМ жёсткой —
            // сам Tradesoft начал отдавать "Превышено время ожидания 6 сек."
            // (см. лог 'Avtozakup empty or error response'), т.е. реальному
            // апстриму на заказные позиции нужно больше 6 сек, а клиент готов
            // столько ждать (см. STEP_TIMEOUTS_MS.avtozakup = 18000 в
            // partSearchRes.blade.php). 12 — с запасом под 18-секундный
            // клиентский бюджет (сеть + рендер Blade + сериализация).
            // Http::timeout(20) — свой PHP-клиент тоже поднят выше 12, иначе
            // он сам оборвал бы запрос раньше, чем Tradesoft успеет уложиться
            // в свои 12 секунд ожидания + отдать ответ.
            $response = Http::timeout(20)->post('https://service.tradesoft.ru/3/provider/get-price-list/', [
                'user'      => env('TRADESOFT_USER'),
                'password'  => env('TRADESOFT_PASSWORD'),
                'service'   => 'provider',
                'action'    => 'getPriceList',
                'timelimit' => 12,
                'container' => [[
                    'provider' => 'avto_zakup',
                    'login'    => env('TRADESOFT_PROVIDER_LOGIN'),
                    'password' => env('TRADESOFT_PROVIDER_PASSWORD'),
                    'code'     => $partnumber,
                    'producer' => $brand,
                ]],
            ]);

            if (!$response->ok()) {
                \Log::warning('Avtozakup non-200 response', ['status' => $response->status()]);
                return;
            }

            $data = $response->json();

            // container[0]['error'] — ошибка именно от провайдера (напр.
            // "не удалось авторизоваться"), а не верхнеуровневая $data['error'];
            // раньше проверяли только верхний уровень и такие сбои проходили
            // мимо логов молча (см. CLAUDE.md, история с протухшими TRADESOFT_
            // PROVIDER_LOGIN/PASSWORD в .env).
            $providerError = $data['container'][0]['error'] ?? null;
            if (!empty($data['error']) || !empty($providerError) || empty($data['container'][0]['data'])) {
                \Log::warning('Avtozakup empty or error response', [
                    'top_level_error' => $data['error'] ?? null,
                    'provider_error'  => $providerError,
                    'has_data'        => !empty($data['container'][0]['data']),
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
                    // Автозакуп — поставки из РФ, международный возврат
                    'returnable'       => false,
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

            // У Автозакупа подбор аналогов местами очень нестрогий — на
            // некоторые артикулы фильтр по срокам доставки выше всё равно
            // оставляет тысячи позиций (реальный случай: 4553 шт. на один
            // артикул => ~15 МБ одного JSON-ответа, на слабом интернете
            // легко вылезает за клиентский таймаут прогрессивной подгрузки,
            // хотя сам запрос к Tradesoft отрабатывает за секунды).
            //
            // Просто взять самые дешёвые — не выход (Роман: "не все ищут
            // именно дешёвые"), поэтому — честная стратифицированная выборка
            // по цене: диапазон от мин. до макс. цены делим на равные
            // ценовые бакеты, из каждого берём не больше N штук. Так виден
            // весь разброс цен — и бюджетные, и премиальные варианты — а не
            // только нижний край.
            if (count($this->finalArr['crosses_to_order']) > self::PRICE_STRATIFY_BUCKETS * self::PRICE_STRATIFY_PER_BUCKET) {
                $this->finalArr['crosses_to_order'] = $this->stratifyByPrice(
                    $this->finalArr['crosses_to_order'],
                    self::PRICE_STRATIFY_BUCKETS,
                    self::PRICE_STRATIFY_PER_BUCKET
                );
            }

        } catch (\Exception $e) {
            \Log::error('Avtozakup exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /**
     * Radlekz (radle.kz) — заказной агрегатор нескольких поставщиков,
     * опрашивается ПОСЛЕ Phaeton и Avtozakup (см. STEP_ORDER в
     * partSearchRes.blade.php — так попросил Роман). По их официальной
     * доке: таймаут — не меньше 60 сек, первый запрос по новому артикулу
     * реально идёт 10-15 сек (поставщики опрашиваются вживую), повтор по
     * тому же артикулу отдаётся из ИХ кэша за доли секунды (кэш живёт до
     * 10 минут). Http::timeout(65) — небольшой запас сверх их минимума,
     * тот же приём, что и в searchAvtozakup (20 сек клиент против 12 сек
     * апстрима).
     */
    public function searchRadle(String $brand, String $partnumber)
    {
        $apiKey = env('RADLE_API_KEY');
        if (empty($apiKey)) {
            \Log::warning('Radle API key not configured (RADLE_API_KEY)');
            return;
        }

        try {
            // Их доке: X-Request-ID можно передать своим — пройдёт насквозь
            // в их журнал, трассировка на нашей стороне не порвётся.
            $requestId = (string) Str::uuid();

            $response = Http::timeout(65)
                ->withHeaders([
                    'x-api-key'    => $apiKey,
                    'X-Request-ID' => $requestId,
                ])
                ->get('https://radle.kz/api/v1/search', [
                    'article' => $partnumber,
                ]);

            $data = $response->json();

            // По их доке проверять надо именно success в теле, а не только
            // HTTP-код — ошибка тоже валидный JSON (400/401/403/429/500).
            if (empty($data['success'])) {
                // 429 (после Retry-After) и 500 (с бэкоффом) по их
                // чек-листу стоило бы повторить — но это синхронный шаг
                // живой прогрессивной подгрузки с собственным клиентским
                // таймаутом (STEP_TIMEOUTS_MS.radle), реальное ожидание
                // Retry-After почти наверняка не уложится и оборвётся
                // браузером раньше. Логируем и отдаём пустой шаг; настоящий
                // retry имеет смысл только в batch/cron-сценарии, не здесь.
                \Log::warning('Radle error response', [
                    'http_status' => $response->status(),
                    'code'        => $data['error']['code'] ?? null,
                    'message'     => $data['error']['message'] ?? null,
                    'request_id'  => $data['meta']['requestId'] ?? $requestId,
                ]);
                return;
            }

            $parts = $data['data']['parts'] ?? [];
            if (empty($parts)) {
                // success:true с пустым parts — по их доке НЕ ошибка,
                // просто ничего не нашлось по артикулу.
                return;
            }

            $searchArticleClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $partnumber));
            $searchBrandLower = strtolower(trim($brand));

            // Коды из supplier (KAZ/ALM/UAE/RUS...) — реальный источник,
            // через который Radle закупает. Роман явно попросил (2026-08-23,
            // по первым живым результатам): для обычных посетителей это
            // сворачивается до страны — все казахстанские подкоды (KAZ,
            // ALM — Алматы) показываются просто как "KZ", UAE остаётся
            // "UAE" как есть. Список неполный (доке даёт только пример KAZ,
            // остальное — из живых ответов) — для не перечисленных кодов
            // просто показываем код как есть, это не более раскрывающе, чем
            // сам факт страны. Точную детализацию (rdl KAZ / rdl ALM / rdl
            // UAE) видит только админ через supplier_name, как и раньше.
            $radleCustomerCountryLabels = [
                'KAZ' => 'KZ',
                'ALM' => 'KZ',
                'UAE' => 'UAE',
                'RUS' => 'РФ',
            ];

            foreach ($parts as $part) {
                $price = (float) ($part['price'] ?? 0);
                if ($price <= 0) {
                    continue;
                }

                $itemArticleClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $part['article'] ?? ''));

                // Баг, найденный живьём 2026-08-23 (Роман: "все остальное
                // исчезает с экрана"): Radle агрегирует МНОГО поставщиков
                // сразу, и по ходовым артикулам (напр. "OC90") десятки
                // РАЗНЫХ производителей выпускают деталь под тем же кодом
                // (замер: 305 предложений от 11 разных брендов на один
                // артикул). Раньше isExact сверял только артикул — все 305
                // уходили в searchedNumber ("Запрошенный артикул"),
                // sortContainerByPrice пересортировывал ВЕСЬ контейнер по
                // цене, а updateSearchedNumberPagination показывает только
                // первые 10 после сортировки — уже отрисованные строки
                // Phaeton/Автозакупа проваливались за пределы видимой
                // страницы. Теперь для searchedNumber требуем совпадения
                // ЕЩЁ и бренда (нестрого — тем же приёмом, что и в
                // searchAutopiter выше: подстрока в любую сторону, регистр
                // не важен), иначе — в crosses_to_order как кросс/аналог,
                // что этим предложениям и является на самом деле.
                $itemBrandLower = strtolower(trim($part['manufacturer'] ?? ''));
                $isBrandMatch = $searchBrandLower !== '' && $itemBrandLower !== '' && (
                    $itemBrandLower === $searchBrandLower ||
                    str_contains($itemBrandLower, $searchBrandLower) ||
                    str_contains($searchBrandLower, $itemBrandLower)
                );
                $isExact = $isBrandMatch && $itemArticleClean === $searchArticleClean;

                // deliveryTime — человекочитаемая строка вида "0-1 день", их
                // доке явно говорит "формат может меняться, не парсите для
                // расчётов". Наш блейд-партиал crossesToOrder тем не менее
                // считает срок через strtotime($delivery_time), и Роман
                // явно просил отсекать RUS с долгим ожиданием (см. ниже) —
                // best-effort берём верхнюю границу диапазона; если строка
                // не содержит числа вообще — просто не проставляем дату,
                // партиал в этом случае покажет "уточняйте".
                $deliveryTimeText = (string) ($part['deliveryTime'] ?? '');
                preg_match_all('/\d+/', $deliveryTimeText, $dayMatches);
                $deliveryDate = null;
                $maxDeliveryDays = null;
                if (!empty($dayMatches[0])) {
                    $maxDeliveryDays = (int) end($dayMatches[0]);
                    $deliveryDate = date('Y-m-d', strtotime('+' . $maxDeliveryDays . ' days'));
                }

                $warehouse = (string) ($part['warehouse'] ?? '');
                $supplierCode = strtoupper((string) ($part['supplier'] ?? ''));

                // Роман (2026-08-23, по первым живым результатам): RUS с
                // ожиданием больше 14 дней вообще не показывать — не только
                // клиенту, целиком, такая позиция бесполезна при живых
                // сроках доставки день-в-день/на следующий день, которые и
                // есть конкурентное преимущество магазина.
                if ($supplierCode === 'RUS' && $maxDeliveryDays !== null && $maxDeliveryDays > 14) {
                    continue;
                }

                // warehouse/supplier — внутренние коды Radle, раскрывают,
                // ЧЕРЕЗ КОГО Radle реально закупает. Полная детализация —
                // только в supplier_name (рендерится лишь при
                // user_role===admin, см. protect-supplier-identity в памяти
                // агента), "rdl" вместо "radle" — короче, в одном стиле с
                // остальными короткими кодами поставщиков в этом файле
                // (atptr/vtzkp и т.п.). supplier_city — версия для всех
                // посетителей, сворачивает конкретный подкод до страны
                // (см. $radleCustomerCountryLabels выше).
                $adminLabel = 'rdl' . ($supplierCode !== '' ? ' ' . $supplierCode : ($warehouse !== '' ? ' ' . $warehouse : ''));
                $customerLabel = $radleCustomerCountryLabels[$supplierCode] ?? ($supplierCode !== '' ? $supplierCode : 'Radle');

                // Роман заметил живьём 2026-08-23: name иногда пустой или
                // просто скобки без текста внутри (напр. "()") — считаем
                // название осмысленным только если в нём есть хоть одна
                // буква или цифра, иначе показываем заглушку вместо пустой
                // или бессмысленной строки.
                $rawName = trim((string) ($part['name'] ?? ''));
                $name = preg_match('/[\p{L}\p{N}]/u', $rawName) === 1 ? $rawName : 'Запчасть';

                $entry = [
                    'brand'            => $part['manufacturer'] ?? '',
                    'article'          => $part['article'] ?? '',
                    'name'             => $name,
                    'price'            => $price,
                    'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                    'qty'              => (int) ($part['quantity'] ?? 0),
                    'delivery_time'    => $deliveryDate ?? $deliveryTimeText,
                    'deliveryStart'    => $deliveryDate,
                    // Radle — поставки из ОАЭ, международный возврат
                    // невозможен (по всем позициям, независимо от
                    // конкретного города-источника — Роман 2026-08-24).
                    'returnable'       => false,
                    'supplier_name'    => $adminLabel,
                    'supplier_city'    => $customerLabel,
                    'supplier_color'   => 'linear-gradient(135deg, #0f172a, #16a34a)',
                    'stocks'           => [[
                        'qty'              => (int) ($part['quantity'] ?? 0),
                        'price'            => $price,
                        'priceWithMargine' => round($this->setPrice($price), self::ROUND_LIMIT),
                        'delivery_time'    => $deliveryDate ?? $deliveryTimeText,
                        'supplier_city'    => $customerLabel,
                    ]],
                ];

                array_push($this->finalArr['brands'], $part['manufacturer'] ?? '');

                if ($isExact) {
                    array_push($this->finalArr['searchedNumber'], $entry);
                } else {
                    array_push($this->finalArr['crosses_to_order'], $entry);
                }
            }

            // По их же доке — "по ходовому артикулу 1000+ предложений" — та
            // же проблема раздутого ответа, что и у Автозакупа (см.
            // stratifyByPrice выше), только тут это не баг нестрогого
            // подбора, а нормальный документированный объём ответа.
            if (count($this->finalArr['crosses_to_order']) > self::PRICE_STRATIFY_BUCKETS * self::PRICE_STRATIFY_PER_BUCKET) {
                $this->finalArr['crosses_to_order'] = $this->stratifyByPrice(
                    $this->finalArr['crosses_to_order'],
                    self::PRICE_STRATIFY_BUCKETS,
                    self::PRICE_STRATIFY_PER_BUCKET
                );
            }

            // См. SEARCHED_NUMBER_RADLE_CAP — оставляем только самые дешёвые,
            // остальное не теряется полностью (Radle всё равно доступен
            // через crosses_to_order при неточном совпадении бренда), просто
            // не монополизирует первую страницу "Запрошенного артикула".
            if (count($this->finalArr['searchedNumber']) > self::SEARCHED_NUMBER_RADLE_CAP) {
                usort($this->finalArr['searchedNumber'], fn($a, $b) => ($a['priceWithMargine'] ?? 0) <=> ($b['priceWithMargine'] ?? 0));
                $this->finalArr['searchedNumber'] = array_slice($this->finalArr['searchedNumber'], 0, self::SEARCHED_NUMBER_RADLE_CAP);
            }

        } catch (\Exception $e) {
            \Log::error('Radle exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }
    }

    private function extractDaysFromText(string $text): int
    {
        preg_match('/(\d+)/', $text, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 999;
    }

    /**
     * Стратифицированная выборка по цене — не "самое дешёвое", а честный
     * срез по всему диапазону: от минимальной до максимальной цены В ЭТОМ
     * конкретном наборе (не хардкодим границы в тенге — у разных деталей
     * ценовой диапазон совсем разный), делим на $bucketsCount равных по
     * ширине бакетов, из каждого берём не больше $maxPerBucket штук
     * (внутри бакета — тоже от дешёвого к дорогому). Так и бюджетный, и
     * премиальный вариант остаются видны, просто без всех тысяч подряд.
     */
    private function stratifyByPrice(array $items, int $bucketsCount, int $maxPerBucket): array
    {
        if (empty($items)) {
            return $items;
        }

        $prices = array_map(fn($item) => (float) ($item['priceWithMargine'] ?? 0), $items);
        $min = min($prices);
        $max = max($prices);

        if ($max <= $min) {
            // Все примерно по одной цене — бакетить нечего, просто режем сверху.
            return array_slice($items, 0, $bucketsCount * $maxPerBucket);
        }

        $bucketWidth = ($max - $min) / $bucketsCount;
        $buckets = array_fill(0, $bucketsCount, []);

        foreach ($items as $item) {
            $price = (float) ($item['priceWithMargine'] ?? 0);
            $bucketIndex = (int) floor(($price - $min) / $bucketWidth);
            $bucketIndex = min($bucketIndex, $bucketsCount - 1); // самая дорогая позиция — в последний бакет
            $buckets[$bucketIndex][] = $item;
        }

        $result = [];
        foreach ($buckets as $bucketItems) {
            usort($bucketItems, fn($a, $b) => ($a['priceWithMargine'] ?? 0) <=> ($b['priceWithMargine'] ?? 0));
            $result = array_merge($result, array_slice($bucketItems, 0, $maxPerBucket));
        }

        return $result;
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
        
        if (Auth()->user() && Auth()->user()->user_role == 'common') {
            return $priceWithMargin;
        } else if(Auth()->user() && Auth()->user()->user_role == 'opt') {
            return $priceWithMargin - ($priceWithMargin * 0.07);
        } elseif(Auth()->user() && Auth()->user()->user_role == 'admin') {
            $priceWithMargin = SetPrice::setPriceForAdmin($price);
            return $priceWithMargin;
        } else {
            return $priceWithMargin;
        }
    }

    /**
     * Cache::lock() вокруг получения токена — раньше при параллельном
     * опросе поставщиков несколько запросов одновременно мимо холодного
     * кеша ломились логиниться на Shate-M разом ("cache stampede"), и если
     * у них на один ApiKey разрешена только одна активная сессия —
     * конкурентные логины инвалидировали токены друг друга, часть
     * запросов ловила протухший токен и Shatem "отваливался". Теперь
     * логин делает только один процесс, остальные ждут и берут готовый
     * токен из кеша.
     */
    private function getShatemToken()
    {
        $cached = cache()->get('shatem_token');
        if ($cached) {
            return $cached;
        }

        return Cache::lock('shatem_token_lock', 10)->block(5, function () {
            return cache()->remember('shatem_token', 3600, function () {
                $response = Http::asForm()->post('https://api.shate-m.kz/api/v1/auth/loginByapiKey', [
                    'ApiKey' => '{3f3b6eeb-709c-4dcb-be59-147ce8f9cb87}',
                ]);
                return $response->json()['access_token'] ?? null;
            });
        });
    }
} 