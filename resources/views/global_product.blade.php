@extends('layouts.app')

@section('title', $product 
    ? "Купить " . $product->name . " " . $product->brand . " (" . $product->article . ") в Казахстане — Цена, Наличие" 
    : "Товар не найден — Global Parts")

@section('description', $product 
    ? "Купить " . $product->name . " " . $product->brand . " (арт. " . $product->article . ") в Астане за " . number_format($product->price, 0, '.', ' ') . " ₸." 
    : "К сожалению, запрашиваемый товар не найден в нашем каталоге.")

@section('canonical')
    <link rel="canonical" href="{{ $canonicalUrl }}" />
@endsection

@section('content')
<style>
    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    /* Стили для стрелок Slick (чтобы они были видны на светлом фоне) */
    .slick-prev, .slick-next {
        z-index: 10;
        width: 40px;
        height: 40px;
        background: #fff !important;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .slick-prev:before, .slick-next:before {
        color: #333 !important; /* Цвет стрелочек */
        font-size: 24px;
    }
    .slick-prev { left: -20px; }
    .slick-next { right: -20px; }

    .faq-section {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .faq-title {
        font-weight: 700;
        font-size: 1.25rem;
        color: #333;
        margin-bottom: 20px;
        padding-left: 10px;
        border-left: 4px solid #ffc107; /* Твой фирменный желтый */
    }

    .accordion-flush .accordion-item {
        border-bottom: 1px solid #f1f1f1;
    }

    .accordion-button:not(.collapsed) {
        background-color: rgba(255, 193, 7, 0.05); /* Легкий желтый фон при открытии */
        color: #664d03;
        box-shadow: none;
    }

    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(255, 193, 7, 0.1);
    }

    .accordion-body {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #555;
    }
    .pulse-badge {
        animation: pulse-animation 2s infinite;
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
    }

    @keyframes pulse-animation {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
        }
        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }
    /* 1. Сначала сбрасываем всё для мобилок */
    .main-wrapper {
        padding-top: 0px !important;
        margin-top: 0px !important;
    }

    /* 2. Для планшетов и компов возвращаем 100px */
    @media (min-width: 992px) {
        .main-wrapper {
            padding-top: 100px !important;
        }
    }
    
    /* Дополнительно убираем отступы у контейнера на мобилках */
    @media (max-width: 991px) {
        .container.my-5 {
            margin-top: 1rem !important;
        }
    }

    .pulse-animation { animation: pulse-blue 2s infinite; }
    @keyframes pulse-blue {
        0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(13, 110, 253, 0); }
        100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
    }
    .animate-bounce { animation: bounce 2s infinite; }
    @keyframes bounce {
        0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
        40% {transform: translateY(-10px);}
        60% {transform: translateY(-5px);}
    }
    /* Этот стиль заставляет все переходы по якорям (id) быть плавными */
    html {
        scroll-behavior: smooth;
    }
</style>
{{-- Основной контейнер с отступом сверху, чтобы не заезжать под хедер --}}
<div class="main-wrapper d-flex flex-column" style="min-height: 100vh;">
    
    @include('components.header')
    @include('components.header-mini')

    @if(!$product)
        {{-- Код для страницы 404 --}}
        <div class="container text-center mt-5">
            <div class="alert alert-warning">
                <h3>Товар не найден</h3>
                <p>К сожалению, запчасть с артикулом {{ request()->route('article') }} не найдена.</p>
                <a href="/" class="btn btn-primary">Вернуться на главную</a>
            </div>
        </div>  
    @endif

    <div class="container flex-grow-1 my-5">
        {{-- Хлебные крошки --}}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/">Главная</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width: 200px;">{{ $product->brand }}</li>
            </ol>
        </nav>

        <div class="row">
    {{-- Левая колонка: Основная инфа --}}
    <div class="col-lg-9">
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h1 class="h2 fw-bold mb-3">
                            {{ $product->name }}
                            {{-- Проверка наличия в офисе --}}
                            @if(isset($product->supplier_name) && $product->supplier_name === 'is_in_office')
                                <span class="badge rounded-pill bg-success pulse-badge ms-2" style="font-size: 0.5em; vertical-align: middle;">
                                    В наличии в офисе
                                </span>
                            @endif
                        </h1>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="p-2 border-start border-4 border-primary bg-light">
                                    <small class="text-muted d-block">Артикул</small>
                                    <span class="fw-bold h5 mb-0">{{ $product->article }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border-start border-4 border-secondary bg-light">
                                    <small class="text-muted d-block">Бренд</small>
                                    <span class="fw-bold h5 mb-0 text-uppercase">{{ $product->brand }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Блок дополнительных OEM номеров для SEO --}}
                        @php
                            // Разбиваем строку OEM по разделителю |, убираем лишние пробелы
                            $oemList = !empty($product->oem) ? explode('|', $product->oem) : [];
                            $oemList = array_map('trim', $oemList);
                            
                            // Фильтруем: оставляем только те, что не совпадают с текущим артикулом и не пустые
                            $filteredOems = array_filter($oemList, function($oem) use ($product) {
                                return !empty($oem) && $oem !== $product->article && $oem !== $product->clean_article;
                            });
                            // Убираем дубликаты
                            $filteredOems = array_unique($filteredOems);
                        @endphp

                        @if(count($filteredOems) > 0)
                            <div class="mb-4">
                                <small class="text-muted d-block mb-1"><i class="fas fa-fingerprint me-1"></i> Дополнительные кросс-номера (OEM):</small>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($filteredOems as $oemItem)
                                        <span class="badge bg-white text-dark border fw-normal" style="font-size: 0.85rem;">
                                            {{ $oemItem }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mb-3">
                            @if(empty($product->retail_price) || $product->retail_price <= 0 || (isset($product->is_virtual) && $product->is_virtual))
                                {{-- Ультра-компактный блок в одну строку --}}
                                <div style="background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 8px; border: 1px solid #ffeeba; font-size: 0.85rem;">
                                    <i class="fas fa-search me-2"></i> 
                                    <strong>Нет в наличии и/или неверные данные:</strong> нажмите ниже для поиска
                                </div>
                            @else
                                {{-- Обычная цена --}}
                                <small class="text-muted d-block">Цена:</small>
                                <h2 class="fw-bold text-dark mb-0">{{ number_format($product->retail_price, 0, '.', ' ') }} ₸</h2>
                            @endif
                        </div>

                        {{-- МОБИЛЬНАЯ КНОПКА: Показывается только на смартфонах (d-lg-none) --}}
                        <div class="d-block d-lg-none mb-4">
                            <button class="btn btn-primary btn-lg w-100 py-3 shadow fw-bold pulse-animation rounded-3 border-0" 
                                    onclick="document.getElementById('start-api-search').click();" 
                                    style="min-height: 70px;">
                                <div class="h5 mb-0"><i class="fas fa-sync-alt me-2"></i> УЗНАТЬ НАЛИЧИЕ И ЦЕНУ</div>
                                <div class="small fw-normal opacity-75" style="font-size: 0.7rem;">Опрос 15+ складов (KZ, РФ, ОАЭ)</div>
                            </button>
                        </div>

                        <a href="https://wa.me/77087172549?text=Интересует {{ $product->brand }} {{ $product->article }}" 
                           class="btn btn-success btn-lg w-100 shadow-sm py-3 mb-3">
                            <i class="fab fa-whatsapp me-2"></i> Заказать через WhatsApp
                        </a>
                    </div>

                    <div class="col-md-5 mt-4 mt-md-0 d-flex flex-column">
                        <div id="prodCarousel" class="carousel slide border rounded bg-white shadow-sm overflow-hidden flex-grow-1" style="min-height: 280px;">
                            <div class="carousel-inner h-100" id="google-images-container">
                                <div class="carousel-item active h-100">
                                    <div class="d-flex flex-column align-items-center justify-content-center h-100 p-4 bg-white" style="min-height: 280px;">
                                        <div class="mb-3">
                                            <img src="{{ asset('images/placeholders/default_gear.jpeg') }}" alt="placeholder" style="max-height: 120px; width: auto;">
                                        </div>
                                        <div class="text-center w-100">
                                            <h5 class="fw-bold text-dark mb-1">{{ $product->brand }}</h5>
                                            <p class="text-muted small mb-3">{{ $product->article }}</p>
                                            <button id="load-google-images" class="btn btn-outline-primary btn-sm rounded-pill px-4 shadow-sm fw-bold">
                                                <i class="fas fa-search-plus me-1"></i> Показать реальное фото
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="image-disclaimer" class="mt-2 text-center">
                            <p class="text-muted mb-0" style="font-size: 0.7rem; line-height: 1.2;">
                                <i class="fas fa-info-circle me-1 text-primary text-opacity-75"></i> 
                                Изображение подобрано автоматически.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Правая колонка: Подбор --}}
    <div class="col-lg-3 d-none d-lg-block">
        <div class="card border-0 shadow-sm sticky-lg-top" style="top: 120px;">
            <div class="card-body text-center p-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                    <i class="fas fa-search h3 mb-0"></i>
                </div>
                <h5 class="fw-bold">Подбор по VIN</h5>
                <p class="small text-muted">Сомневаетесь? Наши эксперты проверят совместимость бесплатно.</p>
                <button class="btn btn-outline-primary w-100 rounded-pill mt-2">Написать эксперту</button>
            </div>
        </div>
    </div>
</div>

{{-- Блок API поиска: Кнопка (Десктоп) + Таблица --}}
<div id="api-search-container">
    
    {{-- ДЕСКТОПНАЯ КНОПКА: Скрыта на мобилках (d-none d-lg-block) --}}
    <div class="row mb-3 mt-4 d-none d-lg-block">
        <div class="col-12">
            <button id="start-api-search" class="btn btn-primary btn-lg w-100 py-3 shadow fw-bold pulse-animation rounded-3 border-0 start-api-search">
                <div class="h5 mb-0 text-uppercase">
                    <i class="fas fa-sync-alt me-2"></i> Получить актуальные предложения
                </div>
                <div class="small fw-normal opacity-75 mt-1" style="font-size: 0.75rem;">
                    Проверить остатки на 15+ складах (Казахстан, РФ, ОАЭ)
                </div>
            </button>
        </div>
    </div>

    <!-- Скрытая кнопка-дублер для мобильной логики (всегда в DOM для работы JS) -->
    <button id="start-api-search" class="d-none start-api-search"></button>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-header bg-dark text-white py-3" id="api-searchres-header">
                    <h6 class="mb-0 fw-bold small text-uppercase">
                        <i class="fas fa-boxes me-2 text-warning"></i> Результаты опроса складов
                    </h6>
                </div>
                
                <div class="card-body p-0">
                    {{-- Заглушка --}}
                    <div id="api-offers-placeholder" class="text-center py-5 bg-light">
                        <div class="py-4 px-3">
                            <i class="fas fa-arrow-up fa-2x text-primary mb-3 animate-bounce"></i>
                            <h6 class="text-secondary fw-bold">Нажмите "Узнать наличие" выше</h6>
                            <p class="small text-muted mb-0">Мы мгновенно проверим актуальные остатки и сроки</p>
                        </div>
                    </div>

                    {{-- Лоадер --}}
                    <div id="api-offers-loader" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                        <p class="mt-3 text-dark fw-bold h6">Опрашиваем склады...</p>
                        <div class="progress mx-auto mt-2" style="width: 200px; height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    {{-- Таблица --}}
                    <div id="api-offers-content" style="display: none;">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0 w-100">
                                <thead class="table-light text-uppercase fw-bold sticky-top">
                                    <tr style="font-size: 0.7rem;">
                                        <th class="ps-3 py-3">Склад</th>
                                        <th class="py-3 text-center">Срок</th>
                                        <th class="py-3 text-center">Нал.</th>
                                        <th class="py-3">Цена</th>
                                        <th class="pe-3 py-3 text-end"></th>
                                    </tr>
                                </thead>
                                <tbody id="api-offers-tbody" class="small"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





        

        <div class="row mt-5 pb-5">
            <div class="col-12">
                <div class="card border-0 bg-white shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <div class="col-lg-4 bg-light d-flex flex-column align-items-center justify-content-center p-4 p-lg-5 border-end">
            <div class="text-center mb-4 w-100 position-relative">
                
                <div class="mb-3 d-flex justify-content-center opacity-50" style="filter: grayscale(100%);">
                    <img src="/images/KZ-map.webp" alt="kz-map" class="w-100">
                </div>
                
                <div class="d-flex align-items-center justify-content-center mt-2">
                    <span class="h4 fw-bold mb-0 me-2 text-dark">Доставка по</span>
                    <img src="https://flagcdn.com/w80/kz.png" width="40" alt="Флаг Казахстана" class="rounded-1 shadow-sm border">
                </div>
                <p class="text-muted mt-2 small">из Астаны в любой регион</p>
            </div>
            
            <div class="d-flex flex-wrap justify-content-center gap-2 px-2">
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Алматы</span>
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Шымкент</span>
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Караганда</span>
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Атырау</span>
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Актобе</span>
                <span class="badge bg-white text-dark border px-3 py-2 fw-medium rounded-pill shadow-sm">Актау</span>
            </div>
        </div>

                            <div class="col-lg-8 p-4 p-lg-5">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="bg-primary p-2 rounded-3 me-3 text-white">
                                        <i class="fas fa-info-circle fa-lg"></i>
                                    </div>
                                    <h2 class="h3 fw-bold mb-0">Информация о товаре и доставке</h2>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-dark"><i class="fas fa-globe-asia me-2 text-primary"></i> География поставок</h6>
                                        <p class="small text-muted">Мы возим запчасти напрямую из <strong>ОАЭ (Дубай), Китая и России</strong>. Это позволяет нам находить даже редкие позиции для {{ $product->brand }} в кратчайшие сроки.</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-dark"><i class="fas fa-pills me-2 text-primary"></i> Склад в Астане</h6>
                                        <p class="small text-muted">Наш основной хаб находится в столице. Большинство запчастей доступны к самовывозу или отправке в день заказа.</p>
                                    </div>
                                </div>

                                <div class="p-4 bg-blue-light rounded-3 mb-4" style="background-color: #f0f7ff; border-left: 4px solid #0d6efd;">
                                    <h5 class="h6 fw-bold">Купить {{ $product->name }} {{ $product->brand }} {{ $product->article }}</h5>
                                    <p class="text-muted small mb-0">
                                        В наличии на складах и под заказ. Мы гарантируем быструю логистику и проверку каждой детали по VIN-коду. Работаем как с частными лицами, так и с СТО по всему Казахстану.
                                    </p>
                                </div>

                                <div class="row text-center g-3">
                                    <div class="col-4 col-md-2">
                                        <i class="fas fa-box-open d-block mb-1 text-muted"></i>
                                        <span class="extra-small text-muted" style="font-size: 0.7rem;">Наличие</span>
                                    </div>
                                    <div class="col-4 col-md-2 border-start">
                                        <i class="fas fa-history d-block mb-1 text-muted"></i>
                                        <span class="extra-small text-muted" style="font-size: 0.7rem;">На заказ</span>
                                    </div>
                                    <div class="col-4 col-md-2 border-start">
                                        <i class="fas fa-shield-check d-block mb-1 text-muted"></i>
                                        <span class="extra-small text-muted" style="font-size: 0.7rem;">Гарантия</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- FAQ --}}
    <div class="container mt-5 mb-5">
        <div class="faq-section">
            <h3 class="faq-title">Часто задаваемые вопросы о {{ $product->brand }} {{ $product->article }}</h3>
            <div class="accordion accordion-flush" id="productFaqAccordion">
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-1">
                            Оригинал ли это {{ $product->brand }} или аналог?
                        </button>
                    </h2>
                    <div id="faq-1" class="accordion-collapse collapse" data-bs-parent="#productFaqAccordion">
                        <div class="accordion-body">
                            Бренд <strong>{{ $product->brand }}</strong> является проверенным производителем автозапчастей. В нашем магазине Global Parts мы гарантируем подлинность продукции. Деталь с артикулом <strong>{{ $product->article }}</strong> проходит строгий контроль качества перед отправкой.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-2">
                            Как точно узнать, подходит ли {{ $product->article }} на мой автомобиль?
                        </button>
                    </h2>
                    <div id="faq-2" class="accordion-collapse collapse" data-bs-parent="#productFaqAccordion">
                        <div class="accordion-body">
                            Лучший способ проверить совместимость — подбор по VIN-коду. Вы можете нажать на кнопку WhatsApp, отправить нам техпаспорт, и наши менеджеры подтвердят применимость детали именно для вашего авто в течение 5-10 минут.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-3">
                            Как осуществляется доставка по Казахстану?
                        </button>
                    </h2>
                    <div id="faq-3" class="accordion-collapse collapse" data-bs-parent="#productFaqAccordion">
                        <div class="accordion-body">
                            По Астане возможна доставка "день в день". В Алматы, Шымкент, Караганду и другие города РК мы отправляем заказы через надежные курьерские службы или транспортные компании. Срок доставки обычно составляет от 2 до 5 рабочих дней.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-4">
                            Можно ли вернуть деталь, если она не подошла?
                        </button>
                    </h2>
                    <div id="faq-4" class="accordion-collapse collapse" data-bs-parent="#productFaqAccordion">
                        <div class="accordion-body">
                            Да, согласно законодательству РК, вы можете вернуть товар в течение 14 дней, если деталь не устанавливалась, сохранена заводская упаковка и товарный вид.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    {{-- Рекомендации для вас--}}
    <div class="container my-5">
        <h5 class="fw-bold mb-4"><i class="bi bi-gear-wide-connected me-2"></i>Похожие товары ({{ $product->brand }})</h5>
        <div class="recommended-slider">
            @foreach($recommended as $item)
                <div class="px-2">
                    <a href="/product/{{ $item->brand }}/{{ $item->article }}" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm text-center p-3 hover-card">
                            
                            <div class="product-img-container mb-2 d-flex align-items-center justify-content-center" 
                                style="height: 100px; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                                
                                <img src="{{ $item->getPlaceholder() }}" 
                                    class="img-fluid" 
                                    style="max-height: 80px; width: auto;"
                                    onerror="this.onerror=null; this.src='{{ asset('images/placeholders/default_gear.jpeg') }}'">
                            </div>

                            <div class="text-muted mb-1 fw-bold" style="font-size: 0.75rem;">
                                {{ $item->article }}
                            </div>

                            <div class="fw-bold text-dark small text-truncate" style="max-width: 100%;">
                                {{ $item->name }}
                            </div>

                            <div class="text-primary fw-bold mt-1">
                                {{ number_format($item->price, 0, '.', ' ') }} ₸
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
    {{-- Футер будет всегда внизу благодаря flex-grow-1 выше --}}
    <div class="mt-auto">
        @include('components.footer-bar-mini')
        @include('components.footer')
    </div>
</div>

<script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "{{ $product->name }} {{ $product->brand }} ({{ $product->article }})",
        "image": [
            "https://shop.globalparts.kz/images/logo1.png" 
        ],
        "description": "Купить {{ $product->name }} артикул {{ $product->article }} бренда {{ $product->brand }} в Казахстане. Цена: {{ number_format($product->retail_price, 0, '', '') }} ₸. В наличии и под заказ.",
        "sku": "{{ $product->article }}",
        "brand": {
            "@type": "Brand",
            "name": "{{ $product->brand }}"
        },
        "offers": {
            "@type": "Offer",
            "url": "{{ url()->current() }}",
            "priceCurrency": "KZT",
            "price": "{{ number_format($product->retail_price, 0, '', '') }}", 
            "itemCondition": "https://schema.org/NewCondition",
            "availability": "https://schema.org/InStock",
            "seller": {
            "@type": "Organization",
            "name": "Global Parts Astana"
            }
        }
    }
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [{
            "@type": "Question",
            "name": "Оригинал ли это {{ $product->brand }} или аналог?",
            "acceptedAnswer": {
            "@type": "Answer",
            "text": "Бренд {{ $product->brand }} является проверенным производителем. Мы в Global Parts гарантируем качество детали {{ $product->article }}."
            }
        },
        {
            "@type": "Question",
            "name": "Как проверить совместимость детали {{ $product->article }}?",
            "acceptedAnswer": {
            "@type": "Answer",
            "text": "Рекомендуем отправить VIN-код менеджеру в WhatsApp для точного подтверждения применимости."
            }
        }]
    }
</script>
<script>
    $(document).ready(function(){
        // Инициализация слайдера рекомендаций
        $('.recommended-slider').slick({
            infinite: false,
            slidesToShow: 4,
            slidesToScroll: 1,
            arrows: true,
            dots: false,
            responsive: [
                { breakpoint: 1024, settings: { slidesToShow: 3 } },
                { breakpoint: 768, settings: { slidesToShow: 2 } },
                { breakpoint: 480, settings: { slidesToShow: 1 } }
            ]
        });
    });

    function renderOfferRow(offer) {
        const qty = parseInt(offer.qty) || 0;
        const priceDisplay = Number(offer.priceWithMargine || 0).toLocaleString();
        
        let delivery = offer.delivery_time || offer.deliveryStart || '1-2 дня';
        if (delivery.includes('T')) delivery = delivery.split('T')[0];

        const isAstana = offer.supplier_city.toLowerCase() === 'ast' || delivery.includes('часа');
        const rowClass = isAstana ? 'table-success' : '';
        const badgeClass = isAstana ? 'bg-success' : 'bg-light text-dark border';

        return `
        <tr class="${rowClass} animate__animated animate__fadeIn">
            <td class="ps-4 py-3">
                <div class="fw-bold text-primary text-uppercase">${offer.brand}</div>
                <div class="small text-muted fw-bold">${offer.article}</div>
                <div class="extra-small text-muted" style="font-size: 0.7rem; max-width: 300px;">
                    ${offer.name || ''}
                </div>
            </td>
            <td class="text-center align-middle">
                <span class="badge ${badgeClass}">${isAstana ? 'В наличии: Астана' : delivery}</span>
                <div class="small text-muted" style="font-size: 0.65rem;">${offer.supplier_city || ''}</div>
            </td>
            <td class="text-center align-middle">
                <span class="badge ${qty > 0 ? 'bg-success' : 'bg-secondary'}">${qty} шт.</span>
            </td>
            <td class="align-middle fw-bold h5 text-primary">${priceDisplay} ₸</td>
            <td class="pe-4 text-end">
                <button class="btn ${isAstana ? 'btn-success' : 'btn-primary'} btn-sm rounded-pill px-3 shadow-sm api-buy-btn"
                    data-brand="${offer.brand}" 
                    data-article="${offer.article}" 
                    data-qty="${qty}" 
                    data-name="${offer.name || 'Автозапчасть'}" 
                    data-price="${offer.price || 0}" 
                    data-price-margine="${offer.priceWithMargine}"
                    data-delivery="${delivery}"
                    data-supplier="${offer.supplier_city || 'Склад'}">
                    Купить
                </button>
            </td>
        </tr>`;
    }

    document.getElementById('start-api-search')?.addEventListener('click', function() {
        const brand = {!! json_encode($product->brand) !!};
        const article = {!! json_encode($product->article) !!};
        const btn = this;
        const placeholder = document.getElementById('api-offers-placeholder');
        const loader = document.getElementById('api-offers-loader');
        const content = document.getElementById('api-offers-content');
        const tbody = document.getElementById('api-offers-tbody');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Ищем...';
        
        placeholder.style.display = 'none';
        loader.style.display = 'block';
        tbody.innerHTML = ''; // Очищаем старое

        // ШАГ 1: Основной быстрый поиск
        fetch(`/api/search-prices?article=${encodeURIComponent(article)}&brand=${encodeURIComponent(brand)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(json => {
            loader.style.display = 'none';
            content.style.display = 'block';
            
            let html = '';
            if (json.offers && json.offers.length > 0) {
                json.offers.forEach(offer => html += renderOfferRow(offer));
                tbody.innerHTML = html;
            }

            // ШАГ 2: Догружаем Rossko (запускаем сразу после отрисовки первых данных)
            console.log('Запрашиваем Rossko...');
            fetch(`/api/search-rossko?article=${encodeURIComponent(article)}&brand=${encodeURIComponent(brand)}`)
            .then(res => res.json())
            .then(rosskoData => {
                if (rosskoData.offers && rosskoData.offers.length > 0) {
                    let rosskoHtml = '';
                    rosskoData.offers.forEach(offer => rosskoHtml += renderOfferRow(offer));
                    // Добавляем в начало, если Астана, или в конец
                    tbody.insertAdjacentHTML('afterbegin', rosskoHtml); 
                    console.log('Rossko добавлен');
                }
                btn.innerHTML = 'Обновлено';
            })
            .catch(err => console.error('Rossko error:', err));
        })
        .catch(err => {
            loader.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = 'Ошибка. Повторить?';
        });
    });

    function handleMobileApiSearch(btn) {
        // 1. Блокировка и лоадер
        btn.disabled = true;
        btn.classList.remove('pulse-animation');
        btn.style.opacity = '0.8';

        const textBlock = document.getElementById('btn-text-mobile');
        const loaderBlock = document.getElementById('btn-loader-mobile');
        
        if (textBlock) textBlock.style.display = 'none';
        if (loaderBlock) loaderBlock.style.display = 'block';

        // 2. ЖЕСТКИЙ СКРОЛЛ (Прямой расчет)
        setTimeout(() => {
            const resultsBlock = document.getElementById('api-search-container');
            if (resultsBlock) {
                // Вычисляем точное расстояние от верха страницы до блока
                const yOffset = -20; // Небольшой отступ сверху, чтобы заголовок не прилипал
                const y = resultsBlock.getBoundingClientRect().top + window.pageYOffset + yOffset;

                window.scrollTo({
                    top: y,
                    behavior: 'smooth'
                });
            }
        }, 150); // Увеличили задержку до 150мс для стабильности на мобилках

        // 3. Запуск основного поиска
        const mainBtn = document.getElementById('start-api-search');
        if (mainBtn) {
            mainBtn.click();
        }

        // Резервный таймер разблокировки
        setTimeout(() => {
            if (btn.disabled) {
                btn.disabled = false;
                btn.classList.add('pulse-animation');
                btn.style.opacity = '1';
                if (textBlock) textBlock.style.display = 'block';
                if (loaderBlock) loaderBlock.style.display = 'none';
            }
        }, 20000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('load-google-images');
        const container = document.getElementById('google-images-container');

        if (btn) {
            btn.onclick = function() {
                // Данные берем прямо из Blade
                const brand = "{{ $product->brand }}";
                const article = "{{ $product->article }}";
                const query = brand + " " + article;

                // Визуальный отклик
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Ищем...';

                // Формируем чистый URL
                const url = '/fetch-images?q=' + encodeURIComponent(query);

                fetch(url)
                    .then(response => {
                        if (!response.ok) throw new Error('Ошибка сервера: ' + response.status);
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.length > 0) {
                            container.innerHTML = ''; 
                            data.forEach((item, index) => {
                                const active = index === 0 ? 'active' : '';
                                container.innerHTML += `
                                    <div class="carousel-item ${active} h-100">
                                        <div class="d-flex align-items-center justify-content-center h-100 p-2" style="min-height: 280px;">
                                            <img src="${item.link}" class="img-fluid rounded shadow-sm" style="max-height: 260px; object-fit: contain;">
                                        </div>
                                    </div>`;
                            });
                            btn.innerHTML = '<i class="fas fa-check"></i> Найдено';
                        } else {
                            btn.innerHTML = 'Фото не найдены';
                            btn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        alert('Ошибка при поиске: ' + error.message);
                        btn.innerHTML = 'Повторить';
                        btn.disabled = false;
                    });
            };
        }
    });
</script>

@endsection