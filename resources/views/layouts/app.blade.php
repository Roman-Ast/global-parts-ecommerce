<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title')</title>
        <link rel="icon" href="{{ URL::asset('images/logo1.png') }}">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
        <link href="{{ URL::asset('css/user/login.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/user/login-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/user/registration-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/user/verify-email.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/header.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/header-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/partSearchRes-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/partSearchRes.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/main.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/main-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/cart.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/cart-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/orders.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/orders-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/admin.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/garage.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/settlements-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/settlements.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/searchCatalog.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/searchCatalog-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/registerForm.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/footer-bar-mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/footer.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/components/notfound.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/korean-cars/index.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/korean-cars/index.mini.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/korean-cars/santafe18-21.css') }}" rel="stylesheet">
        <link href="{{ URL::asset('css/korean-cars/santafe18-21-mini.css') }}" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

        <!-- Styles -->
        <style>

        </style>

    </head>
    <body>

        @yield('content')
        <div id="shadow">
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" style="width: 6rem; height: 6rem;" role="status">
                <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div id="loading" class="d-flex justify-content-center mt-5 pouring">
                Выполняется проценка складов... это может занять несколько секунд, пожалуйста ожидайте...
            </div>
        </div>
        <div id="shadow-main">
            <div id="modal-qr" class="container">
                <img src="images/wa-qr.jpeg" alt="wa-qr">
                Для перехода в Whatsapp отсканируйте QR-код с камеры мобильного телефона
            </div>
        </div>
        <div id="main-mini-shadow" style="position: fixed;width:100%;height:100vh;top:0;left:0;"></div>

    <div id="side-bar-right-mini" style="position:fixed;">
        <div id="side-bar-right-mini-close-wrapper">
            <div id="side-bar-right-mini-close-container">
                <img src="/images/close-x-24.png" alt="close-x">
            </div>
        </div>
        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    Контакты
                </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <div id="side-bar-right-min-contacts">
                <div class="side-bar-right-min-contacts-item">
                    <img src="/images/phone24.png" alt="phone">
                    <a href="tel:+77087172549" style="text-decoration: none; color:#111; font-size: 0.8em; margin-left: 10px;">+77087172549</a>
                </div>
                <div class="side-bar-right-min-contacts-item">
                    <img src="/images/whatsapp24.png" alt="wa">
                    <a href="https://wa.me/+77087172549?text=Здравствуйте%20пишу%20вам%20с%20сайта!" style="text-decoration: none; color:#111;font-size: 0.8em; margin-left: 10px;">+77087172549</a>
                </div>
                <div class="side-bar-right-min-contacts-item">
                    <img src="/images/phone24.png" alt="phone">
                    <a href="tel:+77058443458" style="text-decoration: none; color:#111; font-size: 0.8em; margin-left: 10px;">+77058443458</a>
                </div>
                <div class="side-bar-right-min-contacts-item">
                    <img src="/images/whatsapp24.png" alt="wa">
                    <a href="https://wa.me/+77058443458?text=Здравствуйте%20пишу%20вам%20с%20сайта!" style="text-decoration: none; color:#111;font-size: 0.8em; margin-left: 10px;">+77058443458</a>
                </div>
                <div class="side-bar-right-min-contacts-item">
                    <img src="/images/adress.png" alt="address">
                    <a href="https://go.2gis.com/8z5h5" target="_blank" style="text-decoration: none;color:#111; font-size: 0.8em; margin-left: 10px;">
                        Астана, мкрн Целинный 5/1 <i>(2gis)</i>
                    </a>
                </div>
            </div>
                </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    Оформление заказов
                </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    После оформления заказа с Вами свяжется наш менеджер и уточнит детали. Если товар в наличии в г.Астана, то доставка со склада в пункт выдачи заказов (ПВЗ) занимает <strong>от 1 до 2,5 часов</strong>. По городу можем отправить запчасти через яндекс/индрайвер за отдельную плату по их тарифу. Если же позиция(-ии) заказные, время поставки в ПВЗ указано на сайте при поиске на каждую позицию отдельно, после поступления запчастей в ПВЗ в г.Астана, запчасти можно забрать как самовывозом так и отправкой через агрегаторы такси, если же Вы с другого города, после поступления в ПВЗ в г. Астана также можем отправить через такси (Indrive), можете вызвать курьера любой траспортной компании.
                </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    Возврат/обмен запчастей
                </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    Возврат товара надлежащего качества возможен в течение <strong>14 дней</strong> с момента поступления в пункт выдачи заказов (ПВЗ) в г. Астана, при условии сохранения товарного вида, упаковки и полной комплектности, а так же без следов установки (при условии, что не было оговорено заранее, что позиция является невозвратной). Возврат товара по гарантии возможен при наличии заказ-наряда с автосервиса, где происходила установка, и акта дефектовки с печатью автосервиса и подписью мастера и директора.
                </div>
                </div>
            </div>
        </div>
    </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="{{ URL::asset('js/jquery.min.js') }}"></script>
        <script src="{{ URL::asset('js/main.js') }}"></script>
        <script src="{{ URL::asset('js/admin.js') }}"></script>
        <script src="{{ URL::asset('js/korean-cars.js') }}"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    </body>
</html>















