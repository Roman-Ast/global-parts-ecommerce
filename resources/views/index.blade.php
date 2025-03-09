@extends('layouts.app')

@section('title', 'Главная')
    
@section('content')
    @include('components.header')
    @include('components.header-mini')
    
    @if (session()->has('message'))
    <div class="alert {{ Session::get('class') }}" style="align-text:center;">
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>    
    @endif
    <div id="main-container" class="container">
        <a href="/korean-cars" id="steering" style="text-decoration: none;color:#111">
            <div id="steering-wrapper">
                <div id="steering-reika"> </div>
                <div id="steering-gur"></div>
                <span id="steering-text">Автозапчасти на Hyundai</span>
            </div>
        </a>
       
        <div id="all-parts">
            <div id="all-parts-wrapper">
                
            </div>
            <span id="all-cars-text">Запчасти на все авто</span>
        </div>
        <a href="#" id="mobis" style="text-decoration: none;color:#111">
            <div id="mobis-wrapper"></div>
            <span id="mobis-text">Запчасти на китайские авто</span>
        </a>
        <a id="whatsapp-container">
            <img src="images/whatsapp72.png" alt="wa-big">
        </a>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection















