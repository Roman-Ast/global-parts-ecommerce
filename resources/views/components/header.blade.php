<div id="main-header">
    <div id="main-header-wrapper" class="container">
        <a id="logo-container" href="/" class="header-item">
            <img src="/images/logo1.png" alt="main-logo" id="logo-img">
        </a>
        <div id="search-bar-wrapper" class="header-item">
            <form action="/getCatalog" method="GET " enctype="multipart/form-data" id="search-bar-container">
                <div id="input-searchbtn-wrapper">
                    <div id="search-button-container">
                        <button type="submit" class="btn btn-lg" id="search-btn"><img src="/images/lupa-24.png"></button>
                    </div>
                    <div class="input-group input-group-lg">
                        <input type="text" name="partNumber" id="searchBarInput" class="form-control" placeholder="введите номер детали" required>
                    </div>
                </div>
                <div id="searchOptions">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="stock_or_order" name="only_on_stock">
                        <label class="form-check-label" for="flexSwitchCheckDefault">Только в наличии</label>
                    </div>
                </div>
            </form>
        </div>

        <div id="cart-wrapper" class="header-item">
            <a href="/cart"><img src="/images/cart-36.png" alt="корзина" id="cart-big-img"></a>
                    
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
                <div id="header-cart-qty">{{ session()->get('cart')->count() }} шт</div>
                <div class="header-cart-sum">{{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} T</div>
            @else
                <div id="header-cart-qty"></div>
                <div class="header-cart-sum"></div>
            @endif
        </div>
        <div id="auth-buttons-container" class="header-item">
            @if (Route::has('login'))
            @auth
            <div class="dropdown">
                <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                    {{ auth()->user()->name }}
                </button>
                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                  <li><a class="dropdown-item" href="/orders">Заказы</a></li>
                  <li><a class="dropdown-item" href="/settlements">Взаиморасчеты</a></li>
                  <li><a class="dropdown-item" href="/garage">Гараж</a></li>
                  @if (auth()->user()->user_role == "admin")
                    <li>
                        <a href="{{ route('admin_panel') }}" class="dropdown-item">Админка</a>
                    </li>
                  @endif
                  <li><a href="{{ route('logout') }}" class="dropdown-item">Выход</a></li>
                </ul>
            </div>
                @else   
                    <a id="sign-in-button-container" href="{{ route('login') }}">
                        <button id="sign-in-btn" class="btn btn-sm btn-link">Войти</button>
                    </a>
                    <a id="sign-up-button-container" href="{{ route('register') }}">
                        <button id="sign-up-btn" class="btn btn-sm btn-link">Регистрация</button>
                    </a>
                @endif
            @endif
        </div>
    </div>
    <div id="kaspi-wrapper">
        <div class="kaspi-item">
            <img src="/images/kaspi1.png" alt="kaspi1" class="kaspi-item-img">
        </div>
        <div class="kaspi-item">
            <img src="/images/kaspi-credit.webp" alt="kaspi-credit" class="kaspi-item-img">
        </div>
        <div class="kaspi-item">
            <img src="/images/kaspi-red.png" alt="kaspi-red" class="kaspi-item-img">
        </div>
        <div id="close-kaspi-ads">
            &times;
        </div>
    </div>
</div>



               