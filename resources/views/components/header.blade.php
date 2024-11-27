<div id="main-header" class="">
    <div id="main-header-wrapper" class="container">

        <a id="logo-container" href="/">
            <img src="/images/logo1.png" alt="main-logo" id="logo-img">
        </a>

        <div id="search-bar-wrapper">
            <form action="/getCatalog" method="GET " enctype="multipart/form-data" id="search-bar-container">
                <div id="input-searchbtn-wrapper">
                    <div id="search-button-container">
                        <button type="submit" class="btn" id="search-btn"><img src="/images/lupa-24.png"></button>
                    </div>
                    <div class="input-group input-group">
                        <input type="text" name="partNumber" id="searchBarInput" class="form-control" placeholder="введите номер детали" required>
                    </div>
                </div>
                <!--<div id="searchOptions">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="inlineCheckbox1" value="inStockAndToOrder" checked name="searchType">
                        <label class="form-check-label" for="inlineCheckbox1">в наличии и на заказ</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="inlineCheckbox2" value="onlyInStock" name="searchType">
                        <label class="form-check-label" for="inlineCheckbox1">только в наличии</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="inlineCheckbox3" value="onlyToOrder" name="searchType">
                        <label class="form-check-label" for="inlineCheckbox2">только на заказ</label>
                    </div>
                </div>-->
            </form>
        </div>

        <div id="cart-wrapper">
            <a href="/cart"><img src="/images/cart-36.png" alt="корзина" id="cart-big-img"></a>
                    
            @if (session()->has('cart') && session()->get('cart')->count() != 0)
                <div id="header-cart-qty">кол-во: {{ session()->get('cart')->count() }}</div>
                <div id="header-cart-sum">сумма: {{ number_format(session()->get('cart')->total(), 0, '.', ' ') }} T</div>
            @endif
        </div>
        <div id="auth-buttons-container">
            @if (Route::has('login'))
                @auth
                    <div id="user-data">
                    {{ auth()->user()->name }}
                        <a href="{{ route('logout') }}"><button class="btn btn-link">Выход</button></a>
                        @if (auth()->user()->user_role = "admin")
                            <a href="{{ route('admin_panel') }}"><button class="btn btn-link">Админка</button></a>
                        @endif
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
    <div id="dropdown-menu-container" class="container">
        @auth
            <nav class="nav">
                <a class="nav-link" href="/orders">Заказы</a>
                <a class="nav-link" href="/settlements">Взаиморасчеты</a>
                <a class="nav-link" href="/garage">Гараж</a>
            </nav>
        @endauth
    </div>
</div>


               