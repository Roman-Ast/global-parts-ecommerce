<div id="main-header-mini" style="background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
    
    {{-- Kaspi-бар (без изменений) --}}
    <div id="kaspi-wrapper" style="background: #212529; color: #fff; padding: 6px 15px; overflow: hidden; display: block;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="kaspi-item" style="display: flex; align-items: center; gap: 5px;">
                    <img src="/images/kaspi1.png" alt="kaspi1" class="kaspi-item-img" style="height: 14px; width: auto;">
                    <span style="font-size: 10px; font-weight: bold;">0-0-12</span>
                </div>
                <div style="width: 1px; height: 12px; background: rgba(255,255,255,0.3);"></div>
                <div class="kaspi-item" style="display: flex; align-items: center; gap: 5px;">
                    <img src="/images/kaspi-red.png" alt="kaspi-red" class="kaspi-item-img" style="height: 12px; width: auto;">
                    <span style="font-size: 10px; font-weight: bold;">Red</span>
                </div>
            </div>
            <div id="close-kaspi-ads" style="cursor: pointer; font-size: 1.2rem; line-height: 1; padding: 0 5px;">&times;</div>
        </div>
    </div>

    {{-- Логотип (чуть уменьшил отступ снизу) --}}
    <div style="display: flex; justify-content: center; padding-top: 15px; padding-bottom: 5px;">
        <a id="logo-container" href="/" style="display: block;">
            <img src="/images/logo1.png" alt="main-logo" id="logo-img" style="height: 55px; width: auto; object-fit: contain;">
        </a>
    </div>

    {{-- МАССИВНЫЙ ПОИСК --}}
    <div id="search-bar-wrapper" style="padding: 10px 15px 15px 15px;">
        <form action="/getCatalog" method="GET" id="search-bar-container" style="margin: 0;">
            
            {{-- Высота увеличена до 65px, рамка стала 3px --}}
            <div id="input-searchbtn-wrapper" style="display: flex; align-items: center; background: #fff; border: 3px solid #007bff; border-radius: 16px; padding: 5px 15px; height: 65px; box-shadow: 0 4px 12px rgba(0,123,255,0.15);">
                
                <div id="search-button-container" style="display: flex; align-items: center; margin-right: 12px;">
                    <button type="submit" class="btn p-0 border-0 bg-transparent" id="search-btn">
                        <img src="/images/lupa-24.png" alt="search" style="width: 24px; height: 24px;">
                    </button>
                </div>

                <div class="input-group" style="flex-grow: 1; border: none;">
                    <input type="text" name="partNumber" id="searchBarInput" 
                           class="form-control border-0 p-0 shadow-none" 
                           placeholder="Введите номер детали..." 
                           required
                           style="font-size: 18px; font-weight: 600; background: transparent; height: 100%; color: #333;">
                </div>

                {{-- Три точки --}}
                <div id="three-dots-wrapper" style="padding-left: 12px; border-left: 2px solid #eee; margin-left: 12px; height: 30px; display: flex; align-items: center;">
                    <img src="/images/three-dots.png" alt="menu" style="width: 22px; opacity: 0.6;">
                </div>
            </div>

            {{-- Опции --}}
            <div id="searchOptions" style="margin-top: 12px; display: flex; justify-content: center;">
                <div class="form-check form-switch d-flex align-items-center" style="min-height: auto; padding: 0; gap: 10px;">
                    <input class="form-check-input" type="checkbox" id="stock_or_order" name="only_on_stock" style="margin: 0; cursor: pointer; width: 2.5em; height: 1.2em;">
                    <label class="form-check-label" for="stock_or_order" style="font-size: 13px; color: #444; font-weight: 700; cursor: pointer;">
                        Только в наличии в Астане
                    </label>
                </div>
            </div>
        </form>
    </div>
</div>