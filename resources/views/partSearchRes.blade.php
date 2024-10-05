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
                Предложения для {{ $searchedPartNumber }}   
            </div>
            <div id="search-res-part-header">
                <div class="search-res-part-header-item">
                    Наименование
                </div>
                <div class="search-res-part-header-item">
                    Срок поставки
                </div>
                <div class="search-res-part-header-item">
                    Кол-во
                </div>
                <div class="search-res-part-header-item" style="text-align: center;">
                    Цена
                </div>
            </div>
            @if (count($finalArr['searchedNumber']) > 0)
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Запрошенный артикул
                </div>
            </div>
            @endif
            <div id="requestPartNumberContainer">
                @if (count($finalArr['searchedNumber']) > 0)
                    @foreach ($finalArr['searchedNumber'] as $searchItem)
                        <div class="requestPartNumberContainer-item">
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                                {{ $searchItem['supplier_name'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">
                                {{ $searchItem['brand'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">
                                {{ $searchItem['article'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-name">
                                {{ $searchItem['name'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                                <img src="/images/info_pic.png" alt="info">
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-delivery">
                                {{ date('d.m.y',strtotime($searchItem['deliveryStart']))  }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-count">
                                {{ $searchItem['stocks']  }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-price stock-item-price">
                                {{ $searchItem['price'] }}тг.
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-cart">
                                <div class="stock-item-cart">
                                    <div class="stock-item-cart-btn">
                                        <img src="/images/cart_pic_20.png" alt="cart">
                                    </div>
                                    <div class="stock-item-cart-qty">
                                        <input type='number' value="1" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги в наличии на складе
                </div>
            </div>
            <div id="crossesContainer">
                @foreach ($finalArr['crosses_on_stock'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        {{ $crossItem['supplier_name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">
                        {{ $crossItem['brand'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">
                        {{ $crossItem['article'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">
                        {{ $crossItem['name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        <img src="/images/info_pic.png" alt="info">
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery parts-on-stock">
                        {{ $crossItem['delivery_time'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-qty">
                                @if ($stockItem['qty'] > 10)
                                    >10
                                @else
                                    {{ $stockItem['qty'] }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-price">
                                {{ $crossItem['price'] }}тг.
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn">
                                    <img src="/images/cart_pic_20.png" alt="cart">
                                </div>
                                <div class="stock-item-cart-qty">
                                    <input type='number' value="1" class="form-control">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @if (count($finalArr['crosses_to_order']) > 0)
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги на заказ
                </div>
            </div>
            @endif
            <div id="crossesContainer">
                @foreach ($finalArr['crosses_to_order'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        {{ $crossItem['supplier_name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">
                        {{ $crossItem['brand'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">
                        {{ $crossItem['article'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">
                        {{ $crossItem['name'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        <img src="/images/info_pic.png" alt="info">
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery">
                        {{ date('d.m.y',strtotime($crossItem['delivery_time'])) }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-qty">
                                @if ($stockItem['qty'] > 10)
                                    >10
                                @else
                                    {{ $stockItem['qty'] }}
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-price">
                                {{ $crossItem['price'] }}тг.
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn">
                                    <img src="/images/cart_pic_20.png" alt="cart">
                                </div>
                                <div class="stock-item-cart-qty">
                                    <input type='number' value="1" class="form-control">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>


@include('components.footer')
@endsection