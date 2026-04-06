@extends('layouts.app')

@section('title', 'Корзина — Global Parts')

@section('content')
<div class="main-wrapper d-flex flex-column bg-light" style="min-height: 100vh; padding-top: 100px;">
    
    @include('components.header')
    @include('components.header-mini')

    <div class="container my-5 flex-grow-1">
        {{-- Заголовок страницы --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 fw-bold mb-0">
                <i class="fas fa-shopping-cart text-primary me-2"></i>Корзина
            </h1>
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
                <div class="h4 fw-bold text-dark mb-0">
                    Итого: <span class="text-primary">{{ number_format(session()->get('cart')->totalWithMargine(), 0, '', ' ') }} ₸</span>
                </div>
            @endif
        </div>

        @guest
            <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-3 fa-lg"></i>
                <div>
                    Чтобы оформить заказ, пожалуйста, <a href="/login" class="fw-bold">войдите</a> или <a href="/register" class="fw-bold">зарегистрируйтесь</a>.
                </div>
            </div>
        @endguest

        @if (session()->has('cart') && session()->get('cart')->count() != 0)
            <div class="row g-4">
                {{-- Список товаров --}}
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-body p-0">
                            {{-- Десктопная таблица (скрыта на мобилках) --}}
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-dark text-white">
                                        <tr class="small text-uppercase">
                                            <th class="ps-4 py-3">Товар</th>
                                            <th class="text-center">Срок</th>
                                            <th class="text-center">Цена</th>
                                            <th class="text-center" style="width: 120px;">Кол-во</th>
                                            <th class="text-center">Сумма</th>
                                            <th class="pe-4"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (session()->get('cart')->content() as $key => $cartItem)
                                            <tr>
                                              <td class="ps-4 py-3">
                                                  {{-- ... тут бренд и название ... --}}
                                                  <div class="fw-bold text-primary">{{ $cartItem['brand'] }}</div>
                                                  <div class="small fw-bold text-dark">{{ $cartItem['article'] }}</div>
                                              </td>
                                              <td class="text-center small">{{ $cartItem['deliveryTime'] }}</td>
                                              <td class="text-center fw-bold">
                                                  @if(auth()->check() && (auth()->user()->user_role == 'admin' || auth()->user()->user_role == 'pre_admin'))
                                                      {{-- Класс newPriceWithMargine важен для JS --}}
                                                      <input type="number" value="{{ $cartItem['priceWithMargine'] }}" class="form-control form-control-sm text-center mx-auto newPriceWithMargine" style="max-width: 90px;">
                                                  @else
                                                      {{-- Оборачиваем в span, чтобы JS легко нашел цену --}}
                                                      <span>{{ number_format($cartItem['priceWithMargine'], 0, '', ' ') }}</span> ₸
                                                  @endif
                                              </td>
                                              <td class="text-center">
                                                  {{-- Класс cart-qty-change и data-article обязательны --}}
                                                  <input type="number" 
                                                        class="form-control form-control-sm text-center cart-qty-change" 
                                                        value="{{ $cartItem['qty'] }}" 
                                                        data-article="{{ $cartItem['article'] }}" 
                                                        min="1">
                                              </td>
                                              {{-- Класс item-subtotal-display поможет обновить сумму строки --}}
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

                            {{-- Мобильный вид (карточки) --}}
                            <div class="d-md-none">
                                @foreach (session()->get('cart')->content() as $cartItem)
                                    <div class="p-3 border-bottom position-relative">
                                        <div class="fw-bold text-primary small">{{ $cartItem['brand'] }}</div>
                                        <div class="fw-bold mb-1">{{ $cartItem['article'] }}</div>
                                        <div class="small text-muted mb-2">{{ $cartItem['name'] }}</div>
                                        <div class="d-flex justify-content-between align-items-end mt-2">
                                            <div>
                                                <div class="extra-small text-muted">Кол-во:</div>
                                                <input type="number" class="form-control form-control-sm text-center" value="{{ $cartItem['qty'] }}" style="width: 60px;">
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-dark">{{ number_format($cartItem['priceWithMargine'], 0, '', ' ') }} ₸</div>
                                                <div class="small text-primary fw-bold">Итого: {{ number_format((int)$cartItem['qty'] * (int)$cartItem['priceWithMargine'], 0, '', ' ') }} ₸</div>
                                            </div>
                                        </div>
                                        <button class="btn btn-sm btn-light text-danger position-absolute top-0 end-0 m-2 cart-item-delete">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="/cart/clear" class="btn btn-link text-muted btn-sm text-decoration-none">
                            <i class="fas fa-eraser me-1"></i> Очистить корзину
                        </a>
                    </div>
                </div>

                {{-- Боковая панель оформления --}}
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 100px;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Оформление заказа</h5>
                            
                            <form action="/makeorder" method="POST" id="make-order-form">
                                @csrf
                                @auth
                                    <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                    
                                    

                                    <div class="bg-light p-3 rounded-3 mb-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Позиций:</span>
                                            <span class="fw-bold">{{ session()->get('cart')->count() }}</span>
                                        </div>
                                        <div class="d-flex justify-content-between border-top pt-2">
                                            <span class="fw-bold">К оплате:</span>
                                            <span class="h4 fw-bold text-primary mb-0" id="cart-total-checkout">
                                              {{ number_format(session()->get('cart')->totalWithMargine(), 0, '', ' ') }} ₸
                                          </span>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow-sm py-3" id="modal-show">
                                        Оформить заказ <i class="fas fa-chevron-right ms-2 small"></i>
                                    </button>
                                @else
                                    <button type="button" class="btn btn-secondary btn-lg w-100 rounded-pill disabled py-3">
                                        Требуется вход
                                    </button>
                                @endauth
                            </form>

                            <div class="mt-4 text-center">
                                <img src="/images/kaspi-red.png" height="30" class="me-2 opacity-75">
                                <img src="/images/visa.png" height="20" class="me-2 opacity-75">
                                <img src="/images/mastercard.png" height="20" class="opacity-75">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Пустая корзина --}}
            <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                <div class="mb-4">
                    <i class="fas fa-shopping-basket fa-4x text-light"></i>
                </div>
                <h4 class="fw-bold">Ваша корзина пуста</h4>
                <p class="text-muted">Самое время найти нужные запчасти!</p>
                <a href="/" class="btn btn-primary rounded-pill px-5">На главную</a>
            </div>
        @endif
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>

{{-- Модалка подтверждения (твой старый функционал, но в стиле BS5) --}}
{{-- Модалка подтверждения (твой старый функционал, но в стиле BS5) --}}
<div class="modal fade" id="orderConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="fw-bold mb-0">Подтверждение заказа</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4">
    @if(session()->has('cart') && is_object(session()->get('cart')))
        Вы действительно хотите оформить заказ на сумму 
        <span class="fw-bold text-primary h5">
            {{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} ₸
        </span>
    @else
        Вы действительно хотите оформить заказ?
    @endif
</div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Отмена</button>
        <button type="button" class="btn btn-primary rounded-pill px-4" id="order-confirm-btn">Да, заказываю</button>
      </div>
    </div>
  </div>
</div>
@endsection