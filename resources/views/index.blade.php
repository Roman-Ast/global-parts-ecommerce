@extends('layouts.app')

@section('title', 'Главная')
    
@section('content')
    @include('components.header')
    
    @if (session()->has('message'))
    <div class="alert {{ Session::get('class') }}" style="align-text:center;">
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>    
    @endif
    <div id="main-container" class="container">
        <div id="steering">
            <div id="steering-wrapper">
                <div id="steering-reika"> </div>
                <div id="steering-gur"></div>
                <span id="steering-text">Рулевые рейки, насосы ГУР в НАЛИЧИИ</span>
            </div>
        </div>
        <div id="mobis">
            <div id="mobis-wrapper">
                <span id="mobis-text">Оригинальные запчасти Hyundai/Kia напрямую из Кореи</span>
            </div>
        </div>
        <div id="all-parts">
            <div id="all-parts-wrapper">
                <span id="all-cars-text">Запчасти на все авто</span>
            </div>
        </div>
        <div id="whatsapp-container">
            <img src="images/whatsapp72.png" alt="wa-big">
        </div>
    </div>

    @include('components.footer')
@endsection















