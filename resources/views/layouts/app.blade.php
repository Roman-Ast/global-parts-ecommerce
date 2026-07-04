<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- 1. Системные мета-теги --}}
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- 2. SEO-данные --}}
        <title>@yield('title', 'Global Parts — Автозапчасти в Астане и по всему Казахстану')</title>
        <meta name="description" content="@yield('description', 'Купить запчасти по выгодным ценам в Астане...')">
        <meta name="keywords" content="запчасти астана, автозапчасти казахстан, вин код подбор...">
        
        @hasSection('canonical')
            @yield('canonical')
        @else
            <link rel="canonical" href="{{ url()->current() }}" />
        @endif
        {{-- 3. Твой главный монолитный файл стилей (Bootstrap + твои стили + Cache Busting) --}}
        <link href="{{ asset('css/master.css') }}?v=3" rel="stylesheet">

        {{-- 4. Фавиконка --}}
        <link rel="icon" type="image/png" href="https://shop.globalparts.kz/images/favicon-32x32.png">
        
        <link rel="preconnect" href="https://cdn.jsdelivr.net">

        @stack('styles')
        <!-- Yandex.Metrika counter -->
        <script type="text/javascript">
            (function(m,e,t,r,i,k,a){
                m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();
                for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
            })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=110381917', 'ym');
            ym(110381917, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/110381917" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
        <!-- /Yandex.Metrika counter -->
    </head>
    <body>
        @yield('content')

        <div id="shadow" class="position-fixed">
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
                <img src="/images/whatsapp_qr_77087172549.png" alt="wa-qr">
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

        <div id="social-media-container">
            <a href="https://www.instagram.com/global_parts_astana?igsh=Yjg2ZXphN2Rkb2E2" target="_blank" class="social-media-links">
                <img src="/images/color-instagram-48.png" alt="instagram">
            </a>
            <a href="https://go.2gis.com/BjDJe" target="_blank" class="social-media-links">
                <img src="/images/color-location1-48.png" alt="location">
            </a>
            <a href="tel:+77087172549" class="social-media-links">
                <img src="/images/color-phone-48.png" alt="location">
            </a>
            <a href="#"
                onclick="gtag('event', 'conversion', {'send_to': 'AW-16870370925/M3NOCJe9iqQcEO3ctew-'});"
                class="whatsapp-fixed-btn"
                aria-label="Написать в WhatsApp">
                <div class="pulse-ring"></div>
                <i class="bi bi-whatsapp"></i>
            </a>
        </div>

        <div id="social-media-container-open">
            <img src="/images/arrow-down-blue.png" id="social-media-container-close">
            <div class="whatsapp-fixed-btn-only-to-open-block" aria-label="Написать в WhatsApp">
                <div class="pulse-ring"></div>
                <i class="bi bi-whatsapp"></i>
            </div>
        </div>
    
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "AutoPartsStore",
            "name": "Global Parts Astana",
            "image": "https://shop.globalparts.kz/images/logo1.png",
            "@id": "https://shop.globalparts.kz",
            "url": "https://shop.globalparts.kz",
            "telephone": "+77087172549",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "мкрн Целинный 5/1, 2 этаж",
                "addressLocality": "Astana",
                "postalCode": "010000",
                "addressCountry": "KZ"
            },
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": 51.157169,
                "longitude": 71.450894
            },
            "openingHoursSpecification": {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],
                "opens": "10:00",
                "closes": "19:00"
            }
        }
        </script>
        
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="{{ URL::asset('js/master.js') }}?v=3"></script>

    @stack('scripts')

    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-16870370925"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'AW-16870370925', { 'page_path': location.pathname });
    </script>

    <script>
        (function () {
            var YANDEX_PHONE = '77475711906';
            var DEFAULT_PHONE = '77087172549';
            var STORAGE_KEY = 'traffic_source';

            var params = new URLSearchParams(window.location.search);
            var isYandexClick = params.has('yclid') || (params.get('utm_source') || '').toLowerCase() === 'yandex';
            if (isYandexClick) {
                sessionStorage.setItem(STORAGE_KEY, 'yandex');
            }

            function isYandex() {
                return sessionStorage.getItem(STORAGE_KEY) === 'yandex';
            }

            // 1. Подмена wa.me ссылок (сайдбар + фиксированная кнопка на мобильных)
            document.addEventListener('click', function (e) {
                var link = e.target.closest('a[href*="wa.me/"]');
                if (!link || !isYandex()) return;

                var url = new URL(link.href);
                var pathParts = url.pathname.split('/').filter(Boolean);
                if (pathParts.length > 0) {
                    pathParts[0] = YANDEX_PHONE;
                    url.pathname = '/' + pathParts.join('/');
                    link.href = url.toString();
                }

                // если видимый текст ссылки — это номер телефона, обновим и его
                if (/^\+?7\d{10}$/.test(link.textContent.trim())) {
                    link.textContent = '+' + YANDEX_PHONE;
                }

                if (typeof ym !== 'undefined') {
                    ym(110381917, 'reachGoal', 'whatsapp_click');
                }
            }, true);

            // 2. Подмена QR-кода в модалке для десктопа
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.whatsapp-fixed-btn, .wa-top-container')) return;
                if (!isYandex()) return;

                var qrImg = document.querySelector('#modal-qr img');
                if (qrImg) {
                    qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
                        + encodeURIComponent('https://wa.me/' + YANDEX_PHONE);
                }

                if (typeof ym !== 'undefined') {
                    ym(110381917, 'reachGoal', 'whatsapp_click');
                }
            }, true);
        })();
        </script>

    </body>
</html>
