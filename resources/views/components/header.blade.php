<div id="main-header" class="">
    <div id="logo-container">
        <a id="logo-wrapper" href="/">
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
        <div id="sign-in-button-container">
            <button id="sign-in-btn" class="btn btn-sm btn-secondary">Войти</button>
        </div>

        <div id="sign-up-button-container">
            <button id="sign-up-btn" class="btn btn-sm btn-dark">Регистрация</button>
        </div>
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
               