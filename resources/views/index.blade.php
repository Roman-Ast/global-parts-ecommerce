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
        <section class="hero-section text-center p-4 bg-light">
            <div class="container">
                <h1 class="display-5 fw-bold mb-3 text-dark">Запчасти с доставкой по Казахстану</h1>
                <p class="lead text-muted mb-4">Быстрый подбор по VIN. Оригиналы и аналоги в наличии и на заказ.</p>

                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="#vin-form" class="btn btn-success btn-lg px-4" id="scroll-to-form">Подобрать по VIN</a>
                <a href="#" class="btn btn-outline-success btn-lg px-4 wa-top-container">
                    WhatsApp
                </a>
                </div>

                <div class="mt-4">
                <small class="text-muted">Работаем с 10:00 до 19:00. Ответим в WhatsApp даже в выходные 📦</small>
                </div>
            </div>
        </section>
        <section class="steps-section py-5 bg-white mt-5">
          <div class="container">
            <h2 class="text-center fw-bold mb-4">Как заказать запчасти — всего 3 шага</h2>

            <div class="row text-center gy-4">
              <div class="col-md-4">
                <div class="p-4 border rounded-4 shadow-sm h-100">
                  <div class="fs-1 mb-3 text-primary">📸</div>
                  <h5 class="fw-semibold">1. Присылаете VIN или фото техпаспорта</h5>
                  <p class="text-muted mb-0">Отправляйте в WhatsApp или через форму на сайте</p>
                </div>
              </div>

              <div class="col-md-4">
                <div class="p-4 border rounded-4 shadow-sm h-100">
                  <div class="fs-1 mb-3 text-success">🔍</div>
                  <h5 class="fw-semibold">2. Мы подбираем нужные детали</h5>
                  <p class="text-muted mb-0">Высылаем фото, цену и срок доставки</p>
                </div>
              </div>

              <div class="col-md-4">
                <div class="p-4 border rounded-4 shadow-sm h-100">
                  <div class="fs-1 mb-3 text-danger">📦</div>
                  <h5 class="fw-semibold">3. Отправляем в ваш город</h5>
                  <p class="text-muted mb-0">Доставка по РК или самовывоз в Астане</p>
                </div>
              </div>
            </div>
          </div>
      </section>
      <section class="cta-form-section py-5 bg-light mt-5" id="vin-form">
        <div class="container">
          <div class="text-center mb-4">
            <h2 class="fw-bold">Не знаете номер детали?</h2>
            <p class="text-muted lead mb-0">Подберем по VIN — быстро и точно</p>
          </div>

          <form class="row justify-content-center" action="/sparepart-request" method="POST">
            <div class="col-lg-8">
              <form class="p-4 border rounded-4 shadow-sm bg-white">
                @csrf
                <div class="mb-3">
                  <label for="vin" class="form-label fw-semibold">Винкод авто (VIN)</label>
                  <input type="text" name="vincode" class="form-control" id="vin" placeholder="Например: KMH1234567890" required>
                </div>

                <div class="mb-3">
                  <label for="parts" class="form-label fw-semibold">Какие запчасти нужны</label>
                  <textarea class="form-control" name="spareparts" id="parts" rows="3" placeholder="Например: фара, бампер, колодки..." required></textarea>
                </div>

                <div class="mb-3">
                  <label for="phone" class="form-label fw-semibold">Телефон (для обратной связи)</label>
                  <input type="tel" class="form-control" name="phone" id="phone" placeholder="+7 (777) 123-45-67" required>
                  <p id="error" style="color:red;"></p>
                </div>

                <div class="mb-3">
                  <label for="note" class="form-label fw-semibold">Примечание (не обязательно)</label>
                  <input class="form-control" name="note" id="note" placeholder="Например: только оригинал...">
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                  <button type="submit" class="btn btn-success btn-lg" id="send-vin-search-btn">Получить подбор</button>
                </div>
              </form>
            </div>
          </form>

        </div>
      </section>

      <section class="cta-form-section py-5 bg-light mt-5">
        <div class="text-center mb-4">
          
          <!-- Заголовок -->
          <h2 class="fw-bold">Хиты продаж по моделям авто</h2>
          <p class="text-muted lead mb-0">Переходите сразу к спискам популярных моделей и находите нужное быстрее.</p>
            
          </p>

          <!-- Карточки -->
          <div class="row text-center gy-4">
            
            <!-- Hyundai / Kia -->
            <a href="/hyundai" class="category-card">
              <img src="/images/hyundai/Hyundai_KIA_log.png" alt="Hyundai/Kia">
              <h3>Модели Hyundai / Kia</h3>
              <p>Elantra, Tucson, Rio, Sorento и другие.</p>
            </a>

            <!-- Китайские авто -->
            <a href="#" class="category-card">
              <img src="/images/chinacars/chinese-logos.png" alt="Китайские авто">
              <h3>Модели китайских авто</h3>
              <p>Chery, Haval, Geely, JAC, Exeed и др.(в разработке)</p>
            </a>

            <!-- Все модели -->
            <a href="#" class="category-card">
              <img src="/images/car-from-parts.png" alt="Все авто">
              <h3>Все популярные авто</h3>
              <p>Выберите марку и модель для подбора запчастей. (в разработке)</p>
            </a>

          </div>
        </div>
      </section>

      
      <section class="why-us-section py-5 bg-white mt-5">
        <div class="container">
          <h2 class="text-center fw-bold mb-4">Почему нас выбирают более 1000 клиентов по Казахстану</h2>

          <div class="row gy-4">
            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-primary">🔧</div>
                <div>
                  <h6 class="fw-semibold mb-1">Подбор по VIN-коду</h6>
                  <p class="text-muted mb-0">Быстро, точно и удобно — не нужно искать самому</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-success">✅</div>
                <div>
                  <h6 class="fw-semibold mb-1">Оригиналы и качественные аналоги</h6>
                  <p class="text-muted mb-0">В наличии и под заказ напрямую от поставщиков</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-danger">📦</div>
                <div>
                  <h6 class="fw-semibold mb-1">Доставка по Казахстану</h6>
                  <p class="text-muted mb-0">Отправка за 1–3 дня, а по Астане — самовывоз</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-info">💬</div>
                <div>
                  <h6 class="fw-semibold mb-1">Консультации через WhatsApp</h6>
                  <p class="text-muted mb-0">Без звонков — пишите, как удобно</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-warning">💰</div>
                <div>
                  <h6 class="fw-semibold mb-1">Доступные цены</h6>
                  <p class="text-muted mb-0">Работаем напрямую с проверенными поставщиками</p>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-start p-3 border rounded-4 shadow-sm h-100">
                <div class="fs-2 me-3 text-secondary">🔄</div>
                <div>
                  <h6 class="fw-semibold mb-1">Гарантия и возврат</h6>
                  <p class="text-muted mb-0">Если запчасть не подошла — обмен или возврат</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section class="reviews-grid-section py-5 bg-white mt-5">
        <div class="container">
          <h2 class="text-center fw-bold mb-4">Что говорят наши клиенты</h2>

          <div class="row g-4">
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review1.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 1">
            </div>
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review2.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 2">
            </div>
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review3.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 3">
            </div>
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review4.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 3">
            </div>
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review5.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 3">
            </div>
            <div class="col-sm-6 col-lg-4 review-item">
              <img src="/images/reviews/review6.jpeg" class="img-fluid rounded-4 shadow-sm review-img" alt="Отзыв 3">
            </div>
            <!-- остальные 6 отзывов -->
          </div>
          <i>Больше отзывов можно посмотреть <a href="https://2gis.kz/astana/search/%D0%B0%D0%B2%D1%82%D0%BE%D0%B7%D0%B0%D0%BF%D1%87%D0%B0%D1%81%D1%82%D0%B8/firm/70000001080248919/71.428541%2C51.17667/tab/reviews?m=71.443112%2C51.129941%2F10.79" target="_blank">здесь</a><i>
        </div>
      </section>

        <!--<div id="main-blocks-container" class="bg-light">
            <div id="how-we-work">
                <div id="how-we-work-header">
                    Как заказать запчасти у нас - всего 3 шага!
                </div>
                <div id="how-we-work-content">
                    <div id="how-we-work-img-container">
                        <img src="/images/man-working-laptop.jpg" alt="man-working-laptop" id="how-we-work-img">
                    </div>
                    <div id="how-we-work-text-container">
                            <div>
                                1. <img src="/images/camera-36.png" alt="camera">  Вы присылаете VIN или фото техпаспорта (WhatsApp или через форму)
                            </div>
                            <div>
                                2. <img src="/images/lupa-36.png" alt="loop"> Подберем подходящие детали и отправим вам с фото, ценой и сроками доставки
                            </div>
                            <div>
                                3. <img src="/images/icons8-delivery-36.png" alt="delivery">  Доставляем в ваш город или выдаем в Астане
                            </div>
                    </div>
                </div>
            </div>
            <div id="cta-block">
                <div id="cta-block-header">
                    Не знаете номер детали? Подберем по VIN!
                </div>
                <div id="cta-block-content">
                    <div id="cta-img-container">
                        <img src="/images/car_vin_search2.png" alt="vin_search_car" id="cta-img-container-img">
                    </div>
                    <div id="cta-block-text-container">
                        <form id="feedback-form-wrapper" action="/sparepart-request" method="POST" class="form-control">
                            @csrf
                            
                            <div id="feedback-form-inner-wrapper">
                                <div class="mb-2">
                                    <label class="form-label">Винкод авто (VIN)</label>
                                    <input type="text" class="form-control" name="vincode" required minlength="7">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Запчасти, которые ищете</label>
                                    <textarea class="form-control" name="spareparts" placeholder="введите список запчастей..." required  minlength="4" rows="5"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-danger">Получить подбор</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div id="why-us-wrapper" class="bg-light">
            <div id="why-us-header">
                Почему нас выбирают более 1000 клиентов по Казахстану
            </div>
            <div id="why-us-content">
                <div id="why-us-text-container">
                    
                        <div>
                            <img src="/images/lupa-36.png" alt="searching">
                            Подбор по VIN-коду - быстро и точно
                        </div>
                        <div>
                            <img src="/images/icons8-cart-sp-36.png" alt="cart-spareparts">
                            Оригиналы и качественные аналоги в наличии и на заказ
                        </div>
                        <div>
                            <img src="/images/icons8-delivery-red-36.png" alt="cart-spareparts">
                            Отправка по Казахстану за 1-3 дня
                        </div>
                        <div>
                            <img src="/images/icons8-cell-phone-36.png" alt="cart-spareparts">
                            Консультация в WhatsApp без ожидания
                        </div>
                        <div>
                            <img src="/images/icons8-dollars-36.png" alt="cart-spareparts">
                            Доступные цены - работаем напрямую с поставщиками
                        </div>
                        <div>
                            <img src="/images/icons8-warranty-36.png" alt="cart-spareparts">
                            Гарантия и возврат, если запчасть не подошла
                        </div>
                    
                </div>
                <div id="why-us-img-container">
                    <img src="/images/why-us.jpg" alt="why-us" id="why-us-img">
                </div>
            </div>
        </div>

        <div id="review-wrapper" class="bg-light">
            <div id="review-header">
                Отзывы наших клиентов
            </div>
            <div id="review-content">
                
                    <div id="carouselExample" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                            <img src="images/reviews/review1.jpeg" class="d-block w-100" alt="...">
                            </div>
                            <div class="carousel-item">
                            <img src="images/reviews/review2.jpeg" class="d-block w-100" alt="...">
                            </div>
                            <div class="carousel-item">
                            <img src="images/reviews/review3.jpeg" class="d-block w-100" alt="...">
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                
            </div>
            <div id="review-footer">
                <i>Больше отзывов можно посмотреть <a href="https://2gis.kz/astana/search/%D0%B0%D0%B2%D1%82%D0%BE%D0%B7%D0%B0%D0%BF%D1%87%D0%B0%D1%81%D1%82%D0%B8/firm/70000001080248919/71.428541%2C51.17667/tab/reviews?m=71.443112%2C51.129941%2F10.79" target="_blank">здесь</a><i>
            </div>
        </div>

        <div id="popular-categories-wrapper" class="container bg-light ">
            <a href="/hyundai" id="popular-categories-korea" style="text-decoration:none;color:#111;">
                <div class="popular-categories-korea-card">
                    <img src="/images/hyundai/hyundai.svg" alt="hyundai" class="popular-categories-korea-card-img">
                </div>
                <div class="popular-categories-korea-card">
                    <img src="/images/kia-logo.png" alt="kia" class="popular-categories-korea-card-img">
                </div>
                <div class="popular-category-text-container ">
                    Запчасти Hyundai/Kia <i style="color: #0000EE;">(смотреть)</i>
                </div>
            </a>
            <div id="popular-categories-china">
                <div class="popular-categories-china-card">
                    <img src="/images/chinacars/chinese-logos.png" alt="china-cars" class="popular-categories-china-card-img w-100">
                </div>
                <div class="popular-category-text-container">
                    Запчасти на китайские авто
                </div>
            </div>
            <div id="popular-categories-others">
                <div class="popular-categories-others-card">
                    <img src="/images/car-from-parts.jpg" alt="other-cars" class="popular-categories-others-card-img w-100">
                </div>
                <div class="popular-category-text-container">
                    Запчасти на все автомобили
                </div>
            </div>
        </div>-->
    </div>

    <!--<a class="whatsapp-container">
        <img src="images/whatsapp72.png" alt="wa-big" style="cursor:pointer" title="При первом заказе через whatsapp скидка 5%">
        <div id="whatsapp-offer-wrapper">
            <div class="whatsapp-offer" id="whatsapp-offer-2">Напиши нам прямо сейчас, быстрый подбор по VIN!</div>
        </div>
    </a>-->

    <div id="review-modal" style="display: none;">
      <div class="modal-overlay"></div>
      <div class="modal-content">
        <span class="modal-close">&times;</span>
        <img src="" alt="Просмотр отзыва" id="modal-img">
        <div class="modal-nav">
          <span class="prev">&larr;</span>
          <span class="next">&rarr;</span>
        </div>
      </div>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection















