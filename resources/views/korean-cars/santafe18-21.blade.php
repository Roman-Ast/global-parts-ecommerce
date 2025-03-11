@extends('layouts.app')

@section('title', 'Santafe18-21')

@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="santafe1821-wrapper" class="container">
        <div id="santafe18-21container">
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21fr-bumper.png" alt="santafe18-21fr-bumper" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Передний бампер</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="86510S1510">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <div class="" id="main-img-container">
                <img src="/images/hyundai/santafe2023-red.png" alt="korean-car">
            </div>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-rearlight-outer-lh.png" alt="santafe18-21-rearlight-outer-lh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фонарь зад (наружн, lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="92401S1500">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/brake-disc.png" alt="brake-disk" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Тормозной диск</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="51712P2700">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-headlight.png" alt="santafe18-21-headlight" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>LED Фара</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="92101p6110">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-rearlight-inner-lh.png" alt="santafe18-21-rearlight-inner-lh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фонарь зад (внутр. lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="92403S1500">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-brake-pads.png" alt="santafe18-21-brake-pads" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Колодки торм. перед</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="58101P2A70">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-radiatorgrill.png" alt="santafe18-21-radiatorgrill" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Решетка радиатора</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="86350S1600">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/wheel-hub.png" alt="santafe18-21-wheelhub" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Ступица перед. колеса в сборе</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="51750S1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/shock-absorber-front.png" alt="santafe18-21-shock-absorber-front" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Амортизатор передний</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="54650S1BB0">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-bumper-grill.png" alt="santafe18-21bumpergrill" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Решетка бампера</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="86531S1600">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-bumper-rr.png" alt="santafe18-21bumper-rr" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Задний бампер (верх. часть)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="86611S1500">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-bumper-rr-low.png" alt="santafe18-21bumper-rr-low" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Задний бампер (нижн. часть)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="86650S1600">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/hyundai/santafe18-21-fr-arm-lh.png" alt="santafe18-21-fr-arm-lh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Рычаг перед левый</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="54500S1AA0">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="images/111.png" alt="santafe18-21-camshaft-sprocket-int" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Муфта VVTi (впуск)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai">
                <input type="hidden" name="partnumber" value="243502m800">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
        </div>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection