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
                Предложения для {{ $searchRes[0]['partnumber'] }}
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
                @foreach ($searchRes as $searchResItem)
                    <div class="requestPartNumberContainer-item">
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-brand">
                            {{ $searchResItem['brand'] }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-partnumber">
                            {{ $searchResItem['partnumber'] }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-name">
                            {{ $searchResItem['name'] }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-info">
                            info
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-delivery">
                            {{ $searchResItem['delivery']  }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-count">
                            {{ $searchResItem['count']  }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-price">
                            {{ $searchResItem['price'] }}
                        </div>
                        <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-cart">
                            Купить
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги
                </div>
            </div>
            <div id="crossesContainer">
                @foreach ($crosses as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-brand">
                        {{ $crossItem['brand'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-partnumber">
                        {{ $crossItem['partnumber'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-name">
                        {{ $crossItem['name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity" id="requestPartNumber-info">
                        info
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-delivery">
                        @foreach ($crossItem['stocks'] as $stockItem)
                        
                            <div class="stock-item">
                                {{ $stockItem['delivery'] }}
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable" id="requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item">
                                2
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