@extends('layouts.app')

@section('title', 'K5 (20-23)')

@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="santafe1821-wrapper" class="container">
        <div id="santafe18-21container">
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-fr-bumper.png" alt="k5-20-23-fr-bumper" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Передний бампер</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86511L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <div class="" id="main-img-container">
                <img src="/images/hyundai/kiak5-20-23.png" alt="korean-car">
            </div>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-rear-lamp-rh.png" alt="k5-20-23-rear-lamp-rh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фонарь зад (rh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92402L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/brake-disc.png" alt="brake-disk" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Тормозной диск</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92102L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-headlamp-rh.png" alt="k5-20-23-headlamp-rh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фара передняя (rh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92101p6110">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-rearlamp-central.png" alt="k5-20-23-rearlamp-central" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фонарь задний центр</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92409L2040">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/santafe18-21-brake-pads.png" alt="santafe18-21-brake-pads" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Колодки торм. перед</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="58101-L0A10">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-radiateor-grill.png" alt="k5-20-23-radiateor-grill" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Решетка радиатора</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86351L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/wheel-hub.png" alt="santafe18-21-wheelhub" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Ступица перед. колеса в сборе</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="51730L1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/shock-absorber-front.png" alt="santafe18-21-shock-absorber-front" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Амортизатор передний</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="54650L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-bumper-grill.png" alt="k5-20-23-bumper-grill" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Решетка радиатора</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86531L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-foglamp-grill-rh.png" alt="k5-20-23-foglamp-grill-rh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Оправа ПТФ (rh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86542L2310">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-radiator.png" alt="sonata19-23-radiator" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Радиатор</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="25310L2230">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/santafe18-21-fr-arm-lh.png" alt="sonata19-23-fr-arm-lh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Рычаг перед левый</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="54500L1100">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/k5-20-23-fender-lh.png" alt="k5-20-23-fender-lh" class='santafe18-21-item-img' style="width: 70px">
                <div class="santafe18-21-item-desc">
                    <i>Крыло перед (lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="66310L2000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
        </div>

        <div id="cant-search-part-wrapper">
            <div id="cant-search-part">
                Не нашли то, что искали? Не беда, напишите или позвоните нашим менеджерам прямо сейчас, подберем и найдем в кратчайшие сроки!
                <div id="finger-down">
                    <img src="/images/finger-down-38-red.png">
                </div>
            </div>
        </div>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection