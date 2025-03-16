<div id="main-header-mini">
    <div id="search-bar-wrapper" class="header-item">
        <form action="/getCatalog" method="GET " enctype="multipart/form-data" id="search-bar-container">
            <div id="input-searchbtn-wrapper">
                <div id="search-button-container">
                    <button type="submit" class="btn btn-light" id="search-btn"><img src="/images/lupa-24.png"></button>
                </div>
                <div class="input-group">
                    <input type="text" name="partNumber" id="searchBarInput" class="form-control" placeholder="введите номер детали" required>
                </div>
                <div id="three-dots-wrapper">
                    <img src="/images/three-dots.png" alt="three-dots">
                </div>
            </div>
            <div id="searchOptions">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="stock_or_order" name="only_on_stock">
                    <label class="form-check-label" for="flexSwitchCheckDefault" style="font-size: 0.9em;">Только в наличии</label>
                </div>
            </div>
        </form>
    </div>
    
</div>