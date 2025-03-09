@extends('layouts.app')

@section('title', 'Корейские авто')

@section('content')
@include('components.header')
@include('components.header-mini')

<div id="korean-cars-wrapper" class="container">
    
    <h3 id="korean-cars-wrapper-header">Автозапчасти Hyundai</h3>

    <div id="korean-cars-container">
        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/santafe2023-red.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Santafe
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/sorento-new.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Kia Sorento
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/santafe-new.png" alt="korean-car">
            </div>
            <div class="korean-cars-item-header">
                Hyundai Santafe
            </div>
            <div class="korean-cars-item-description">
                
            </div>
        </a>

        <a href="" class="korean-cars-item">
            <div class="korean-cars-img-container">
                <img src="/images/hyundai/sportage-new.png" alt="korean-car">
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
    </div>
</div>

@include('components.footer-bar-mini')
@include('components.footer')
@endsection