@extends('layouts.app')

@section('title', 'Заказы')
   
@section('content')

@include('components.header')

<div id="orders-content-wrapper" class="container">
    @foreach ($orders as $orderItem)
    <div class="order-item-wrapper">
        <div class="order-item-header">
           <div class="order-item-id">
                0000{{ $orderItem->id }}
           </div>
           <div class="order-item-user-name">
                {{ $orderItem->user->name }}
           </div>
           <div class="order-item-status">
                {{ $orderItem->status }} <img src="/images/clock-wait-16.png">
           </div>
           <div class="order-item-date">
                {{ $orderItem->date }}
           </div>
           <div class="order-item-time">
                {{ $orderItem->time }}
           </div>
           <div class="order-item-sum">
            {{ number_format($orderItem->sum, 2, ',', ' ') }}
       </div>
        </div>
        <div class="order-item-products-wrapper">
            <div class="order-item-products-content-header">
                <div class="products-content-header-item">Партномер</div>
                <div class="products-content-header-item">Артикул</div>
                <div class="products-content-header-item">Брэнд</div>
                <div class="products-content-header-item">Наименование</div>
                <div class="products-content-header-item">Кол-во</div>
                <div class="products-content-header-item">Цена</div>
                <div class="products-content-header-item">Сумма</div>
                <div class="products-content-header-item">Склад</div>
                <div class="products-content-header-item">Доставка</div>
            </div>
            @foreach ($orderItem->products as $product)
            <div class="order-item-products-content">
                <div class="order-products-searched_number">
                    {{ $product->searched_number }}
                </div>
                <div class="order-products-article">
                    {{ $product->article }}
                </div>
                <div class="order-products-brand">
                    {{ $product->brand }}
                </div>
                <div class="order-products-name">
                    {{ mb_strimwidth($product->name, 0, 50, '...') }}
                </div>
                <div class="order-products-qty">
                    {{ $product->qty }}
                </div>
                <div class="order-products-price">
                    {{ number_format($product->price, 0, ',', ' ') }}
                </div>
                <div class="order-products-item_sum">
                    {{ number_format($product->item_sum, 0, ',', ' ') }}
                </div>
                <div class="order-products-fromStock">
                    {{ $product->fromStock }}
                </div>
                <div class="order-products-deliveryTime">
                    {{ $product->deliveryTime }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @endforeach

</div>

@include('components.footer')
@endsection