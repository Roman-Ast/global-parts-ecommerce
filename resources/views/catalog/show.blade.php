@extends('layouts.app')

@section('title', $card->name . ' ' . \Illuminate\Support\Str::upper($card->brand) . ' (' . $card->article . ') — Global Parts')
@section('description', 'Купить ' . $card->name . ' ' . \Illuminate\Support\Str::upper($card->brand) . ' (арт. ' . $card->article . ') в Астане.')

@section('canonical')
    <link rel="canonical" href="{{ url()->current() }}" />
@endsection

@section('content')
<style>
    .main-wrapper { padding-top: 0px !important; margin-top: 0px !important; }
    @media (min-width: 992px) {
        .main-wrapper { padding-top: 100px !important; }
    }

    .gp-gallery-main {
        background: #f8f9fa;
        cursor: zoom-in;
    }
    .gp-gallery-main img { width: 100%; height: 100%; object-fit: contain; }

    .gp-thumb {
        width: 64px;
        height: 64px;
        object-fit: contain;
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: .5rem;
        cursor: pointer;
        transition: border-color .15s ease;
    }
    .gp-thumb:hover { border-color: #adb5bd; }
    .gp-thumb.active { border-color: #0d6efd; }

    .gp-info-sticky { position: sticky; top: 110px; }

    .gp-fact-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .55rem 0;
        border-bottom: 1px solid #f1f1f1;
        font-size: .925rem;
    }
    .gp-fact-row:last-child { border-bottom: none; }
    .gp-fact-label { color: #6c757d; }
    .gp-fact-value { font-weight: 600; text-align: right; }

    .gp-tabs .nav-link {
        color: #6c757d;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
    }
    .gp-tabs .nav-link.active {
        color: #212529;
        background: transparent;
        border-bottom-color: #0d6efd;
    }

    .gp-spec-table td { padding: .6rem .5rem; font-size: .925rem; }
    .gp-spec-table td:first-child { color: #6c757d; width: 45%; }
    .gp-spec-section-title {
        font-weight: 700;
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6c757d;
        margin-bottom: .5rem;
    }
</style>
<div class="main-wrapper d-flex flex-column" style="min-height: 100vh;">
    @include('components.header')
    @include('components.header-mini')

<div class="container pt-4 flex-grow-1" style="padding-bottom: 6rem;">
    <nav class="small text-muted mb-3">
        <a href="{{ route('home') }}" class="text-decoration-none">Главная</a>
        @if($card->category_slug)
            &rsaquo; <a href="{{ route('catalog.category', $card->category_slug) }}" class="text-decoration-none">{{ $card->category_top_title }}</a>
        @endif
        @if($card->category_group_slug)
            &rsaquo; <a href="{{ route('catalog.group', [$card->category_slug, $card->category_group_slug]) }}" class="text-decoration-none">{{ $card->category_group_title }}</a>
        @endif
    </nav>

    <div class="row g-4">
        {{-- Галерея --}}
        <div class="col-lg-6">
            <div class="border rounded-4 shadow-sm p-3 bg-white">
                <div class="ratio ratio-1x1 rounded-3 overflow-hidden gp-gallery-main d-flex align-items-center justify-content-center mb-3">
                    @if(!empty($card->images))
                        <img id="mainImage" src="{{ $card->images[0] }}" alt="{{ $card->name }}">
                    @else
                        <span class="text-muted">Нет изображений</span>
                    @endif
                </div>
                @if(!empty($card->images) && count($card->images) > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach($card->images as $i => $img)
                            <img src="{{ $img }}" class="gp-thumb @if($i === 0) active @endif"
                                 onclick="document.getElementById('mainImage').src = this.src;
                                          document.querySelectorAll('.gp-thumb').forEach(t => t.classList.remove('active'));
                                          this.classList.add('active');">
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Инфо-панель --}}
        <div class="col-lg-6">
            <div class="gp-info-sticky">
                <h1 class="h3 fw-bold mb-3">{{ $card->name }}</h1>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge bg-light text-dark border px-3 py-2">Артикул: <span style="font-family: monospace;">{{ $card->article }}</span></span>
                    <span class="badge bg-light text-dark border px-3 py-2">Бренд: {{ \Illuminate\Support\Str::upper($card->brand) }}</span>
                </div>

                @if($quickFacts->isNotEmpty())
                    <div class="border rounded-4 p-3 mb-4">
                        @foreach($quickFacts as $fact)
                            <div class="gp-fact-row">
                                <span class="gp-fact-label">{{ $fact['label'] }}</span>
                                <span class="gp-fact-value">{{ $fact['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($card->offer)
                    <div class="h3 fw-bold mb-3">{{ number_format($card->offer['retail_price'], 0, '.', ' ') }} ₸</div>

                    @if($card->offer['stock'] > 0)
                        <button type="button" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2 api-buy-btn"
                                data-brand="{{ \Illuminate\Support\Str::upper($card->brand) }}"
                                data-article="{{ $card->article }}"
                                data-name="{{ $card->name }}"
                                data-price="{{ $card->offer['purchase_price'] }}"
                                data-price-margine="{{ $card->offer['retail_price'] }}"
                                data-qty="{{ $card->offer['stock'] }}"
                                data-supplier="{{ $card->offer['supplier_name'] }}"
                                data-delivery="Сегодня-завтра">
                            <i class="fas fa-shopping-cart"></i> Добавить в корзину
                        </button>
                        @if($card->offer['stock'] <= 3)
                            <div class="small fw-semibold mt-2 text-center" style="color: #DA251C;">Осталось {{ $card->offer['stock'] }} шт — заканчивается</div>
                        @else
                            <div class="small text-muted mt-2 text-center">В наличии, доставка сегодня-завтра по Астане</div>
                        @endif
                    @else
                        <div class="alert alert-light border small mb-2">
                            Под заказ{{ $card->offer['preorder_days'] ? ' (' . $card->offer['preorder_days'] . ' дн.)' : '' }} — уточните точный срок
                        </div>
                        <a href="https://wa.me/77087172549?text={{ urlencode('Здравствуйте, интересует ' . $card->name . ' ' . \Illuminate\Support\Str::upper($card->brand) . ' (арт. ' . $card->article . '), подскажите точный срок поставки.') }}"
                           onclick="gtag('event', 'conversion', {'send_to': 'AW-16870370925/M3NOCJe9iqQcEO3ctew-'});"
                           class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center gap-2 wa-top-container">
                            <i class="bi bi-whatsapp"></i> Уточнить срок в WhatsApp
                        </a>
                    @endif
                @else
                    <a href="https://wa.me/77087172549?text={{ urlencode('Здравствуйте, интересует ' . $card->name . ' ' . \Illuminate\Support\Str::upper($card->brand) . ' (арт. ' . $card->article . '), подскажите цену и наличие.') }}"
                       onclick="gtag('event', 'conversion', {'send_to': 'AW-16870370925/M3NOCJe9iqQcEO3ctew-'});"
                       class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center gap-2 wa-top-container">
                        <i class="bi bi-whatsapp"></i> Узнать цену и наличие в WhatsApp
                    </a>
                    <div class="small text-muted mt-2 text-center">Отвечаем обычно в течение нескольких минут</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Табы: описание / характеристики / применимость --}}
    @if($card->description || !empty($card->characteristics['specifications']) || $card->applicability)
        <div class="mt-5">
            <ul class="nav nav-tabs gp-tabs" id="cardTabs" role="tablist">
                @if($card->description)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-description" type="button">Описание</button>
                    </li>
                @endif
                @if(!empty($card->characteristics['specifications']))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if(!$card->description) active @endif" data-bs-toggle="tab" data-bs-target="#tab-specs" type="button">Характеристики</button>
                    </li>
                @endif
                @if($card->applicability)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if(!$card->description && empty($card->characteristics['specifications'])) active @endif" data-bs-toggle="tab" data-bs-target="#tab-applicability" type="button">Применимость</button>
                    </li>
                @endif
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom-4 p-4 bg-white shadow-sm">
                @if($card->description)
                    <div class="tab-pane fade show active" id="tab-description">
                        {!! $card->description !!}
                    </div>
                @endif

                @if(!empty($card->characteristics['specifications']))
                    <div class="tab-pane fade @if(!$card->description) show active @endif" id="tab-specs">
                        @foreach($card->characteristics['specifications'] as $section)
                            <div class="mb-4">
                                <div class="gp-spec-section-title">{{ $section['name'] ?? '' }}</div>
                                <table class="table table-sm gp-spec-table mb-0">
                                    <tbody>
                                        @foreach($section['features'] ?? [] as $feature)
                                            <tr>
                                                <td>{{ $feature['name'] ?? '' }}</td>
                                                <td>{{ collect($feature['featureValues'] ?? [])->pluck('value')->join(', ') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($card->applicability)
                    <div class="tab-pane fade @if(!$card->description && empty($card->characteristics['specifications'])) show active @endif" id="tab-applicability">
                        {!! $card->applicability !!}
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($related->isNotEmpty())
        <div class="mt-5 mb-4">
            <h2 class="fs-4 fw-semibold mb-3 text-dark gp-heading-accent">Обычно меняют вместе</h2>
            <div class="row g-3">
                @foreach($related as $item)
                    <div class="col-6 col-md-4 col-lg-2">
                        @include('catalog._card', ['item' => $item])
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>
@endsection
