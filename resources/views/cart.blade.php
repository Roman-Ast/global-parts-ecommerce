@extends('layouts.app')

@section('title', 'Корзина')
   
@section('content')


<div id="search-catalog-main-container" class="container">
    @include('components.header')
    
    <div id="cart-content-wrapper" class="container">
        <div id="cart-content-inner-wrapper">
            <div id="cart-header">
                <div class="cart-header-item" id="cart-header-name">
                    Корзина
                </div>
                <div class="cart-header-item" id="cart-header-sum">
                    @if (session()->has('cart'))
                        <div>{{ number_format(session()->get('cart')->total(), 2, '.', ' ') }} Т</div>
                    @endif
                </div>
            </div>
            <div id="cart-pre-header">
    
            </div>
            <div id="cart-content">
                @if (session()->has('cart'))
                <table class="table">
                    <thead>
                      <tr>
                        <th scope="col">#</th>
                        <th scope="col">Бренд</th>
                        <th scope="col">Артикул</th>
                        <th scope="col">Наименование</th>
                        <th scope="col">Дата поставки</th>
                        <th scope="col">Цена</th>
                        <th scope="col">Кол-во</th>
                        <th scope="col">Сумма</th>
                      </tr>
                    </thead>
                    <tbody>
                        @foreach (session()->get('cart')->content() as $cartItem)
                            <tr class="">
                                <th scope="row">1</th>
                                <td>{{ $cartItem['brand'] }}</td>
                                <td>{{ $cartItem['article'] }}</td>
                                <td>{{ $cartItem['name'] }}</td>
                                <td>{{ $cartItem['deliveryTime'] }}</td>
                                <td>{{ $cartItem['price'] }}</td>
                                <td><input type="number" class="form-control" value="{{ $cartItem['qty'] }}"></td>
                                <td>{{ (int)$cartItem['qty'] * (int)$cartItem['price'] }}</td>
                                <td class="cart-item-delete">&times;</td>
                            </tr>
                        @endforeach
                    </tbody>
                  </table>
                    
                @endif
            </div>
        </div>
    </div>

    
@endsection