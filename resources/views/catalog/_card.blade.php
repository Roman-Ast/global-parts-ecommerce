{{-- Плитка карточки каталога. Ожидает $item (PartsCatalog, с ->offer из CatalogController::attachOffers,
     ->isBestPrice — опционально, из CatalogController::markBestPrice). --}}
<div class="border rounded-3 h-100 catalog-card-tile d-flex flex-column bg-white position-relative">
    @if($item->isBestPrice ?? false)
        <span class="badge position-absolute" style="top: 8px; left: 8px; z-index: 1; background-color: #DA251C;">Выгодно</span>
    @endif
    <a href="{{ route('catalog.show', $item->id) }}" class="text-decoration-none text-reset p-2 d-block">
        <div class="ratio ratio-1x1 bg-light rounded mb-2 d-flex align-items-center justify-content-center overflow-hidden">
            @if(!empty($item->images[0] ?? null))
                <img src="{{ $item->images[0] }}" alt="{{ $item->name }}" loading="lazy"
                     style="width:100%;height:100%;object-fit:contain;">
            @else
                <span class="text-muted small">Нет фото</span>
            @endif
        </div>
        <div class="small text-truncate" title="{{ $item->name }}">{{ $item->name }}</div>
        <div class="small text-muted">{{ \Illuminate\Support\Str::upper($item->brand) }} · {{ $item->article }}</div>
    </a>

    <div class="p-2 pt-0 mt-auto">
        @if($item->offer)
            <div class="fw-bold mb-1">{{ number_format($item->offer['retail_price'], 0, '.', ' ') }} ₸</div>

            @if($item->offer['stock'] > 0)
                <button type="button" class="btn btn-sm btn-primary w-100 api-buy-btn"
                        data-brand="{{ \Illuminate\Support\Str::upper($item->brand) }}"
                        data-article="{{ $item->article }}"
                        data-name="{{ $item->name }}"
                        data-price="{{ $item->offer['purchase_price'] }}"
                        data-price-margine="{{ $item->offer['retail_price'] }}"
                        data-qty="{{ $item->offer['stock'] }}"
                        data-supplier="{{ $item->offer['supplier_name'] }}"
                        data-delivery="Сегодня-завтра">
                    Купить
                </button>
            @else
                <div class="small text-muted">
                    Под заказ{{ $item->offer['preorder_days'] ? ', ' . $item->offer['preorder_days'] . ' дн.' : '' }}
                </div>
            @endif
        @else
            <div class="small text-muted">Цена по запросу</div>
        @endif
    </div>
</div>
