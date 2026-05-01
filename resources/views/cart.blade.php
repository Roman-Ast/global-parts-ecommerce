@extends('layouts.app')

@section('title', 'Корзина — Global Parts')

@section('content')
<div class="main-wrapper d-flex flex-column bg-light" style="min-height: 100vh; padding-bottom: 150px;">
    
    {{-- 1. УПРАВЛЕНИЕ ХЕДЕРАМИ И ОТСТУПАМИ --}}
    <div class="d-none d-md-block">
        @include('components.header')
        <div style="height: 170px;"></div> {{-- Отступ для десктопа --}}
    </div>

    <div class="d-md-none">
        <div style="position: fixed; top: 0; width: 100%; z-index: 1050; background: #fff;">
            @include('components.header-mini')
        </div>
        <div style="height: 200px;"></div> {{-- Отступ для мобилки, чтобы "Корзина" вылезла --}}
    </div>

    {{-- 2. ОСНОВНОЙ КОНТЕНТ --}}
    <div class="container flex-grow-1 pb-5">
        
        {{-- Заголовок страницы --}}
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-2">
            <h1 class="h3 fw-bold mb-0 text-dark">
                <i class="fas fa-shopping-cart text-primary me-2"></i>Корзина
            </h1>
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
                <div class="h5 fw-bold text-dark mb-0 bg-white p-2 px-3 rounded-3 shadow-sm d-md-none text-center border-start border-4 border-primary">
                    Итого: <span class="text-primary cart-total-display">{{ number_format(session()->get('cart')->totalWithMargine(), 0, '', ' ') }} ₸</span>
                </div>
            @endif
        </div>

        @if (session()->has('cart') && session()->get('cart')->count() != 0)
            <div class="row g-4">
                {{-- ЛЕВАЯ КОЛОНКА --}}
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-body p-0">
                            
                            {{-- ДЕКСТОПНАЯ ТАБЛИЦА --}}
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-dark text-white">
                                        <tr class="small text-uppercase">
                                            <th class="ps-4 py-3">Товар</th>
                                            <th class="text-center">Цена</th>
                                            <th class="text-center" style="width: 120px;">Кол-во</th>
                                            <th class="text-center">Сумма</th>
                                            <th class="pe-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (session()->get('cart')->content() as $key => $cartItem)
                                            <tr class="cart-item-row">
                                              <td class="ps-4 py-3">
                                                  <div class="fw-bold text-primary">{{ $cartItem['brand'] }}</div>
                                                  <div class="small fw-bold text-dark">{{ $cartItem['article'] }}</div>
                                                  <div class="extra-small text-muted" style="font-size: 0.75rem;">{{ $cartItem['name'] }}</div>
                                              </td>
                                              <td class="text-center fw-bold">{{ number_format($cartItem['priceWithMargine'], 0, '', ' ') }} ₸</td>
                                              <td class="text-center">
                                                  <input type="number" class="form-control form-control-sm text-center cart-qty-change" 
                                                        value="{{ $cartItem['qty'] }}" 
                                                        data-article="{{ $cartItem['article'] }}" 
                                                        data-price="{{ (int)$cartItem['priceWithMargine'] }}"
                                                        min="1">
                                              </td>
                                              <td class="text-center fw-bold text-primary item-subtotal-display">
                                                  {{ number_format((int)$cartItem['qty'] * (int)$cartItem['priceWithMargine'], 0, '', ' ') }} ₸
                                              </td>
                                              <td class="pe-4 text-end">
                                                  <button class="btn btn-link text-danger p-0 cart-item-delete" data-article="{{ $cartItem['article'] }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                              </td>
                                          </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- МОБИЛЬНЫЕ КАРТОЧКИ --}}
                            <div class="d-md-none">
                                @foreach (session()->get('cart')->content() as $cartItem)
                                    <div class="p-3 border-bottom position-relative bg-white cart-item-row">
                                        <div class="fw-bold text-primary mb-1">{{ $cartItem['brand'] }} | {{ $cartItem['article'] }}</div>
                                        <div class="small text-muted mb-2 lh-sm" style="font-size: 0.85rem;">{{ $cartItem['name'] }}</div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-2 bg-light p-3 rounded-3 border">
                                            <div>
                                                <div class="extra-small text-muted mb-1" style="font-size: 0.7rem;">Кол-во:</div>
                                                <input type="number" class="form-control form-control-sm text-center cart-qty-change fw-bold" 
                                                       value="{{ $cartItem['qty'] }}" 
                                                       data-article="{{ $cartItem['article'] }}" 
                                                       data-price="{{ (int)$cartItem['priceWithMargine'] }}"
                                                       style="width: 80px; height: 35px;">
                                            </div>
                                            <div class="text-end">
                                                <div class="small text-muted mb-1">{{ number_format($cartItem['priceWithMargine'], 0, '', ' ') }} ₸/шт.</div>
                                                <div class="fw-bold text-primary h6 mb-0 item-subtotal-display">
                                                    {{ number_format((int)$cartItem['qty'] * (int)$cartItem['priceWithMargine'], 0, '', ' ') }} ₸
                                                </div>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm border-0 position-absolute top-0 end-0 m-2 cart-item-delete" data-article="{{ $cartItem['article'] }}">
                                            <i class="fas fa-times fa-lg"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="/cart/clear" class="btn btn-link text-muted btn-sm text-decoration-none p-0">
                            <i class="fas fa-eraser me-1"></i> Очистить корзину
                        </a>
                    </div>
                </div>

                {{-- ПРАВАЯ КОЛОНКА ОФОРМЛЕНИЯ --}}
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 sticky-top mb-5" style="top: 130px;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Оформление заказа</h5>
                            <div class="bg-light p-3 rounded-3 mb-4 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Позиций:</span>
                                    <span class="fw-bold">{{ session()->get('cart')->count() }}</span>
                                </div>
                                <div class="d-flex justify-content-between border-top pt-2">
                                    <span class="fw-bold">К оплате:</span>
                                    <span class="h4 fw-bold text-primary mb-0 cart-total-display" id="cart-total-checkout">
                                        {{ number_format(session()->get('cart')->totalWithMargine(), 0, '', ' ') }} ₸
                                    </span>
                                </div>
                            </div>
                            <form action="/makeorder" method="POST" id="make-order-form">
                                @csrf
                                @auth
                                    <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                    <a href="{{ route('checkout') }}" class="col-lg-4 btn btn-primary btn-lg w-100 rounded-pill shadow-sm py-3 fw-bold">
                                        Перейти к оформлению
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="btn btn-dark btn-lg w-100 rounded-pill py-3 fw-bold">Войти</a>
                                @endauth
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ПУСТАЯ КОРЗИНА --}}
            <div class="text-center py-5 bg-white rounded-4 shadow-sm my-5 border">
                <i class="fas fa-shopping-basket fa-4x text-light mb-4"></i>
                <h4 class="fw-bold">Ваша корзина пуста</h4>
                <a href="/" class="btn btn-primary rounded-pill px-5 mt-3 shadow-sm fw-bold">На главную</a>
            </div>
        @endif
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>

{{-- МОДАЛКА --}}
<div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered px-3">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body py-5 text-center">
                @if(session()->has('cart') && is_object(session()->get('cart')))
                    <p class="mb-2 text-muted">Сумма к оплате:</p>
                    <span class="fw-bold text-primary h3 d-block cart-total-display">
                        {{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} ₸
                    </span>
                    <div class="d-flex gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-4 flex-grow-1" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 flex-grow-1 fw-bold" onclick="document.getElementById('make-order-form').submit();">
                            Подтверждаю
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .sticky-top { z-index: 100 !important; }
</style>
@endsection