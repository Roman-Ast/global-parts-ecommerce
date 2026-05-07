@extends('layouts.app')

@section('title', 'Результат поиска')

@section('content')

@php
/**
 * Улучшенный расчёт диапазона дней доставки.
 * Теперь корректно обрабатывает ISO даты (T00:00:00) и кириллические статусы.
 */
function deliveryRange(string $rawDate): string {
    $cleanDate = str_contains($rawDate, 'T') ? explode('T', $rawDate)[0] : $rawDate;

    if (
        in_array($cleanDate, ['в офисе', '1 день'], true) || 
        str_contains($cleanDate, 'час') || 
        str_contains($cleanDate, 'часа')
    ) {
        return $cleanDate;
    }

    $today = new \DateTime('today');

    try {
        $checkDate = new \DateTime($cleanDate);
        if ($checkDate->format('Y-m-d') === $today->format('Y-m-d')) {
            return '1.5–2 часа';
        }
    } catch (\Exception $e) {
        return $rawDate;
    }

    $supplierDate = \DateTime::createFromFormat('Y-m-d', $cleanDate)
                  ?: \DateTime::createFromFormat('d.m.Y', $cleanDate)
                  ?: \DateTime::createFromFormat('d.m.y', $cleanDate)
                  ?: null;

    if (!$supplierDate) {
        try { $supplierDate = new \DateTime($cleanDate); } 
        catch (\Exception $e) { return $rawDate; }
    }

    $diff = $today->diff($supplierDate);
    $daysMin = (int) $diff->format("%r%a");
    
    if ($daysMin <= 0) return '1.5–2 часа';

    return $daysMin . '–' . ($daysMin + 3) . ' дней';
}

/**
 * Цвета баджей: зелёный для наличия, жёлтый для заказа.
 */
function getBadgeColor($rawDate, $sectionKey) {
    if ($sectionKey === 'crosses_in_office' || $sectionKey === 'crosses_on_stock' || $rawDate === 'в офисе') {
        return 'bg-success text-white border-0';
    }
    return 'bg-warning text-dark border-0';
}
@endphp

{{-- Основной контейнер с отступом сверху 150px, чтобы не заходил под хэдер --}}
<div class="container" style="margin-top: 170px; padding-bottom: 120px;">
    
    @include('components.header')
    @include('components.header-mini')

    <div class="row g-3">
        
        {{-- ФИЛЬТР БРЕНДОВ (Sticky) --}}
        <div class="col-lg-2 d-none d-lg-block">
            <div class="card border-0 shadow-sm sticky-top" style="top: 160px; z-index: 10;">
                <div class="card-header bg-white fw-bold py-2 small border-0">
                    БРЕНДЫ
                </div>
                <div class="card-body p-2" style="max-height: 450px; overflow-y: auto;">
                    @foreach ($brands as $brand)
                        <div class="form-check small mb-1">
                            <input class="form-check-input brand-filter" type="checkbox" value="{{ $brand }}" id="br-{{ $loop->index }}">
                            <label class="form-check-label cursor-pointer" for="br-{{ $loop->index }}">
                                {{ $brand }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- РЕЗУЛЬТАТЫ ВЫДАЧИ --}}
        <div class="col-lg-10 col-12 px-2 px-md-0">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold">
                    Предложения для <span class="text-primary">{{ $chosenBrand }} {{ $finalArr['originNumber'] }}</span>
                </h6>
            </div>

            @foreach(['searchedNumber' => 'Запрошенный номер', 'crosses_in_office' => 'В наличии в офисе', 'crosses_on_stock' => 'На складе', 'crosses_to_order' => 'Аналоги на заказ'] as $key => $title)
                @if(!empty($finalArr[$key]))
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-pill px-3 shadow-sm">{{ $title }}</span>
                            <div class="flex-grow-1 border-bottom" style="opacity: 0.1"></div>
                        </div>

                        @foreach($finalArr[$key] as $item)
                            @php 
                                $dTime = $item['delivery_time'] ?? $item['deliveryStart'] ?? '—';
                                $delivery = deliveryRange($dTime);
                                $badgeClass = getBadgeColor($delivery, $key);
                                // Ограничение наименования до 60 символов
                                $shortName = Str::limit($item['name'], 60, '...');
                            @endphp
                            
                            <div class="card border-0 shadow-sm mb-2 gp-product-row overflow-hidden" 
                                 data-brand="{{ $item['brand'] }}" 
                                 data-article="{{ $item['article'] }}">
                                
                                {{-- DESKTOP --}}
                                <div class="card-body p-3 d-none d-md-grid align-items-center" 
                                     style="grid-template-columns: 2.5fr 1fr 80px 110px 150px;">
                                    
                                    <div class="pe-2">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="text-primary fw-bold">{{ $item['brand'] }}</span>
                                            <span class="fw-bold">{{ $item['article'] }}</span>
                                        </div>
                                        <div class="small text-muted" title="{{ $item['name'] }}">{{ $shortName }}</div>
                                        <div class="x-small text-secondary mt-1"><i class="bi bi-geo-alt"></i> {{ $item['supplier_city'] ?? 'склад' }}</div>
                                    </div>

                                    <div class="text-center">
                                        <span class="badge {{ $badgeClass }} py-2 px-3 rounded-pill shadow-sm">{{ $delivery }}</span>
                                    </div>

                                    <div class="text-center small fw-bold">{{ $item['qty'] ?? 0 }} шт.</div>

                                    <div class="text-end fw-bold text-primary h6 mb-0">
                                        {{ number_format($item['priceWithMargine'], 0, '.', ' ') }} ₸
                                    </div>

                                    <div class="d-flex gap-1 ps-3">
                                        <input type="number" class="form-control form-control-sm text-center" value="1" min="1" style="width: 50px;" data-target="cart-qty">
                                        <button class="btn btn-primary btn-sm flex-grow-1 gp-add-to-cart shadow-sm">В корзину</button>
                                    </div>
                                </div>

                                {{-- MOBILE --}}
                                <div class="card-body p-3 d-md-none">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-primary mb-0" style="font-size: 0.85rem;">{{ $item['brand'] }}</div>
                                            <div class="fw-bold h6 mb-1">{{ $item['article'] }}</div>
                                        </div>
                                        <span class="badge {{ $badgeClass }} rounded-pill shadow-sm">{{ $delivery }}</span>
                                    </div>
                                    <div class="small text-muted mb-3 lh-sm">{{ $shortName }}</div>
                                    <div class="d-flex align-items-center justify-content-between border-top pt-2">
                                        <div>
                                            <div class="x-small text-muted mb-0">Наличие: <b>{{ $item['qty'] ?? 0 }} шт.</b></div>
                                            <div class="h5 fw-bold text-dark mb-0">{{ number_format($item['priceWithMargine'], 0, '.', ' ') }} ₸</div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            <input type="number" class="form-control form-control-sm text-center" value="1" min="1" style="width: 45px;" data-target="cart-qty">
                                            <button class="btn btn-primary btn-sm gp-add-to-cart px-3 shadow-sm">
                                                <i class="bi bi-cart-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            {{-- ПАГИНАЦИЯ --}}
            <nav class="mt-4 pb-5">
                <ul class="pagination pagination-sm justify-content-center">
                    @for ($i = 1; $i <= 10; $i++)
                        <li class="page-item {{ $i == 1 ? 'active' : '' }}">
                            <a class="page-link border-0 shadow-sm mx-1 rounded page-num-trigger" href="#" data-page="{{ $i }}">{{ $i }}</a>
                        </li>
                    @endfor
                </ul>
            </nav>

        </div>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>

{{-- БЛОК КОПИРОВАНИЯ (Admin) --}}
@auth @if(auth()->user()->user_role == 'admin')
<div id="copy_text_wrapper" style="position: fixed; bottom: 80px; right: 20px; z-index: 1050;">
    <button id="copy_text_btn" class="btn btn-primary shadow-lg border-0 px-4 py-2 rounded-pill">
        <i class="bi bi-clipboard-check me-2"></i> Копировать текст
    </button>
</div>
@endif @endauth

<textarea id="clipboard-buffer" style="position: absolute; left: -9999px;"></textarea>

<style>
    .x-small { font-size: 0.7rem; }
    .cursor-pointer { cursor: pointer; }
    .gp-product-row { transition: transform 0.2s; }
    .gp-product-row:hover { transform: translateY(-2px); }
    
    /* Кастомный скролл для фильтра */
    .card-body::-webkit-scrollbar { width: 4px; }
    .card-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }

    @media (max-width: 768px) {
        .gp-product-row { border-radius: 12px; }
    }
</style>

@endsection