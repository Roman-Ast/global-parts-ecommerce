<header id="main-header" class="fixed-top shadow bg-white">
    {{-- Твой старый ID для логики закрытия баннера --}}
    <div id="kaspi-wrapper" class="bg-dark text-white py-1 d-none d-md-block overflow-hidden" style="max-height: 40px;">
        <div class="container" style="max-width: 85%;">
            <div class="d-flex align-items-center justify-content-between h-100">
                <div class="d-flex align-items-center gap-4">
                    {{-- Класс kaspi-item и kaspi-item-img из твоего старого кода --}}
                    <div class="kaspi-item d-flex align-items-center gap-2">
                        <img src="/images/kaspi1.png" alt="kaspi1" class="kaspi-item-img" style="height: 20px; width: auto; object-fit: contain;">
                        <span class="fw-bold" style="font-size: 0.75rem;">Рассрочка 0-0-12</span>
                    </div>
                    <div class="vr opacity-50 my-1"></div>
                    <div class="kaspi-item d-flex align-items-center gap-2">
                        <img src="/images/kaspi-red.png" alt="kaspi-red" class="kaspi-item-img" style="height: 18px; width: auto; object-fit: contain;">
                        <span class="fw-bold" style="font-size: 0.75rem;">Kaspi Red</span>
                    </div>
                </div>
                {{-- Твой ID для закрытия рекламного блока --}}
                <div id="close-kaspi-ads" style="cursor:pointer; font-size: 1.2rem; line-height: 1;" class="px-2">&times;</div>
            </div>
        </div>
    </div>

    <nav id="main-header-wrapper" class="navbar navbar-expand-lg py-3">
        <div class="container" style="max-width: 85%;">
            
            {{-- Твой ID logo-container --}}
            <a id="logo-container" href="/" class="navbar-brand me-4">
                <img src="/images/logo1.png" alt="main-logo" id="logo-img" style="height: 75px; width: auto; object-fit: contain;">
            </a>

            {{-- Твой ID search-bar-wrapper --}}
            <div id="search-bar-wrapper" class="flex-grow-1 mx-lg-4">
                <form action="/getCatalog" method="GET" id="search-bar-container" class="position-relative">
                    <div id="input-searchbtn-wrapper" class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border border-2 border-primary">
                        <div id="search-button-container">
                            <button type="submit" class="btn btn-white border-0 ps-3" id="search-btn">
                                <img src="/images/lupa-24.png" alt="search" style="width: 20px;">
                            </button>
                        </div>
                        <input type="text" name="partNumber" id="searchBarInput" 
                               class="form-control border-0 py-2 shadow-none fw-medium" 
                               style="font-size: 1rem;"
                               placeholder="введите номер детали" required>
                        <div id="search-input-text-delete" class="d-flex align-items-center px-2" style="cursor:pointer; color:#ccc;">&times;</div>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Найти</button>
                    </div>
                    
                    <div id="searchOptions" class="form-check form-switch position-absolute start-0 mt-2 ms-4">
                        <input class="form-check-input" type="checkbox" id="stock_or_order" name="only_on_stock">
                        <label class="form-check-label fw-bold text-secondary" for="stock_or_order" style="font-size: 0.75rem;">
                            Товары локальных поставщиков
                        </label>
                    </div>
                </form>
            </div>

            {{-- Правая часть: Корзина и Профиль --}}
            <div class="d-flex align-items-center gap-3">
                <div id="cart-wrapper" class="header-item">
                    <a href="/cart" class="d-flex align-items-center text-decoration-none p-2 px-3 rounded-4 bg-light border border-2 position-relative shadow-sm">
                        <img src="/images/cart-36.png" alt="корзина" id="cart-big-img" style="height: 28px; width: auto;">
                        
                        @if (session()->has('cart') && session()->get('cart')->count() != 0)
                            <div id="header-cart-qty" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white" style="font-size: 0.65rem;">
                                {{ session()->get('cart')->count() }} шт
                            </div>
                            <div class="header-cart-sum ms-2 fw-bold text-primary d-none d-xl-block" style="font-size: 0.85rem;">
                                {{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} T
                            </div>
                        @else
                            <div id="header-cart-qty"></div>
                            <div class="header-cart-sum"></div>
                        @endif
                    </a>
                </div>

                <div id="auth-buttons-container" class="header-item">
                    @auth
                        <div class="dropdown">
                            <button class="btn btn-white border border-2 rounded-4 p-2 px-3 dropdown-toggle d-flex align-items-center gap-2 shadow-sm" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="fas fa-user small"></i>
                                </div>
                                <span class="fw-bold d-none d-md-block text-dark small">{{ auth()->user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 rounded-4 p-2 animate__animated animate__fadeInUp animate__faster">
                                <li><a class="dropdown-item rounded-3 py-2" href="/orders">Заказы</a></li>
                                <li><a class="dropdown-item rounded-3 py-2" href="/settlements">Взаиморасчеты</a></li>
                                <li><a class="dropdown-item rounded-3 py-2" href="/garage">Гараж</a></li>
                                @if (auth()->user()->user_role == "admin")
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a href="{{ route('admin_panel') }}" class="dropdown-item text-danger fw-bold">Админка</a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li><a href="{{ route('logout') }}" class="dropdown-item py-2">Выход</a></li>
                            </ul>
                        </div>
                    @else
                        <div id="auth-guest-buttons" class="d-flex align-items-center gap-1">
                            <a id="sign-in-button-container" href="{{ route('login') }}" class="text-decoration-none">
                                <button id="sign-in-btn" class="btn btn-link text-dark fw-bold btn-sm">Войти</button>
                            </a>
                            <a id="sign-up-button-container" href="{{ route('register') }}" class="text-decoration-none">
                                <button id="sign-up-btn" class="btn btn-primary rounded-pill px-3 py-2 fw-bold shadow-sm btn-sm">Регистрация</button>
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>