<div id="main-header" class="">
    <div id="logo-container">
        <a id="logo-wrapper" href="/home">
            <img src="/images/logo.jpg" alt="main-logo" id="logo-img">
        </a>
    </div>

    <div id="search-bar-wrapper">
        <form action="/getCatalog" method="GET " enctype="multipart/form-data" id="search-bar-container">
            <div id="input-searchbtn-wrapper">
                <div id="search-button-container">
                    <button type="submit" class="btn btn-primary btn-lg" id="search-btn">Найти</button>
                </div>
                <div class="input-group input-group-lg">
                    <input type="text" name="partNumber" id="searchBarInput" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg" placeholder="введите VIN код авто или партномер детали" required>
                </div>
            </div>
            <div id="searchOptions">
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
            </div>
        </form>
    </div>

    <div id="cart-wrapper">
        <a href="/cart"><img src="/images/cart-main.png" alt="корзина" id="cart-big-img"></a>
        @if (session()->has('cart') && session()->get('cart')->count() != 0)
            <div id="cart-qty">{{ session()->get('cart')->count() }}</div>
            <div id="cart-sum">{{ session()->get('cart')->total() }} T</div>
        @endif
        
    </div>

    <div id="auth-buttons-container">
        @if (Route::has('login'))
            @auth
                <div id="user-data">
                {{ auth()->user()->name }}
                    <a href="{{ route('logout') }}"><button class="btn btn-link">Выход</button></a>
                </div>
            @else   
                <a id="sign-in-button-container" href="{{ route('login') }}">
                    <button id="sign-in-btn" class="btn btn-sm btn-info">Войти</button>
                </a>
            
                <a id="sign-up-button-container" href="{{ route('register') }}">
                    <button id="sign-up-btn" class="btn btn-sm btn-light">Регистрация</button>
                </a>
            @endif
        @endif
    </div>
</div>

<div id="dropdown-menu-container" class="container">
    <nav class="nav">
        <a class="nav-link" href="#">Заказы</a>
        <a class="nav-link" href="#">Взаиморасчеты</a>
        <a class="nav-link" href="#">Гараж</a>
    </nav>
</div>
               