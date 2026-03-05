@extends('layouts.app')

@section('title', 'Hyundai Sonata (19-23)')

@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="santafe1821-wrapper" class="container">
        <div id="santafe18-21container">
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-fr-bumper.png" alt="sonata19-23-fr-bumper.png" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Передний бампер</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86510L1010">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <div class="" id="main-img-container">
                <img src="/images/hyundai/sonata19-23.png" alt="korean-car">
            </div>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-rearlamp-lh.png" alt="sonata19-23-rearlamp-lh.png" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фонарь зад (наружн, lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92401L1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/brake-disc.png" alt="brake-disk" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Тормозной диск</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="51712-L1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-headlamp-lh.png" alt="sonata19-23-headlamp-lh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Фара передняя (lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92101p6110">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-mirror-rh.png" alt="sonata19-23-mirror-rh" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Зеркало з/в (rh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="92403S1500">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/santafe18-21-brake-pads.png" alt="santafe18-21-brake-pads" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Колодки торм. перед</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="58101L1A00">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-radiator-grill.png" alt="sonata19-23-radiator-grill" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Решетка радиатора</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="863A0L1390">
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
                <input type="hidden" name="partnumber" value="54650L1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-rr-bumper-bottom.png" alt="sonata19-23-rr-bumper-bottom" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Нижняя часть бампера</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86512L1000">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-rr-bumper.png" alt="sonata19-23-rr-bumper" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Задний бампер (верх. часть)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86610L1010">
                <button type="submit" class="btn btn-sm btn-link car-form-btn">Узнать цену</button>
            </form>
            <form class="santafe18-21container-item" method="post" action="{{ route('getPart') }}" target="_blank">
                @csrf
                <img src="/images/hyundai/sonata19-23-radiator.png" alt="sonata19-23-radiator" class='santafe18-21-item-img'>
                <div class="santafe18-21-item-desc">
                    <i>Радиатор</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="25310L1700">
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
                <img src="/images/hyundai/sonata19-23-locker-fr.png" alt="sonata19-23-locker-fr" class='santafe18-21-item-img' style="width: 70px">
                <div class="santafe18-21-item-desc">
                    <i>Подкрылок перед (lh)</i>
                </div>
                <input type="hidden" name="brand" value="Hyundai-Kia">
                <input type="hidden" name="partnumber" value="86811l1000">
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