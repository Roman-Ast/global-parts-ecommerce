@extends('layouts.app')

@section('title', 'Ничего не найдено')
   
@section('content')
@include('components.header')

{{-- resources/views/components/notFoundStub.blade.php --}}
@php
    // Входные данные (передавай из контроллера)
    $q = $query ?? request('article') ?? '';
    $waPhone = $waPhone ?? '77087172549'; // без +, замени на свой
    $waText = $waText ?? ("Здравствуйте! Поиск на сайте не дал результатов. Номер: {$q}. Проверьте наличие/аналоги и срок доставки, пожалуйста.");
    $waUrl  = "https://wa.me/{$waPhone}?text=" . urlencode($waText);

    // Иллюстрация: положи файл в public/images/empty-search.svg (или .png)
    $img = $img ?? asset('images/notFoundStub.png');
@endphp

<div class="container my-4" id="nothing-found-stub-container">
    <div class="card shadow-sm border-0">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-4 text-center">
                    <img src="{{ $img }}"
                         alt="Ничего не найдено"
                         class="img-fluid"
                         style="max-height: 300px;">
                </div>

                <div class="col-12 col-md-8">
                    <h4 class="mb-2 fw-bold">
                        По этому номеру сейчас нет предложений
                    </h4>

                    <p class="mb-3 text-muted">
                        Это не значит, что детали нет. Мы можем быстро проверить аналоги и поставки “под заказ”
                        у других каналов (в т.ч. Китай/Корея/Европа) и подсказать лучший вариант по сроку и цене.
                    </p>

                    {{-- CTA кнопки --}}
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="https://wa.me/77087172549?text=Здравствуйте,%20пишу%20вам%20с%20сайта."
                            onclick="gtag('event', 'conversion', {'send_to': 'AW-16870370925/M3NOCJe9iqQcEO3ctew-'});"
                           class="btn btn-success wa-top-container"
                           target="_blank" rel="noopener">
                            Написать в WhatsApp
                        </a>

                        <a href="/"
                           class="btn btn-outline-secondary">
                            На главную
                        </a>
                    </div>

                    {{-- Мини-подсказки --}}
                    <div class="small text-muted mb-2">
                        Подсказка: проверьте номер — без пробелов, без лишних символов. Если есть VIN — подбор будет точнее.
                    </div>

                    {{-- Доверие / условия --}}
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge rounded-pill text-bg-light border">Доставка по Казахстану</span>
                        <span class="badge rounded-pill text-bg-light border">Гарантия до 6 месяцев*</span>
                        <span class="badge rounded-pill text-bg-light border">Возврат 14 дней*</span>
                    </div>

                    <div class="small text-muted mt-2">
                        <span class="text-muted">* условия зависят от категории и поставщика</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal: быстрый запрос подбора --}}
<div class="modal fade" id="partRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" >
            @csrf
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Запрос подбора запчасти</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Номер / артикул</label>
                    <input type="text"
                           name="article"
                           class="form-control"
                           value="{{ $q }}"
                           placeholder="Например: 86511J7DA0"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label">VIN (если есть)</label>
                    <input type="text"
                           name="vin"
                           class="form-control"
                           placeholder="VIN поможет точнее подобрать аналог/применимость">
                </div>

                <div class="mb-3">
                    <label class="form-label">Телефон / WhatsApp</label>
                    <input type="text"
                           name="phone"
                           class="form-control"
                           placeholder="+7..."
                           required>
                </div>

                <div class="mb-0">
                    <label class="form-label">Комментарий</label>
                    <textarea name="comment" class="form-control" rows="3"
                              placeholder="Марка/модель/год/двигатель, если знаете"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-primary">Отправить запрос</button>
            </div>
        </form>
    </div>
</div>

@include('components.footer')
@endsection