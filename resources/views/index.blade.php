@extends('layouts.app')

@section('title', 'Главная')
    


@section('content')
    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>
     
    @include('components.header')
    
    <div id="shadow">
        <div id="modal-qr" class="container">
            <img src="images/wa-qr.jpeg" alt="wa-qr">
            Для перехода в Whatsapp отсканируйте QR-код с камеры мобильного телефона
        </div>
    </div>
    
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















