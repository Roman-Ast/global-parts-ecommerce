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
    @include('components.header-mini')
    @auth
    @else
    <div class="alert alert-warning" role="alert">
      Что оформить заказ, войдите или зарегестрируйтесь
    </div>
    @endauth
    <div id="cart-content-wrapper" class="container">
        <div id="cart-content-inner-wrapper">
            <div id="cart-header">
                <div class="cart-header-item" id="cart-header-name">
                    Корзина
                </div>
                <div class="cart-header-item" id="cart-header-sum">
                    @if (session()->has('cart'))
                        <div>{{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} Т</div>
                    @endif
                </div>
            </div>
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
              <div id="cart-pre-header">
                  <a href="cart/clear">
                      <button class="btn btn-danger" style="margin-right: 5px">Очистить</button>
                  </a>
                  @auth
                  <a href="#"><button class="btn btn-success" id="modal-show">Заказать</button></a>
                  @endauth
              </div>
              <div id="cart-content">
                  @if (session()->has('cart'))
                  <table class="table" id="cart-content-table">
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
                              @auth
                                <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                @if (auth()->user()->user_role == 'admin' || auth()->user()->user_role == 'pre_admin')
                                  <input type="tel" placeholder="телефон клиента" name="customer_phone" required>
                                @endif
                               @endauth
                              @foreach (session()->get('cart')->content() as $cartItem)
                                  <tr class="cart-item">
                                      <td><input type="hidden" name="stockFrom" value="$cartItem['stockFrom']">{{ $cartItem['stockFrom'] }}</td>
                                      <td>{{ $cartItem['brand'] }}</td>
                                      <td>{{ $cartItem['article'] }}</td>
                                      <td>{{ $cartItem['name'] }}</td>
                                      <td>{{ $cartItem['deliveryTime'] }}</td>
                                        @if (auth()->user())
                                          @if (auth()->user()->user_role == 'admin' || auth()->user()->user_role == 'pre_admin')
                                            <td>
                                              <input type="number" value="{{ $cartItem['priceWithMargine'] }}" min="0" class="form-control newPriceWithMargine" value="newPriceWithMargine">
                                            </td>
                                            @else
                                            <td><span>{{ $cartItem['priceWithMargine'] }}</span></td>
                                          @endif
                                        @else
                                          <td><span>{{ $cartItem['priceWithMargine'] }}</span></td>
                                        @endif
                                      <td class="cart-qty-change-container">
                                        <input type="number" class="form-control cart-qty-change" value="{{ $cartItem['qty'] }}" name="qty" min="1" step="1">
                                      </td>
                                      <td>{{ (int)$cartItem['qty'] * (int)$cartItem['priceWithMargine'] }}</td>
                                      <td class="cart-item-delete">
                                        <img src="images/dump-red-24.png" alt="cart-garbage">
                                      </td>
                                  </tr>
                                  
                              @endforeach
                              <input type="submit" id="order-btn-submit">
                          </form>
                      </tbody>
                    </table>
                  @endif
              </div>
            @else
            <div id="cart-pre-header">
              <span style="font-style: italic;">Ваша корзина пуста...</span>
            </div>
            @endif
        </div>
    </div>
</div>
  @include('components.footer-bar-mini')
  @include('components.footer')
@endsection