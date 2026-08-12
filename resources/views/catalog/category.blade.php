@extends('layouts.app')

@section('title', ($top->category_top_title ?? 'Категория') . ' — купить автозапчасти в Астане | Global Parts')
@section('description', 'Каталог автозапчастей категории «' . ($top->category_top_title ?? '') . '» — в наличии в Астане.')

@section('canonical')
    <link rel="canonical" href="{{ url()->current() }}" />
@endsection

@section('content')
<style>
    .main-wrapper { padding-top: 0px !important; margin-top: 0px !important; }
    @media (min-width: 992px) {
        .main-wrapper { padding-top: 100px !important; }
    }
</style>
<div class="main-wrapper d-flex flex-column" style="min-height: 100vh;">
    @include('components.header')
    @include('components.header-mini')

<div class="container pt-4 pb-5 flex-grow-1">
    <nav class="small text-muted mb-3">
        <a href="{{ route('home') }}" class="text-decoration-none">Главная</a> &rsaquo;
        {{ $top->category_top_title }}
    </nav>

    <h1 class="h4 fw-bold mb-4">{{ $top->category_top_title }}</h1>

    @if($groups->isNotEmpty())
        <div class="row g-3 justify-content-center">
            @foreach($groups as $group)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('catalog.group', [$top->category_slug, $group->category_group_slug]) }}"
                       class="text-decoration-none text-reset">
                        <div class="border rounded p-3 h-100 text-center">
                            <div class="ratio ratio-1x1 bg-light rounded mb-2 d-flex align-items-center justify-content-center overflow-hidden" style="max-width: 140px; margin-left: auto; margin-right: auto;">
                                <img src="{{ asset($group->icon) }}" alt="{{ $group->category_group_title }}" loading="lazy" style="width:100%;height:100%;object-fit:contain;">
                            </div>
                            <div class="fw-semibold">{{ $group->category_group_title }}</div>
                            <div class="small text-muted mt-1">{{ $group->cnt }} товаров</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="row g-3 justify-content-center">
            @foreach($cards as $item)
                <div class="col-6 col-md-4 col-lg-2">
                    @include('catalog._card', ['item' => $item])
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $cards->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>
@endsection
