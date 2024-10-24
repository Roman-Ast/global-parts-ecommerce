<div id="main-header" class="">
    <div id="logo-container">
        <a id="logo-wrapper" href="/home">
            <img src="/images/main-logo.png" alt="main-logo" id="logo-img">
        </a>
    </div>

        <form action="/getCatalog" method="GET " enctype="multipart/form-data" id="search-bar-container">
            <div id="search-button-container">
                <button type="submit" class="btn btn-primary btn-lg" id="search-btn">Найти</button>
            </div>
            <div class="input-group input-group-lg">
                <input type="text" name="partNumber" id="searchBarInput" class="form-control" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-lg" placeholder="введите VIN код авто или партномер детали" required>
            </div>
        </form>
    

    <div id="auth-buttons-container">
        @if (Route::has('login'))
            @auth
                <div id="user-data">
                {{ auth()->user()->name }}
                    <a href="{{ route('logout') }}"><button class="btn btn-link">Выход</button></a>
                </div>
            @else   
                <a id="sign-in-button-container" href="{{ route('login') }}">
                    <button id="sign-in-btn" class="btn btn-sm btn-secondary">Войти</button>
                </a>
            
                <a id="sign-up-button-container" href="{{ route('register') }}">
                    <button id="sign-up-btn" class="btn btn-sm btn-dark">Регистрация</button>
                </a>
            @endif
        @endif
    </div>
</div>

<div id="dropdown-menu-container" class="container">
    <nav class="nav">
        <a class="nav-link active" aria-current="page" href="#">Активная</a>
        <a class="nav-link" href="#">Ссылка</a>
        <a class="nav-link" href="#">Ссылка</a>
        <a class="nav-link disabled">Отключенная</a>
    </nav>
</div>
               