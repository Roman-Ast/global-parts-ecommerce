<div id="footer-bar-mini" style="z-index:1500;">
    
    {{-- Главная --}}
    <div id="footer-bar-logo" style="flex: 1; text-align: center;">
        <a href="/" style="text-decoration: none; color: #333; display: flex; flex-direction: column; align-items: center; gap: 2px;">
            <img src="/images/logo1.png" alt="main-logo" id="logo-img" style="height: 55px; width: auto; object-fit: contain;">
        </a>
    </div>

    {{-- Корзина (твой ID footer-bar-cart-container) --}}
    <div id="footer-bar-cart-container" style="flex: 1; text-align: center;">
        <div id="cart-wrapper" class="header-item">
            <a href="/cart" style="text-decoration: none; color: #333; display: flex; flex-direction: column; align-items: center; gap: 2px; position: relative;">
                <img src="/images/cart-36.png" alt="корзина" id="cart-big-img" style="height: 22px; width: auto;">
                
                @if (session()->has('cart') && session()->get('cart')->count() != 0)
                    {{-- Красный бейдж количества --}}
                    <span id="header-cart-qty" style="position: absolute; top: -5px; right: 15%; background: #ff4757; color: #fff; border-radius: 50%; width: 16px; height: 16px; font-size: 9px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #fff;">
                        {{ session()->get('cart')->count() }}
                    </span>
                    <span class="header-cart-sum" style="font-size: 9px; font-weight: bold; color: #007bff; white-space: nowrap;">
                        {{ number_format(session()->get('cart')->totalWithMargine(), 0, '.', ' ') }} T
                    </span>
                @else
                    <span style="font-size: 10px; font-weight: bold;">Корзина</span>
                    <div id="header-cart-qty"></div>
                    <div class="header-cart-sum"></div>
                @endif
            </a>
        </div>
    </div>

    {{-- Профиль / Авторизация (твой ID auth-buttons-container) --}}
    <div id="auth-buttons-container" class="header-item" style="flex: 1; text-align: center;">
        @if (Route::has('login'))
            @auth
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle text-decoration-none" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false" style="color: #007bff; display: flex; flex-direction: column; align-items: center; gap: 2px;">
                        <i class="fas fa-user-circle" style="font-size: 20px;"></i>
                        <span style="font-size: 10px; font-weight: bold; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ auth()->user()->name }}
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mb-2 rounded-4" aria-labelledby="dropdownMenuButton1" style="font-size: 14px;">
                        <li><a class="dropdown-item py-2" href="/orders"><i class="fas fa-box me-2"></i>Заказы</a></li>
                        <li><a class="dropdown-item py-2" href="/garage"><i class="fas fa-car me-2"></i>Гараж</a></li>
                        @if (auth()->user()->user_role == "admin")
                            <li><hr class="dropdown-divider"></li>
                            <li><a href="{{ route('admin_panel') }}" class="dropdown-item text-danger fw-bold">Админка</a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li><a href="{{ route('logout') }}" class="dropdown-item"><i class="fas fa-sign-out-alt me-2"></i>Выход</a></li>
                    </ul>
                </div>
            @else   
                <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                    <i class="fas fa-sign-in-alt" style="font-size: 20px; color: #6c757d;"></i>
                    <div class="d-flex gap-1">
                        <a id="sign-in-button-container" href="{{ route('login') }}" style="text-decoration: none; font-size: 10px; font-weight: bold; color: #007bff;">Войти</a>
                        <span style="font-size: 10px; color: #ccc;">|</span>
                        <a id="sign-up-button-container" href="{{ route('register') }}" style="text-decoration: none; font-size: 10px; font-weight: bold; color: #007bff;">Рег</a>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>