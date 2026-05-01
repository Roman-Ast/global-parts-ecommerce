@extends('layouts.app')

@section('title', 'Оформление заказа — Global Parts')

@section('content')

<div class="d-none d-md-block">
        @include('components.header')
        <div style="height: 50px;z-index: 1000"></div> {{-- Отступ для десктопа --}}
    </div>

    <div class="d-md-none">
        <div style="position: fixed; top: 0; width: 100%; z-index: 10; background: #fff;">
            @include('components.header-mini')
        </div>
        <div style="height: 50px;"></div> {{-- Отступ для мобилки, чтобы "Корзина" вылезла --}}
    </div>

<div class="main-wrapper bg-light" style="min-height: 100vh; padding-top: 120px;">
    <div class="container">
        <h1 class="h3 fw-bold mb-4">Оформление заказа</h1>

        <form action="/makeorder" method="POST">
            @csrf
            <div class="row g-4">
                {{-- ЛЕВАЯ КОЛОНКА: ДАННЫЕ --}}
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h5 class="fw-bold mb-4 border-bottom pb-2">1. Контактные данные</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Ваше имя</label>
                                <input type="text" name="name" class="form-control rounded-3" 
                                       value="{{ $user->name ?? '' }}" required placeholder="Иван">
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Телефон (WhatsApp)</label>
                                <input type="text" name="customer_phone" id="phone_mask" class="form-control rounded-3" 
                                       required placeholder="+7 (7xx) xxx-xx-xx">
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <h5 class="fw-bold mb-4 border-bottom pb-2">2. Детали доставки и авто</h5>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="small text-muted mb-1">VIN-код (для проверки совместимости)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-car text-muted"></i></span>
                                    <input type="text" name="vin" class="form-control border-start-0 rounded-end-3" 
                                           maxlength="17" placeholder="17 знаков">
                                </div>
                                <div class="extra-small text-primary mt-1" style="font-size: 0.75rem;">
                                    <i class="fas fa-info-circle me-1"></i> Рекомендуем указать VIN, чтобы мы проверили заказ.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Город</label>
                                <input type="text" name="city" class="form-control rounded-3" placeholder="Астана" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Адрес / Район</label>
                                <input type="text" name="address" class="form-control rounded-3" placeholder="Ул. Абая, дом 10" required>
                            </div>
                            <div class="col-md-12">
                                <label class="small text-muted mb-1">Комментарий к заказу</label>
                                <textarea name="comment" class="form-control rounded-3" rows="2" placeholder="Напишите, если есть пожелания"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ПРАВАЯ КОЛОНКА: ВАША КОРЗИНА (Sticky) --}}
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4" style="top: 130px;">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4">Ваш заказ</h5>
                            
                            <div class="cart-items-preview mb-4" style="max-height: 300px; overflow-y: auto;">
                                @foreach ($cart->content() as $item)
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <div style="max-width: 70%;">
                                            <div class="fw-bold small text-primary">{{ $item['brand'] }}</div>
                                            <div class="extra-small text-dark fw-bold">{{ $item['article'] }}</div>
                                            <div class="text-muted extra-small" style="font-size: 0.7rem;">{{ $item['qty'] }} шт. x {{ number_format($item['priceWithMargine'], 0, '', ' ') }} ₸</div>
                                        </div>
                                        <div class="fw-bold small">
                                            {{ number_format($item['qty'] * $item['priceWithMargine'], 0, '', ' ') }} ₸
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="bg-light p-3 rounded-3 mb-4">
                                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                                    <span class="fw-bold">Итого к оплате:</span>
                                    <span class="h4 fw-bold text-primary mb-0">
                                        {{ number_format($cart->totalWithMargine(), 0, '', ' ') }} ₸
                                    </span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow-sm">
                                Подтвердить и заказать
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="{{ route('cart.index') }}" class="small text-muted text-decoration-none">
                                    <i class="fas fa-chevron-left me-1"></i> Вернуться в корзину
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

    @include('components.footer-bar-mini')
    @include('components.footer')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone_mask');

    phoneInput.addEventListener('input', function (e) {
        let x = e.target.value.replace(/\D/g, '').match(/(\d{0,1})(\d{0,3})(\d{0,3})(\d{0,2})(\d{0,2})/);
        
        // Если первый символ не 7 или 8, принудительно ставим 7
        if (!x[1]) {
            e.target.value = '+7 ';
            return;
        }
        
        e.target.value = !x[2] ? '+7' : '+7 (' + x[2] + (x[3] ? ') ' + x[3] : '') + (x[4] ? '-' + x[4] : '') + (x[5] ? '-' + x[5] : '');
    });

    // Запрещаем удалять +7
    phoneInput.addEventListener('keydown', function(e) {
        if (e.target.selectionStart < 3 && (e.keyCode === 8 || e.keyCode === 46)) {
            e.preventDefault();
        }
    });
});
</script>
@endsection