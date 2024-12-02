@extends('layouts.app')

@section('title', 'Результат поиска')
   
@section('content')

<div id="search-res-main-container" class="container">
    @include('components.header')

    <div id="search-res-main-wrapper">
        <div id="search-res-filter">
            <div class="search-res-filter-item" id="filter-brands">
                <div class="search-res-filter-item-header">
                    БРЕНД
                </div>
                <div class="search-res-filter-item-content">
                    <ul>
                        <li>
                            @foreach ($brands as $brand)
                                <div class="form-check">
                                    <input class="form-check-input brand-filter" type="checkbox" value="{{ $brand }}" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault" class="filter-brand-name">
                                        {{ $brand }}
                                    </label>
                                </div>
                            @endforeach
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    
        <div id="search-part-main-container">
            <div id="search-res-header">
                Предложения для "{{ $finalArr['originNumber'] }}" 
            </div>
            <div id="search-res-part-header">
                <div class="search-res-part-header-item">
                    Наименование
                </div>
                <div class="search-res-part-header-item">
                    Доставка
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
                <input type="hidden" value="{{ $finalArr['originNumber'] }}" id="originNumber">
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
                                {{ $searchItem['priceWithMargine'] }}
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-cart">
                                <div class="stock-item-cart">
                                    <div class="stock-item-cart-btn">
                                        <img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img">
                                    </div>
                                    <div class="stock-item-cart-qty">
                                        <input type='number' value="1" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
                <div id="show-other-items" counter="10">
                    <a href="###">Показать еще 10</a>
                </div>
            </div>
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги в наличии на складе
                </div>
            </div>
            <div id="crossesContainer-on-stock">
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
                        <div class="stock-item stock-item-qty">
                            @if ($crossItem['stocks'] > 10)
                                >10
                            @else
                                {{ $crossItem['stocks'] }}
                            @endif
                        </div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        <div class="stock-item stock-item-price">
                            {{ $crossItem['priceWithMargine'] }}
                        </div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                       
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn">
                                    <img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img">
                                </div>
                                <div class="stock-item-cart-qty">
                                    <input type='number' value="1" class="form-control">
                                </div>
                            </div>
                        
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
            <div id="crossesContainer-to-order">
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
                                {{ $crossItem['priceWithMargine'] }}
                            </div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn">
                                    <img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img">
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
            <nav aria-label="..." class="pagination-nav">
                <ul class="pagination pagination-sm">
                    <li class="page-item active">
                        <a class="page-link" page-num="1" href="###">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="2" href="###">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="3" href="###">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="4" href="###">4</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="5" href="###">5</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="6" href="###">6</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="7" href="###">7</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="8" href="###">8</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="9" href="###">9</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="10" href="###">10</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="11" href="###">11</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="12" href="###">12</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="13" href="###">13</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="14" href="###">14</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="15" href="###">15</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="16" href="###">16</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="17" href="###">17</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="18" href="###">18</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="19" href="###">19</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="20" href="###">20</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="21" href="###">21</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="22" href="###">22</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="23" href="###">23</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="24" href="###">24</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" page-num="25" href="###">25</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>


@include('components.footer')
@endsection