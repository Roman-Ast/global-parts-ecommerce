@extends('layouts.app')

@section('content')
<div class="container py-4">
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary mb-3">&larr; Назад к списку</a>

    <div class="row g-4">
        {{-- Галерея --}}
        <div class="col-md-5">
            @if(!empty($card->images))
                <div id="cardGallery" class="mb-2">
                    <img id="mainImage" src="{{ $card->images[0] }}" alt="{{ $card->name }}"
                         class="img-fluid border rounded" style="max-height: 400px; object-fit: contain; width: 100%;">
                </div>
                @if(count($card->images) > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach($card->images as $img)
                            <img src="{{ $img }}" class="border rounded thumb"
                                 style="width: 64px; height: 64px; object-fit: contain; cursor: pointer;"
                                 onclick="document.getElementById('mainImage').src = this.src">
                        @endforeach
                    </div>
                @endif
            @else
                <div class="border rounded bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                    <span class="text-muted">Нет изображений</span>
                </div>
            @endif
        </div>

        {{-- Основная инфа --}}
        <div class="col-md-7">
            <h4>{{ $card->name ?? '(название не вытянуто)' }}</h4>
            <div class="text-muted mb-3">
                Артикул: <strong>{{ $card->article }}</strong> ·
                Бренд: <strong>{{ $card->brand }}</strong> ·
                <span class="badge bg-{{ $card->source === 'own' ? 'primary' : 'dark' }}">{{ $card->source }}</span>
                <span class="badge bg-secondary">{{ $card->scrape_status }}</span>
            </div>

            <div class="small text-muted mb-3">
                kaspi_sku: {{ $card->source_kaspi_sku }} ·
                <a href="https://kaspi.kz/shop/p/-{{ $card->source_kaspi_sku }}/" target="_blank" rel="noopener">
                    открыть на Kaspi &#8599;
                </a>
                @if($card->scraped_at)
                    · спарсено {{ $card->scraped_at->diffForHumans() }}
                @endif
            </div>

            @if($card->description)
                <h6>Описание</h6>
                <div class="border rounded p-3 mb-3 bg-light">
                    {!! $card->description !!}
                </div>
            @endif

            @if($card->applicability)
                <h6>Применимость</h6>
                <div class="border rounded p-3 mb-3">
                    {!! $card->applicability !!}
                </div>
            @endif
        </div>
    </div>

    {{-- Характеристики --}}
    @if(!empty($card->characteristics['specifications']))
        <div class="mt-4">
            <h6>Характеристики</h6>
            @foreach($card->characteristics['specifications'] as $section)
                <div class="mb-3">
                    <div class="fw-semibold small text-uppercase text-muted mb-1">{{ $section['name'] ?? '' }}</div>
                    <table class="table table-sm table-bordered">
                        <tbody>
                            @foreach($section['features'] ?? [] as $feature)
                                <tr>
                                    <td style="width: 280px;" class="text-muted">{{ $feature['name'] ?? '' }}</td>
                                    <td>
                                        {{ collect($feature['featureValues'] ?? [])->pluck('value')->join(', ') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Сырой JSON для отладки --}}
    <details class="mt-4">
        <summary class="text-muted small">Сырые данные (debug)</summary>
        <pre class="small bg-light border rounded p-3 mt-2" style="max-height: 400px; overflow: auto;">{{ json_encode($card->characteristics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
</div>
@endsection