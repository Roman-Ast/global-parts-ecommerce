@extends('layouts.app')

@section('title', 'Hyundai/Kia')

@section('content')
@include('components.header')
@include('components.header-mini')

<div id="korean-cars-wrapper" class="container">
    
    <h3 id="korean-cars-wrapper-header">Автозапчасти Hyundai</h3>

    <div id="korean-cars-container">
        <a href="hyundai/santafe20-24" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/santafe2023-red.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Santafe
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="hyundai/sonata19-23" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/sonata19-23.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Sonata(`19-`23)
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="hyundai/k520-23" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/kiak5-20-23.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Kia K5 (`20-`23)
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="/hyundai/sportage21-25" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/sportage23.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Kia Sportage
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/tucson-newred.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Tucson
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/sonata-new.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Sonata
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/elantra-new.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Elantra
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/seltos-new.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Kia Seltos
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