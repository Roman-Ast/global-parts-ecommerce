@extends('layouts.app')

@section('title', 'Китайские авто')

@section('content')
@include('components.header')
@include('components.header-mini')

<div id="korean-cars-wrapper" class="container">
    
    <h3 id="korean-cars-wrapper-header" class="fw-semibold">Автозапчасти на Китайские авто</h3>

    <div id="korean-cars-container">
        <a href="china/chery-tigo-7-pro" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="images/chinacars/chery-tiggo7.webp" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Chery Tiggo 7 Pro
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/chery-tigo-2-pro" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/Chery_Tiggo_2_Pro.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Chery Tiggo 2 Pro
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/haval-h-6" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/haval-h6.webp" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Haval H6
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/haval-m6" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/haval-m6.jpg" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Haval M6
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/geely-coolray" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/coolray-red.webp" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Geely Coolray
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/jetour-x-90-plus" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/jetour-x90plus.png" alt="jetour-x90plus">
            </div>
            <div class="korean-cars-item-header">
                Jetour X 90 Plus
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/geely-atlas" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/geely-atlas.webp" alt="Geely Atlas">
            </div>
            <div class="korean-cars-item-header">
                Geely Atlas
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="china/tank-300" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/chinacars/tank-300.png" alt="tank-300">
            </div>
            <div class="korean-cars-item-header">
                Tank 300
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <div id="cant-search-part-wrapper">
            <div id="cant-search-part">
                Не нашли свой авто? Не беда, напишите или позвоните нашим менеджерам прямо сейчас, подберем и найдем в кратчайшие сроки!
                <div id="finger-down">
                    <img src="/images/finger-down-38-red.png">
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.footer-bar-mini')
@include('components.footer')
@endsection