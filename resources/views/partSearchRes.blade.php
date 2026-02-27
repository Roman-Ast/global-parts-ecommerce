@extends('layouts.app')

@section('title', 'Результат поиска')
   
@section('content')

<div id="search-res-main-container" class="container">
    @include('components.header')
    @include('components.header-mini')

    <div id="curtain-grey-searchpartres"></div>

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
                <div>Предложения для <span id="search-res-header-val">{{ $chosenBrand}} {{ $finalArr['originNumber'] }}</span></div>
                @auth
                @if(auth()->user()->user_role == "admin")
                    <div id="articles-hide-wrapper">
                        <i>скрыть артикула</i> <input type="checkbox" id="articles-hide">
                    </div>
                @endif
                @endauth
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
            
            <div id="requestPartNumberContainer">
                <input type="hidden" value="{{ $finalArr['originNumber'] }}" id="originNumber">
                @if (count($finalArr['searchedNumber']) > 0)
                    @foreach ($finalArr['searchedNumber'] as $searchItem)
                        <div class="requestPartNumberContainer-item">
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                                @auth
                                    @if (auth()->user()->user_role == "admin")
                                        {{ $searchItem['supplier_name'] }}
                                    @else
                                        {{ $searchItem['supplier_city'] }}
                                    @endif
                                @else
                                {{ $searchItem['supplier_city'] }}
                                @endauth
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
                                @if(array_key_exists('info',$searchItem))
                                    <img src="/images/info_pic.png" alt="info">
                                @else
                                    <img src="/images/info_unavailable.png" alt="info">
                                @endif
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-delivery">
                                @if (date('d.m.y',strtotime($searchItem['deliveryStart'])) == date('d.m.y'))
                                    <div class="parts-on-stock">1.5-2 часа</div>
                                @elseif($searchItem['deliveryStart'] == 'в офисе')
                                    <div style="background-color:{{ $searchItem['supplier_color']}};color:#111">{{ $searchItem['deliveryStart'] }}</div>
                                @elseif($searchItem['deliveryStart'] == '1 день')
                                    <div style="background-color:{{ $searchItem['supplier_color'] }};color:#fff">
                                    {{ $searchItem['deliveryStart'] }}
                                    </div>
                                @else
                                    {{ date('d.m.y',strtotime($searchItem['deliveryStart'])) }}
                                @endif
                            </div>
                            <div class="requestPartNumberContainer-item-entity requestPartNumber-count">
                                {{ $searchItem['qty']  }}
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
                                    <input type="hidden" value="{{ $searchItem['price'] }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
                <div id="show-other-items" counter="10">
                    <a href="###">Показать еще 10</a>
                </div>
            </div>
            @endif

            @if (!empty($finalArr['crosses_in_office']))

            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги в наличии в офисе
                </div>
            </div>

            <div id="crossesContainer-on-stock">
                @foreach ($finalArr['crosses_in_office'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth
                            @if (auth()->user()->user_role == "admin")
                                {{ $crossItem['supplier_name'] }}
                            @else
                                {{ $crossItem['supplier_city'] }}
                            @endif
                        @else
                        {{ $crossItem['supplier_city'] }}
                        @endauth
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
                        @if(array_key_exists('info',$crossItem))
                            <img src="/images/info_pic.png" alt="info">
                        @else
                            <img src="/images/info_unavailable.png" alt="info">
                        @endif
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery" style="background-color:{{ $crossItem['supplier_color']}};color:#111">
                        {{ $crossItem['delivery_time'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">
                            @if ($crossItem['qty'] > 10)
                                >10
                            @else
                                {{ $crossItem['qty'] }}
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
                            <input type="hidden" value="{{ $crossItem['price'] }}">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @endif

            
            @if (!empty($finalArr['crosses_on_stock']))

            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги в наличии на складе
                </div>
            </div>

            <div id="crossesContainer-on-stock">
                @foreach ($finalArr['crosses_on_stock'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth
                            @if (auth()->user()->user_role == "admin")
                                {{ $crossItem['supplier_name'] }}
                            @else
                                {{ $crossItem['supplier_city'] }}
                            @endif
                        @else
                        {{ $crossItem['supplier_city'] }}
                        @endauth
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
                        
                        @if (array_key_exists('info', $crossItem))
                            <img src="/images/info_pic.png" alt="info" class="spare-part-info-show">

                            <div class="info-block">
                                <div class="block-info-close-wrapper">
                                    <button type="button" class="btn-close block-info-item-close" aria-label="Close"></button>
                                </div>
                                <div class="info-block-pictures">
                                        <div class="info-block-pictures-name">
                                            <div class="info-block-pictures-name-header">
                                                {{ $crossItem['name'] }}
                                            </div>
                                            <div class="info-block-pictures-name-brand">
                                                <span style="color:#bbb"> Брэнд: </span> {{ $crossItem['brand'] }}
                                            </div>
                                            <div class="info-block-pictures-name-article">
                                                <span style="color:#bbb"> Артикул: </span> {{ $crossItem['article'] }}
                                            </div>
                                        </div>
                                        <div id="carouselExampleControls-{{ $crossItem['article'] }}" class="carousel slide carouselExampleControls" data-bs-ride="carousel">
                                        <div class="carousel-inner">
                                            @if (!empty($crossItem['info']['pictures']))
                                                @foreach($crossItem['info']['pictures'] as $pic_number => $picture_address)
                                                    @if($pic_number == 0)
                                                        <div class="carousel-item active">
                                                            <img src="{{ $picture_address }}" class="carousel-item-img" alt="sparepart-picture">
                                                        </div>
                                                    @else
                                                        <div class="carousel-item">
                                                            <img src="{{ $picture_address }}" class="carousel-item-img" alt="sparepart-picture">
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleControls-{{ $crossItem['article'] }}" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Previous</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleControls-{{ $crossItem['article'] }}" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Next</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="info-block-information">
                                    <!-- NAV TABS -->
                                    <ul class="nav nav-tabs" id="productTabs-{{ $crossItem['article'] }}" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active"
                                                    id="description-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#description-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Описание
                                            </button>
                                        </li>

                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link"
                                                    id="original-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#original-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Оригинальные номера
                                            </button>
                                        </li>

                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link"
                                                    id="usage-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#usage-{{ $crossItem['article'] }}"
                                                    type="button"
                                                    role="tab">
                                                Применение в автомобилях
                                            </button>
                                        </li>
                                    </ul>

                                    <!-- TAB CONTENT -->
                                    <div class="tab-content mt-3" id="productTabsContent-{{ $crossItem['article'] }}" class="productTabsContent">

                                        <div class="tab-pane fade show active info-description"
                                            id="description-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                            <ul class="info-description-sizes">
                                                <li>
                                                    <b>Размеры</b>
                                                </li>
                                                <li>Ширина: {{ $crossItem['info']['params']['sizes']['width'] }}</li>
                                                <li>Высота: {{ $crossItem['info']['params']['sizes']['height'] }}</li>
                                                <li>Толщина: {{ $crossItem['info']['params']['sizes']['depth'] }}</li>
                                            </ul>
                                        </div>

                                        <div class="tab-pane fade info-oem-numbers"
                                            id="original-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                                @foreach($crossItem['info']['params']['OEM'] as $oem_number)
                                                    {{ $oem_number }}
                                                @endforeach
                                        </div>

                                        <div class="tab-pane fade"
                                            id="usage-{{ $crossItem['article'] }}"
                                            role="tabpanel">
                                            <p>
                                                
                                            </p>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @else
                            <img src="/images/info_unavailable.png" alt="info">
                        @endif
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery parts-on-stock">
                        {{ $crossItem['delivery_time'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">
                            {{ $crossItem['qty'] }}
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
                            <input type="hidden" value="{{ $crossItem['price'] }}">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @endif

            @if (count($finalArr['crosses_to_order']) > 0)
            
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">
                    Аналоги на заказ
                </div>
            </div>
            
            <div id="crossesContainer-to-order">
                @foreach ($finalArr['crosses_to_order'] as $index => $crossItem)
                <div class="requestPartNumberContainer-item">
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth
                            @if (auth()->user()->user_role == "admin")
                                {{ $crossItem['supplier_name'] }}
                            @else
                                {{ $crossItem['supplier_city'] }}
                            @endif
                        @else
                        {{ $crossItem['supplier_city'] }}
                        @endauth
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
                        @if(array_key_exists('info',$crossItem))
                            <img src="/images/info_pic.png" alt="info">
                        @else
                            <img src="/images/info_unavailable.png" alt="info">
                        @endif
                    </div>
                        <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery">
                            {{ date('d.m.y',strtotime($crossItem['delivery_time'])) }}
                        </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-qty">
                                {{ $stockItem['qty'] }}
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
                                <input type="hidden" value="{{ $crossItem['price'] }}">
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            @endif
            <nav aria-label="..." class="pagination-nav">
                <ul class="pagination pagination-sm">
                    <li class="page-item active">
                        <span class="page-link" page-num="1">1</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="2">2</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="3">3</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="4">4</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="5">5</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="6">6</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="7">7</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="8">8</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="9">9</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="10">10</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="11">11</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="12">12</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="13">13</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="14">14</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="15">15</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="16">16</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="17">17</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="18">18</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="19">19</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="20">20</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="21">21</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="22">22</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="23">23</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="24">24</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="25">25</span>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

@include('components.footer-bar-mini')
@include('components.footer')
@endsection