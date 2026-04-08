@extends('layouts.app')

@section('title', "Купить " . $product->name . " " . $product->brand . " (" . $product->article . ") в Казахстане — Цена, Наличие")

@section('description', "Купить " . $product->name . " " . $product->brand . " (арт. " . $product->article . ") в Астане за " . number_format($product->price, 0, '.', ' ') . " ₸. В наличии в Global Parts, быстрая доставка по Казахстану.")

@section('content')
{{-- Основной контейнер с отступом сверху, чтобы не заезжать под хедер --}}
<div class="main-wrapper d-flex flex-column" style="min-height: 100vh; padding-top: 100px;">
    
    @include('components.header')
    @include('components.header-mini')

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
                                <h1 class="h2 fw-bold mb-3">{{ $product->name }}</h1>
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

                                <div class="mb-4">
                                    <p class="text-muted mb-1">Цена:</p>
                                    {{-- Используем retail_price, которую мы рассчитали в контроллере --}}
                                    <h2 class="fw-bold text-dark">{{ number_format($product->retail_price, 0, '.', ' ') }} ₸</h2>
                                </div>

                                <a href="https://wa.me/77087172549?text=Интересует {{ $product->brand }} {{ $product->article }}" 
                                   class="btn btn-success btn-lg w-100 shadow-sm py-3">
                                    <i class="fab fa-whatsapp me-2"></i> Заказать через WhatsApp
                                </a>
                            </div>

                            <div class="col-md-5 mt-4 mt-md-0 d-flex flex-column">
                                <div id="prodCarousel" class="carousel slide border rounded bg-white shadow-sm overflow-hidden flex-grow-1" data-bs-ride="carousel" style="min-height: 280px;">
                                    <div class="carousel-inner h-100" id="google-images-container">
                                        
                                        {{-- Светлая презентабельная заглушка в едином стиле --}}
                                        <div class="carousel-item active h-100">
                                            <div class="d-flex flex-column align-items-center justify-content-center h-100 p-4 bg-white" style="min-height: 280px;">
                                                
                                                {{-- Иконка из твоего хелпера (100% видимость, без прозрачности) --}}
                                                <div class="mb-3">
                                                    <img src="{{ $product->getPlaceholder() }}" 
                                                        alt="placeholder" 
                                                        style="max-height: 120px; width: auto;"
                                                        onerror="this.onerror=null; this.src='{{ asset('images/placeholders/default_gear.jpeg') }}'">
                                                </div>

                                                {{-- Информация о товаре --}}
                                                <div class="text-center w-100">
                                                    <h5 class="fw-bold text-dark mb-1">{{ $product->brand }}</h5>
                                                    <p class="text-muted small mb-3">{{ $product->article }}</p>
                                                    
                                                    {{-- Твоя кнопка поиска в Google --}}
                                                    <button id="load-google-images" class="btn btn-outline-primary btn-sm rounded-pill px-4 shadow-sm fw-bold">
                                                        <i class="fas fa-search-plus me-1"></i> Показать реальное фото
                                                    </button>
                                                </div>

                                            </div>
                                        </div>

                                    </div>
                                </div>
                                
                                {{-- Дисклеймер под блоком --}}
                                <div id="image-disclaimer" class="mt-auto pt-2 text-center">
                                    <p class="text-muted mb-0" style="font-size: 0.7rem; line-height: 1.2;">
                                        <i class="fas fa-info-circle me-1 text-primary text-opacity-75"></i> 
                                        Изображение подобрано автоматически. Реальный вид детали может отличаться.
                                    </p>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>

            {{-- Правая колонка: Подбор --}}
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm" style="top: 120px;">
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
        {{-- Таблица предложений на ВСЮ ширину контейнера --}}
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-0 mb-5 w-100">
                    <div class="card-header bg-dark text-white py-3 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-boxes me-2 text-warning"></i> 
                            Актуальные предложения со складов
                        </h5>
                        {{-- Кнопка в хедере --}}
                        <button id="start-api-search" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm fw-bold">
                            <i class="fas fa-sync-alt me-1"></i> Получить предложения
                        </button>
                    </div>
                    
                    <div class="card-body p-0">
                        {{-- 1. Заглушка, которую видит клиент ДО нажатия --}}
                        <div id="api-offers-placeholder" class="text-center py-5 bg-light">
                            <div class="py-4">
                                <i class="fas fa-search-dollar fa-3x text-muted mb-3"></i>
                                <h6 class="text-secondary">Нажмите кнопку выше, чтобы проверить наличие и актуальные цены у 15+ поставщиков</h6>
                                <p class="small text-muted px-4">Мы опросим склады в Астане и под заказ (Алматы, РФ, ОАЭ)</p>
                            </div>
                        </div>

                        {{-- 2. Лоадер (скрыт) --}}
                        <div id="api-offers-loader" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-3 text-muted fw-bold">Опрашиваем остатки поставщиков...</p>
                            <p class="small text-muted">Это может занять 10-15 секунд</p>
                        </div>

                        {{-- 3. Контент с таблицей (скрыт) --}}
                        <div id="api-offers-content" style="display: none;">
                            {{-- Обертка для прокрутки --}}
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 w-100" style="min-width: 800px;">
                                    <thead class="table-light text-uppercase small fw-bold" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th class="ps-4 py-3">Поставщик / Склад</th>
                                            <th class="py-3 text-center">Срок доставки</th>
                                            <th class="py-3 text-center">Наличие</th>
                                            <th class="py-3">Ваша цена</th>
                                            <th class="pe-4 py-3 text-end">Действие</th>
                                        </tr>
                                    </thead>
                                    <tbody id="api-offers-tbody">
                                        {{-- Данные из JS --}}
                                    </tbody>
                                </table>
                            </div>
                            {{-- Маленькая подсказка снизу, если данных много --}}
                            <div class="text-center py-2 bg-light border-top">
                                <small class="text-muted italic">Прокрутите таблицу, чтобы увидеть все предложения</small>
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

{{-- Скрипт подгрузки цен --}}
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
</script>

<script>
// Функция для отрисовки ОДНОЙ строки таблицы (используем везде)
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
    const article = "{{ $product->article }}";
    const brand = "{{ $product->brand }}";
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
    fetch(`/api/search-prices?article=${encodeURIComponent(article)}&brand=${encodeURIComponent(brand)}`)
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
</script>
@endsection