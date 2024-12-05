@extends('layouts.app')

@section('title', 'Заказы')
   
@section('content')

@include('components.header')

@if (session()->has('message'))
    <div class="alert {{ Session::get('class') }}" style="align-text:center;">
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>    
@endif

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
            {{ number_format($orderItem->sum_with_margine, 2, ',', ' ') }}
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
                <div class="products-content-header-item">Статус</div>
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
                    {{ number_format($product->priceWithMargine, 0, ',', ' ') }}
                </div>
                <div class="order-products-item_sum">
                    {{ number_format($product->itemSumWithMargine, 0, ',', ' ') }}
                </div>
                <div class="order-products-fromStock">
                    {{ $product->fromStock }}
                </div>
                <div class="order-products-deliveryTime">
                    {{ $product->deliveryTime }}
                </div>
                <div class="order-products-status">
                    @if ($product->status == 'payment_waiting' )
                        <div class="payment_waiting">ожидание оплаты</div>
                    @elseif($product->status == 'processing' )
                        <div class="processing">принят в работу</div>
                    @elseif($product->status == 'supplier_refusal' )
                        <div class="supplier_refusal">отказ поставщика</div>
                    @elseif($product->status == 'arrived_at_the_point_of_delivery' )
                        <div class="arrived_at_the_point_of_delivery">поступил в ПВЗ</div>
                    @elseif($product->status == 'issued' )
                        <div class="issued">выдано</div>
                    @elseif($product->status == 'returned' )
                        <div class="returned">возвращено</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @endforeach

</div>

@include('components.footer')
@endsection