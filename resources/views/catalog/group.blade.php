@extends('layouts.app')

@section('title', ($groupTitle ?? 'Категория') . ' — купить автозапчасти в Астане | Global Parts')
@section('description', 'Каталог автозапчастей «' . ($groupTitle ?? '') . '» — в наличии в Астане.')

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
        <a href="{{ route('catalog.category', $top->category_slug) }}" class="text-decoration-none">{{ $top->category_top_title }}</a> &rsaquo;
        {{ $groupTitle }}
    </nav>

    <h1 class="h4 fw-bold mb-4">{{ $groupTitle }}</h1>

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
</div>

    @include('components.footer-bar-mini')
    @include('components.footer')
</div>
@endsection
