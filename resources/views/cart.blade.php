@extends('layouts.app')

@section('title', 'Корзина')
   
@section('content')


<div id="search-catalog-main-container" class="container">
    <div id="cart-shadow"></div>
    <div id="order-confirmation-form" class="container">
        <div class="cart-modal-window" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Оформление заказа</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                  <p>Пожалуйста, подтвердите оформление заказа...</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary modal-close" data-bs-dismiss="modal">Отмена</button>
                  <button type="button" class="btn btn-primary" id="order-confirm">Оформить заказ</button>
                </div>
              </div>
            </div>
          </div>
    </div>

    @include('components.header')
    
    <div id="cart-content-wrapper" class="container">
        <div id="cart-content-inner-wrapper">
            <div id="cart-header">
                <div class="cart-header-item" id="cart-header-name">
                    Корзина
                </div>
                <div class="cart-header-item" id="cart-header-sum">
                    @if (session()->has('cart'))
                        <div>{{ number_format(session()->get('cart')->total(), 0, '.', ' ') }} Т</div>
                    @endif
                </div>
            </div>
            <div id="cart-pre-header">
                <a href="cart/clear">
                    <button class="btn btn-danger" style="margin-right: 5px">Очистить</button>
                </a>
                <a href="#"><button class="btn btn-success" id="modal-show">Заказать</button></a>
            </div>
            <div id="cart-content">
                @if (session()->has('cart'))
                <table class="table" id="cart-content">
                    <thead>
                      <tr>
                        <th scope="col">Склад</th>
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
                        <form action="/makeorder" method="POST" id="make-order-form">
                          @csrf
                            <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                            @foreach (session()->get('cart')->content() as $cartItem)
                                <tr class="cart-item">
                                    <td><input type="hidden" name="stockFrom" value="$cartItem['stockFrom']">{{ $cartItem['stockFrom'] }}</td>
                                    <td>{{ $cartItem['brand'] }}</td>
                                    <td>{{ $cartItem['article'] }}</td>
                                    <td>{{ $cartItem['name'] }}</td>
                                    <td>{{ $cartItem['deliveryTime'] }}</td>
                                    <td>{{ $cartItem['price'] }}</td>
                                    <td><input type="number" class="form-control cart-qty-change" value="{{ $cartItem['qty'] }}" name="qty"></td>
                                    <td>{{ (int)$cartItem['qty'] * (int)$cartItem['price'] }}</td>
                                    <td class="cart-item-delete">&times;</td>
                                </tr>
                            @endforeach
                            <input type="submit" id="order-btn-submit">
                        </form>
                    </tbody>
                  </table>
                    
                @endif
            </div>
        </div>
    </div>

    
@endsection