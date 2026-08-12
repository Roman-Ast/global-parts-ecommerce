@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <h3 class="mb-4">Каталог карточек Kaspi</h3>

    {{-- Фильтры --}}
    <form method="GET" class="row g-2 mb-4 align-items-center">
        <div class="col-auto">
            <select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Все источники</option>
                <option value="own" @selected(request('source') === 'own')>Свои ({{ $counts['total'] }})</option>
                <option value="competitor" @selected(request('source') === 'competitor')>Конкуренты</option>
            </select>
        </div>
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Любой статус</option>
                <option value="done" @selected(request('status') === 'done')>Done ({{ $counts['done'] }})</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending ({{ $counts['pending'] }})</option>
                <option value="not_found" @selected(request('status') === 'not_found')>Not found ({{ $counts['not_found'] }})</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed ({{ $counts['failed'] }})</option>
            </select>
        </div>
        <div class="col-auto">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Поиск по артикулу/названию/бренду" style="width: 280px;">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Применить</button>
            <a href="{{ route('admin.parts-catalog.index') }}" class="btn btn-sm btn-outline-secondary">Сброс</a>
        </div>
    </form>

    {{-- Плитки --}}
    <div class="row g-3">
        @forelse($cards as $card)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="{{ route('admin.parts-catalog.show', $card) }}" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm">
                        <div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center">
                            @php $firstImage = $card->images[0] ?? null; @endphp
                            @if($firstImage)
                                <img src="{{ $firstImage }}" alt="{{ $card->name }}"
                                     class="w-100 h-100" style="object-fit: contain;" loading="lazy">
                            @else
                                <span class="text-muted small">нет фото</span>
                            @endif
                        </div>
                        <div class="card-body p-2">
                            <div class="small text-truncate fw-semibold" title="{{ $card->name }}">
                                {{ $card->name ?? $card->article }}
                            </div>
                            <div class="small text-muted text-truncate">
                                {{ $card->brand }} · {{ $card->article }}
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-{{ match($card->scrape_status) {
                                    'done' => 'success',
                                    'pending' => 'secondary',
                                    'not_found' => 'warning',
                                    'failed' => 'danger',
                                    default => 'light',
                                } }}">
                                    {{ $card->scrape_status }}
                                </span>
                                <span class="badge bg-{{ $card->source === 'own' ? 'primary' : 'dark' }}">
                                    {{ $card->source }}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12 text-muted">Ничего не найдено по текущим фильтрам.</div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $cards->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection