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
      <!-- Блок ИИ помощника -->
      <!-- <section class="hero-section text-center p-4 bg-white">
        <div class="container">
          <h2 class="fw-bold mb-3 text-dark">
            🤖 ИИ-помощник по подбору запчастей (GPT 5.0)
            <span class="badge bg-warning text-dark ms-2" style="animation: blink 1.2s infinite;">НОВИНКА</span>
          </h2>
          <p class="lead text-muted mb-4">
            Введите данные авто или VIN и нужную деталь — наш ИИ найдет оригинальные номера и аналоги.
          </p>

          
          <div class="d-flex justify-content-center mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="searchModeSwitch">
              <label class="form-check-label" for="searchModeSwitch"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    title="Без VIN: точность ниже, но попробовать можно 👌">
                Поиск по VIN
              </label>
            </div>
          </div>

          
          <div id="form-no-vin" class="row g-2 justify-content-center">
            <div class="col-12 col-sm-8 col-md-6">
              <textarea id="ai-no-vin-input" class="form-control form-control-lg" rows="2"
                placeholder="Например: Hyundai Accent 2013 радиатор" required></textarea>
            </div>
            <div class="col-12 col-sm-auto">
              <button type="submit" class="btn btn-success btn-lg w-100" id="ai-no-vin-form-btn">🔍 Найти</button>
            </div>
          </div>

          
          <div id="form-vin" class="row g-2 justify-content-center d-none">
            <div class="col-12 col-sm-6 col-md-4">
              <input type="text" id="ai-vin-input" class="form-control form-control-lg"
                placeholder="Введите VIN (например: KMHCT41BDD...)" required>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
              <input type="text" id="ai-vin-part-input" class="form-control form-control-lg"
                placeholder="Какая запчасть нужна? (например: колодки)" required>
            </div>
            <div class="col-12 col-sm-auto">
              <button type="submit" class="btn btn-success btn-lg w-100" id="ai-vin-form-btn">🔍 Найти</button>
            </div>
          </div>

          
          <div id="ai-search-results" class="mt-4 text-start"></div>
        </div>
      </section> -->

      <section class="hero-section text-center p-4 bg-light mt-5">
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
                <small class="text-muted"><b>г.Астана</b><br>Работаем с 10:00 до 19:00. Ответим в WhatsApp даже в выходные 📦</small>
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

          <form class="row justify-content-center" action="/sparepart-request" method="POST" onsubmit="return validatePhone();">
            <div class="col-lg-8">
              <form class="p-4 border rounded-4 shadow-sm bg-white">
                @csrf
                <div class="mb-3">
                  <label for="vin" class="form-label fw-semibold">Винкод авто (VIN)</label>
                  <input type="text" name="vincode" class="form-control vin-selection-field" id="vin" placeholder="Например: KMH1234567890" required>
                </div>

                <div class="mb-3">
                  <label for="parts" class="form-label fw-semibold">Какие запчасти нужны</label>
                  <textarea class="form-control vin-selection-field" name="spareparts" id="parts" rows="3" placeholder="Например: фара, бампер, колодки..." required></textarea>
                </div>

                <div class="mb-3">
                  <label for="phone" class="form-label fw-semibold">Телефон (для обратной связи)</label>
                  <input type="tel" class="form-control vin-selection-field" name="phone" id="phone" placeholder="+7 (777) 123-45-67" required>
                  <div id="error" style="font-size:12px; font-style:italic; color:#d32f2f; margin-top:4px;"></div>
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
    </div>

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















