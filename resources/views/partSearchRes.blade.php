@extends('layouts.app')

@section('title', 'Результат поиска')
   
@section('content')


<div id="search-res-main-container" class="container" class="container">
    @include('components.header')

    <div id="search-res-main-wrapper">
        <div id="search-res-filter">

        </div>
    
        <div id="search-part-main-container">
            <div id="search-res-header">
                
            </div>
            <div id="search-res-part-header">
                <div class="search-res-part-header-item">
                    Наименование
                </div>
                <div class="search-res-part-header-item">
                    Срок поставки
                </div>
                <div class="search-res-part-header-item">
                    Количество
                </div>
                <div class="search-res-part-header-item" style="text-align: center;">
                    Цена
                </div>
            </div>
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    ЗАПРОШЕННЫЙ АРТИКУЛ
                </div>
            </div>
            <div id="requestPartNumberContainer">
                @if (count($finalArr['searchedNumber']) > 0)
                    @foreach ($finalArr['searchedNumber'] as $searchItem)
                        <div class="requestPartNumberContainer-item">
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-brand">
                                {{ $searchItem['brand'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-partnumber">
                                {{ $searchItem['article'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-name">
                                {{ $searchItem['name'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-info">
                                info
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-delivery">
                                {{ $searchItem['deliveryStart']  }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-count">
                                {{ $searchItem['stocks']  }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-price">
                                {{ $searchItem['price'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-cart">
                                Купить
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги
                </div>
            </div>
            <div id="crossesContainer">
                @foreach ($finalArr['crosses'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-brand">
                        {{ $crossItem['brand'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-partnumber">
                        {{ $crossItem['article'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-name">
                        {{ $crossItem['name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-info">
                        info
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-delivery">
                        
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item">
                                @foreach ($stockItem['stocks'] as $item)
                                    
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-price">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item">
                                2565
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item">
                                Купить
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    
</div>
@endsection