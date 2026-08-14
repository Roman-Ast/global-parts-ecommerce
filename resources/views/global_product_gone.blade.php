@extends('layouts.app')

@section('title', 'Товар больше не поддерживается — Global Parts')
@section('description', 'Эта страница больше не поддерживается в каталоге Global Parts.')

@section('robots')
    <meta name="robots" content="noindex, follow">
@endsection

@section('content')
<div class="main-wrapper d-flex flex-column" style="min-height: 100vh;">
    @include('components.header')
    @include('components.header-mini')

    <div class="container flex-grow-1 my-5 text-center">
        <div class="alert alert-warning d-inline-block">
            <h3>Эта страница больше не поддерживается</h3>
            <p class="mb-0">Позиция удалена из каталога или никогда не была в нём частью. Попробуйте поиск ниже.</p>
        </div>
        <div class="mt-3">
            <a href="/" class="btn btn-primary">Вернуться на главную</a>
        </div>
    </div>

    <div class="mt-auto">
        @include('components.footer-bar-mini')
        @include('components.footer')
    </div>
</div>
@endsection
