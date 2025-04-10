@extends('layouts.app')

@section('title', 'Главная')
    
@section('content')
    @include('components.header')
    @include('components.header-mini')
    
    @if (session()->has('message'))

        @if(Session::get('class') == 'alert-success')
            <div class="alertion-success">
                <div style="display:flex;justify-content:flex-end;" class="close-flash">
                        &times;
                </div>
                {{ Session::get('message') }}
            </div>
         @endif      
            
    @endif

    <div id="main-container" class="container">

        

        <form id="feedback-form-wrapper" action="/sparepart-request" method="POST" class="form-control">
            @csrf
            <div class="mb-3" id="feedback-form-close-container" status="close">
                <strong><i>Отправьте нам запрос...</i></strong>
                <img src="/images/plus-24.png" alt="close-open-form">
            </div>
            <div id="feedback-form-inner-wrapper">
                <div class="mb-2">
                    <label class="form-label">Винкод авто (VIN)</label>
                    <input type="text" class="form-control" name="vincode" required minlength="7">
                </div>
                <div class="mb-2">
                    <label class="form-label">Запчасти, которые ищете</label>
                    <textarea class="form-control" name="spareparts" placeholder="введите список запчастей..." required  minlength="4"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">Телефон</label>
                    <input class="form-control" name="phone" type="text" minlength="11" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Примечание</label>
                    <input class="form-control" name="note" type="text">
                </div>
                <button type="submit" class="btn btn-primary">Отправить запрос</button>
            </div>
        </form>
        
        <a href="/hyundai" id="steering" style="text-decoration: none;color:#111">
            <div id="steering-wrapper">
                <div id="steering-reika"> </div>
                <div id="steering-gur"></div>
                <buttom id="steering-text" class="btn btn-link">Автозапчасти на Hyundai <i>(смотреть)</i></buttom>
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
            <img src="images/whatsapp72.png" alt="wa-big" style="cursor:pointer" title="При первом заказе через whatsapp скидка 5%">
            <div id="whatsapp-offer-wrapper">
                <div class="whatsapp-offer" id="whatsapp-offer-1">При заказе через whatsapp - скидка 5% на первый заказ!</div>
                <div class="whatsapp-offer" id="whatsapp-offer-2">Напиши нам прямо сейчас, быстрый подбор по VIN!</div>
            </div>
        </a>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection















