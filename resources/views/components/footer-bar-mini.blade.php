<div id="footer-bar-mini">
    <div id="footer-bar-logo">
        <img src="/images/logo1.png" alt="main-logo" id="logo-img">
    </div>
    <div id="footer-bar-cart-container">
        <div id="cart-wrapper" class="header-item">
            <a href="/cart"><img src="/images/cart-36.png" alt="корзина" id="cart-big-img"></a>
                    
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
                <div id="header-cart-qty">{{ session()->get('cart')->count() }} шт</div>
                <div id="header-cart-sum">{{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} T</div>
            @else
                <div id="header-cart-qty"></div>
                <div id="header-cart-sum"></div>
            @endif
        </div>
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