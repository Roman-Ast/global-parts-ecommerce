@extends('layouts.app')

@push('styles')
    <link href="{{ URL::asset('css/components/partSearchRes-mini.css') }}" rel="stylesheet">
    <link href="{{ URL::asset('css/components/partSearchRes.css') }}" rel="stylesheet">
    <style>
        /* === Google-style top loading bar === */
        #top-loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: #1a73e8;
            z-index: 9999;
            transition: width 0.3s ease;
            box-shadow: 0 0 8px rgba(26, 115, 232, 0.6);
        }
        #top-loading-bar.indeterminate {
            animation: bar-indeterminate 1.8s infinite ease-in-out;
        }
        @keyframes bar-indeterminate {
            0%   { left: -30%; width: 30%; }
            60%  { left: 60%;  width: 40%; }
            100% { left: 110%; width: 30%; }
        }

        /* === Skeleton loader === */
        @keyframes skeleton-shimmer {
            0%   { background-position: -600px 0; }
            100% { background-position: 600px 0; }
        }
        .skeleton-row {
            display: flex;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid rgba(0,0,0,0.07);
            align-items: center;
        }
        .skeleton-cell {
            height: 14px;
            border-radius: 4px;
            background: linear-gradient(
                90deg,
                rgba(0, 0, 0, 0.06) 25%,
                rgba(0, 0, 0, 0.12) 50%,
                rgba(0, 0, 0, 0.06) 75%
            );
            background-size: 1200px 100%;
            animation: skeleton-shimmer 1.6s infinite linear;
        }
        .sk-supplier { width: 70px; }
        .sk-brand    { width: 80px; }
        .sk-article  { width: 90px; }
        .sk-name     { flex: 1; }
        .sk-delivery { width: 70px; }
        .sk-qty      { width: 40px; }
        .sk-price    { width: 60px; }
        .sk-cart     { width: 80px; }

        .async-loading-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #aaa;
            margin-left: 10px;
        }
        .async-done-badge {
            display: none;
            font-size: 0.75rem;
            color: #4caf50;
            margin-left: 10px;
        }
    </style>
@endpush

@section('title', 'Результат поиска')
   
@section('content')
<div id="top-loading-bar" class="indeterminate"></div>
<div id="search-res-main-container" class="container">

    @include('components.header')
    @include('components.header-mini')

    <div id="curtain-grey-searchpartres"></div>

    <div id="search-res-main-wrapper">
        <div id="search-res-filter">
            <div class="search-res-filter-item" id="filter-brands">
                <div class="search-res-filter-item-header">
                    БРЕНД
                </div>
                <div class="search-res-filter-item-content">
                    <ul>
                        <li>
                            @foreach ($brands as $brand)
                                <div class="form-check">
                                    <input class="form-check-input brand-filter" type="checkbox" value="{{ $brand }}" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault" class="filter-brand-name">
                                        {{ $brand }}
                                    </label>
                                </div>
                            @endforeach
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    
        <div id="search-part-main-container">
            <div id="search-res-header">
                <div>
                    Предложения для <span id="search-res-header-val">{{ $chosenBrand}} {{ $finalArr['originNumber'] }}</span>
                    <span class="async-loading-badge" id="async-loading-badge">
                        <span class="spinner-border spinner-border-sm" role="status"></span>
                        загружаем все предложения...
                    </span>
                    <span class="async-done-badge" id="async-done-badge">✓ загружено</span>
                </div>
                @auth
                @if(auth()->user()->user_role == "admin")
                    <div id="articles-hide-wrapper">
                        <i>скрыть артикула</i> <input type="checkbox" id="articles-hide">
                    </div>
                @endif
                @endauth
            </div>
            
            <div id="search-res-part-header">
                <div class="search-res-part-header-item">Наименование</div>
                <div class="search-res-part-header-item">Доставка</div>
                <div class="search-res-part-header-item">Кол-во</div>
                <div class="search-res-part-header-item" style="text-align: center;">Цена</div>
            </div>

            {{-- ===== ЗАПРОШЕННЫЙ АРТИКУЛ ===== --}}
            @if (count($finalArr['searchedNumber']) > 0)
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">Запрошенный артикул</div>
            </div>
            
            <div id="requestPartNumberContainer">
                <input type="hidden" value="{{ $finalArr['originNumber'] }}" id="originNumber">
                @foreach ($finalArr['searchedNumber'] as $searchItem)
                    <div class="requestPartNumberContainer-item" data-price="{{ $searchItem['priceWithMargine'] }}">
                        {{-- ...без изменений, твой оригинальный markup... --}}
                        @auth
                        @if(auth()->user()->user_role == "admin")
                            <div class="form-check">
                                <input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;">
                            </div>
                        @endif
                        @endauth
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                            @auth
                                @if (auth()->user()->user_role == "admin") {{ $searchItem['supplier_name'] }}
                                @else {{ $searchItem['supplier_city'] }}
                                @endif
                            @else {{ $searchItem['supplier_city'] }}
                            @endauth
                        </div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">{{ $searchItem['brand'] }}</div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">{{ $searchItem['article'] }}</div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-name">{{ $searchItem['name'] }}</div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                            @if(array_key_exists('info',$searchItem))
                                <img src="/images/info_pic.png" alt="info">
                            @else
                                <img src="/images/info_unavailable.png" alt="info">
                            @endif
                        </div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-delivery">
                            @if ($searchItem['deliveryStart'] == 'в офисе' || (strtotime($searchItem['deliveryStart']) && date('d.m.y', strtotime($searchItem['deliveryStart'])) == date('d.m.y')))
                                <span class="badge bg-success" style="padding: 5px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-block; min-width: 80px; text-align: center;">
                                    {{ $searchItem['deliveryStart'] == 'в офисе' ? 'в офисе' : '1.5-2 часа' }}
                                </span>
                            @elseif (!empty($searchItem['deliveryStart']) && strtotime($searchItem['deliveryStart']) && strtotime($searchItem['deliveryStart']) > 0)
                                <span class="text-muted" style="font-weight: 600;">{{ date('d.m.y', strtotime($searchItem['deliveryStart'])) }}</span>
                            @else
                                <span class="text-muted small">уточняйте</span>
                            @endif
                        </div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-count">{{ $searchItem['qty'] }}</div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-price stock-item-price">{{ $searchItem['priceWithMargine'] }}</div>
                        <div class="requestPartNumberContainer-item-entity requestPartNumber-cart">
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                                <div class="stock-item-cart-qty"><input type='number' value="1" class="form-control"></div>
                                <input type="hidden" value="{{ $searchItem['price'] }}">
                            </div>
                        </div>
                    </div>
                @endforeach
                <div id="show-other-items" counter="10"><a href="###">Показать еще 10</a></div>
            </div>
            @endif

            {{-- ===== АНАЛОГИ В ОФИСЕ (только БД, без async) ===== --}}
            @if (!empty($finalArr['crosses_in_office']))
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">Аналоги в наличии в офисе</div>
            </div>
            <div id="crossesContainer-in-office">
                @foreach ($finalArr['crosses_in_office'] as $crossItem)
                <div class="requestPartNumberContainer-item" data-price="{{ $crossItem['priceWithMargine'] }}">
                    @auth
                    @if(auth()->user()->user_role == "admin")
                        <div class="form-check"><input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;"></div>
                    @endif
                    @endauth
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth @if (auth()->user()->user_role == "admin") {{ $crossItem['supplier_name'] }} @else {{ $crossItem['supplier_city'] }} @endif
                        @else {{ $crossItem['supplier_city'] }} @endauth
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">{{ $crossItem['brand'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">{{ $crossItem['article'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">{{ $crossItem['name'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        @if(array_key_exists('info',$crossItem)) <img src="/images/info_pic.png" alt="info"> @else <img src="/images/info_unavailable.png" alt="info"> @endif
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery" style="background-color:{{ $crossItem['supplier_color']}};color:#111">
                        {{ $crossItem['delivery_time'] }}
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">{{ $crossItem['qty'] > 10 ? '>10' : $crossItem['qty'] }}</div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        <div class="stock-item stock-item-price">{{ $crossItem['priceWithMargine'] }}</div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        <div class="stock-item-cart">
                            <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                            <div class="stock-item-cart-qty"><input type='number' value="1" class="form-control"></div>
                            <input type="hidden" value="{{ $crossItem['price'] }}">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- ===== АНАЛОГИ НА СКЛАДЕ (БД сразу + API async) ===== --}}
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">Аналоги в наличии на складе</div>
            </div>
            <div id="crossesContainer-on-stock">
                {{-- БД-данные --}}
                @foreach ($finalArr['crosses_on_stock'] as $crossItem)
                <div class="requestPartNumberContainer-item" data-price="{{ $crossItem['priceWithMargine'] }}">
                    @auth
                    @if(auth()->user()->user_role == "admin")
                        <div class="form-check"><input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;"></div>
                    @endif
                    @endauth
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth @if (auth()->user()->user_role == "admin") {{ $crossItem['supplier_name'] }} @else {{ $crossItem['supplier_city'] }} @endif
                        @else {{ $crossItem['supplier_city'] }} @endauth
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">{{ $crossItem['brand'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">{{ $crossItem['article'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">{{ $crossItem['name'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        @if (array_key_exists('info', $crossItem))
                            <img src="/images/info_pic.png" alt="info" class="spare-part-info-show">
                            {{-- info-block popup без изменений --}}
                        @else
                            <img src="/images/info_unavailable.png" alt="info">
                        @endif
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery parts-on-stock">
                        <span class="badge bg-success" style="padding: 5px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-block; min-width: 80px; text-align: center;">
                            {{ $crossItem['delivery_time'] }}
                        </span>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        <div class="stock-item stock-item-qty">{{ $crossItem['qty'] }}</div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        <div class="stock-item stock-item-price">{{ $crossItem['priceWithMargine'] }}</div>
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        <div class="stock-item-cart">
                            <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                            <div class="stock-item-cart-qty"><input type='number' value="1" class="form-control"></div>
                            <input type="hidden" value="{{ $crossItem['price'] }}">
                        </div>
                    </div>
                </div>
                @endforeach
                {{-- Skeleton-строки — JS заменит их на реальные данные --}}
                <div id="skeleton-on-stock">
                    @for ($i = 0; $i < 4; $i++)
                    <div class="skeleton-row">
                        <div class="skeleton-cell sk-supplier"></div>
                        <div class="skeleton-cell sk-brand"></div>
                        <div class="skeleton-cell sk-article"></div>
                        <div class="skeleton-cell sk-name"></div>
                        <div class="skeleton-cell sk-delivery"></div>
                        <div class="skeleton-cell sk-qty"></div>
                        <div class="skeleton-cell sk-price"></div>
                        <div class="skeleton-cell sk-cart"></div>
                    </div>
                    @endfor
                </div>
            </div>

            {{-- ===== АНАЛОГИ НА ЗАКАЗ (БД сразу + API async) ===== --}}
            <div class="searchResForRequestPartNumber">
                <div class="searchResForRequestPartNumberHeader">Аналоги на заказ</div>
            </div>
            <div id="crossesContainer-to-order">
                @foreach ($finalArr['crosses_to_order'] as $crossItem)
                <div class="requestPartNumberContainer-item" data-price="{{ $crossItem['priceWithMargine'] }}">
                    @auth
                    @if(auth()->user()->user_role == "admin")
                        <div class="form-check"><input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width: 0.9em; height: 0.9em;"></div>
                    @endif
                    @endauth
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">
                        @auth @if (auth()->user()->user_role == "admin") {{ $crossItem['supplier_name'] }} @else {{ $crossItem['supplier_city'] }} @endif
                        @else {{ $crossItem['supplier_city'] }} @endauth
                    </div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">{{ $crossItem['brand'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">{{ $crossItem['article'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-name">{{ $crossItem['name'] }}</div>
                    <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                        @if(array_key_exists('info',$crossItem)) <img src="/images/info_pic.png" alt="info"> @else <img src="/images/info_unavailable.png" alt="info"> @endif
                    </div>
                    @if ($crossItem['supplier_color'])
                        <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery">
                            <span class="badge" style="background-color: {{ $crossItem['supplier_color'] }}; color: #fff; padding: 5px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-block; min-width: 80px; text-align: center; border: 1px solid {{ $crossItem['supplier_color'] }}">
                                {{ date('d.m.y', strtotime($crossItem['delivery_time'])) }}
                            </span>
                        </div>
                    @else
                        <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery text-muted">
                            <span class="badge bg-light text-dark" style="padding: 5px 10px; border-radius: 6px;">
                                {{ date('d.m.y', strtotime($crossItem['delivery_time'])) }}
                            </span>
                        </div>
                    @endif
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-qty">{{ $stockItem['qty'] }}</div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item stock-item-price">{{ $crossItem['priceWithMargine'] }}</div>
                        @endforeach
                    </div>
                    <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                        @foreach ($crossItem['stocks'] as $stockItem)
                            <div class="stock-item-cart">
                                <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                                <div class="stock-item-cart-qty"><input type='number' value="1" class="form-control"></div>
                                <input type="hidden" value="{{ $crossItem['price'] }}">
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
                {{-- Skeleton --}}
                <div id="skeleton-to-order">
                    @for ($i = 0; $i < 4; $i++)
                    <div class="skeleton-row">
                        <div class="skeleton-cell sk-supplier"></div>
                        <div class="skeleton-cell sk-brand"></div>
                        <div class="skeleton-cell sk-article"></div>
                        <div class="skeleton-cell sk-name"></div>
                        <div class="skeleton-cell sk-delivery"></div>
                        <div class="skeleton-cell sk-qty"></div>
                        <div class="skeleton-cell sk-price"></div>
                        <div class="skeleton-cell sk-cart"></div>
                    </div>
                    @endfor
                </div>
            </div>

            {{-- pagination без изменений --}}
            <nav aria-label="..." class="pagination-nav">...</nav>
        </div>
    </div>
</div>

<div id="copy_text_wrapper">
    <button id="copy_text_btn" class="btn btn-primary">Копировать текст</button>
</div>
<textarea id="clipboard-buffer" style="position: absolute; left: -9999px;"></textarea>

@include('components.footer-bar-mini')
@include('components.footer')

@push('scripts')
<script>
(function () {
    // Параметры из текущей страницы
    const brand      = @json($chosenBrand);
    const partnumber = @json($finalArr['originNumber']);
    const isAdmin    = @json(auth()->check() && auth()->user()->user_role === 'admin');

    // ---- helpers ----

    function formatDeliveryOnStock(item) {
        // Для crosses_on_stock — всегда "в наличии" или дата
        const d = item.delivery_time ?? '';
        if (!d) return '<span class="text-muted small">уточняйте</span>';
        return `<span class="badge bg-success" style="padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:600;display:inline-block;min-width:80px;text-align:center;">${d}</span>`;
    }

    function formatDeliveryToOrder(item) {
        const d = item.delivery_time ?? '';
        let dateStr;
        if (!d || d === '—') {
            dateStr = '—';
        } else {
            const parsed = new Date(d);
            dateStr = isNaN(parsed)
                ? d  // если не дата — показываем как есть (например "3-5 дней")
                : parsed.toLocaleDateString('ru-RU', {day:'2-digit', month:'2-digit', year:'2-digit'});
        }

        const color = item.supplier_color ?? null;
        if (color) {
            return `<span class="badge" style="background-color:${color};color:#fff;padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:600;display:inline-block;min-width:80px;text-align:center;">${dateStr}</span>`;
        }
        return `<span class="badge" style="background-color:#6c757d;color:#fff;padding:5px 10px;border-radius:6px;font-size:0.85rem;font-weight:600;display:inline-block;min-width:80px;text-align:center;">${dateStr}</span>`;
    }

    function supplierLabel(item) {
        return isAdmin ? (item.supplier_name ?? '') : (item.supplier_city ?? '');
    }

    function adminCheckbox() {
        return isAdmin ? `<div class="form-check"><input class="form-check-input shadow-none copy_text" name="copy_text" type="checkbox" style="width:0.9em;height:0.9em;"></div>` : '';
    }

    function buildOnStockRow(item) {
        return `
        <div class="requestPartNumberContainer-item" data-price="${item.priceWithMargine}">
            ${adminCheckbox()}
            <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">${supplierLabel(item)}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">${item.brand ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">${item.article ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-name">${item.name ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                <img src="/images/info_unavailable.png" alt="info">
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery parts-on-stock">
                ${formatDeliveryOnStock(item)}
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">
                <div class="stock-item stock-item-qty">${item.qty ?? ''}</div>
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">
                <div class="stock-item stock-item-price">${item.priceWithMargine ?? ''}</div>
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">
                <div class="stock-item-cart">
                    <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                    <div class="stock-item-cart-qty"><input type="number" value="1" class="form-control"></div>
                    <input type="hidden" value="${item.price ?? ''}">
                </div>
            </div>
        </div>`;
    }

    function buildToOrderRow(item) {
        const stocks = item.stocks ?? [{}];
        const stockCells = stocks.map(() => `
            <div class="stock-item stock-item-qty">${item.qty ?? ''}</div>`).join('');
        const priceCells = stocks.map(() => `
            <div class="stock-item stock-item-price">${item.priceWithMargine ?? ''}</div>`).join('');
        const cartCells = stocks.map(() => `
            <div class="stock-item-cart">
                <div class="stock-item-cart-btn"><img src="/images/cart_pic_20.png" alt="cart" class="stock-item-cart-img"></div>
                <div class="stock-item-cart-qty"><input type="number" value="1" class="form-control"></div>
                <input type="hidden" value="${item.price ?? ''}">
            </div>`).join('');

        return `
        <div class="requestPartNumberContainer-item" data-price="${item.priceWithMargine}">
            ${adminCheckbox()}
            <div class="requestPartNumberContainer-item-entity requestPartNumber-supplier">${supplierLabel(item)}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-brand">${item.brand ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-partnumber">${item.article ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-name">${item.name ?? ''}</div>
            <div class="requestPartNumberContainer-item-entity requestPartNumber-info">
                <img src="/images/info_unavailable.png" alt="info">
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-delivery">
                ${formatDeliveryToOrder(item)}
            </div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-count">${stockCells}</div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-price">${priceCells}</div>
            <div class="requestPartNumberContainer-item-entity cross-item-countable requestPartNumber-cart">${cartCells}</div>
        </div>`;
    }

    function sortContainerByPrice(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const rows = [...container.querySelectorAll('.requestPartNumberContainer-item')];
        rows.sort((a, b) => {
            return parseFloat(a.dataset.price || 0) - parseFloat(b.dataset.price || 0);
        });
        rows.forEach(r => container.appendChild(r));
    }

    function insertRows(containerId, skeletonId, rows) {
        const skeleton = document.getElementById(skeletonId);
        const container = document.getElementById(containerId);
        if (skeleton) skeleton.remove();
        rows.forEach(html => {
            container.insertAdjacentHTML('beforeend', html);
        });
        sortContainerByPrice(containerId);
    }

    // ---- fetch ----

    const url = `/search/other-json?brand=${encodeURIComponent(brand)}&partnumber=${encodeURIComponent(partnumber)}`;
    const bar = document.getElementById('top-loading-bar');
    
    fetch(url)
    .then(r => r.json())
    .then(data => {
        // На складе
        const onStockRows = (data.crosses_on_stock ?? []).map(buildOnStockRow);
        insertRows('crossesContainer-on-stock', 'skeleton-on-stock', onStockRows);

        // На заказ
        const toOrderRows = (data.crosses_to_order ?? []).map(buildToOrderRow);
        insertRows('crossesContainer-to-order', 'skeleton-to-order', toOrderRows);

        // Бренды в фильтр
        if (data.brands && data.brands.length) {
            const filterList = document.querySelector('#filter-brands .search-res-filter-item-content ul li');
            if (filterList) {
                data.brands.forEach(brand => {
                    const exists = [...filterList.querySelectorAll('.brand-filter')]
                        .some(el => el.value === brand);
                    if (!exists) {
                        filterList.insertAdjacentHTML('beforeend', `
                            <div class="form-check">
                                <input class="form-check-input brand-filter" type="checkbox" value="${brand}">
                                <label class="form-check-label filter-brand-name">${brand}</label>
                            </div>`);
                    }
                });
            }
        }

        // Останавливаем полосу
        bar.classList.remove('indeterminate');
        bar.style.width = '100%';
        setTimeout(() => { bar.style.opacity = '0'; }, 400);
        setTimeout(() => { bar.style.display = 'none'; }, 800);

        document.getElementById('async-loading-badge').style.display = 'none';
        document.getElementById('async-done-badge').style.display    = 'inline-flex';
    })
    .catch(err => {
        console.error('Async load error:', err);
        document.getElementById('skeleton-on-stock')?.remove();
        document.getElementById('skeleton-to-order')?.remove();

        bar.classList.remove('indeterminate');
        bar.style.background = '#e53935';
        bar.style.width = '100%';
        setTimeout(() => { bar.style.opacity = '0'; }, 600);

        document.getElementById('async-loading-badge').style.display = 'none';
    });
})();
</script>
@endpush

@endsection