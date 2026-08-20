            {{-- Разбито на 4 персистентных блока (searched-number / crosses-in-office /
                 crosses-on-stock / crosses-to-order) — каждый рендерится РОВНО ОДИН РАЗ
                 (шапка + контейнер), а сами строки внутри теперь отдельные @include
                 (partials/items/*.blade.php), чтобы прогрессивная подгрузка
                 (searchRosskoFragment/searchRestFragment) могла досыпать новые строки
                 В ТОТ ЖЕ контейнер, а не рендерить блок целиком заново — раньше это
                 давало задвоенные заголовки секций при двух отдельных фрагментах.
                 d-none, если сейчас (при данном рендере) в секции пусто — JS снимает
                 класс, когда в контейнер прилетает первая строка. --}}

            <div id="section-searched-number" class="{{ count($finalArr['searchedNumber']) > 0 ? '' : 'd-none' }}">
                <div class="searchResForRequestPartNumber">
                    <div class="searchResForRequestPartNumberHeader">
                        Запрошенный артикул
                    </div>
                </div>

                <div id="requestPartNumberContainer">
                    <input type="hidden" value="{{ $finalArr['originNumber'] }}" id="originNumber">
                    @include('partials.items.searchedNumber', ['items' => $finalArr['searchedNumber']])
                    <div id="show-other-items" counter="10">
                        <a href="###">Показать еще 10</a>
                    </div>
                </div>
            </div>

            <div id="section-crosses-in-office" class="{{ !empty($finalArr['crosses_in_office']) ? '' : 'd-none' }}">
                <div class="searchResForRequestPartNumber">
                    <div class="searchResForRequestPartNumberHeader">
                        Аналоги в наличии в офисе
                    </div>
                </div>

                <div id="crossesContainer-in-office">
                    @include('partials.items.crossesInOffice', ['items' => $finalArr['crosses_in_office']])
                </div>
            </div>

            <div id="section-crosses-on-stock" class="{{ !empty($finalArr['crosses_on_stock']) ? '' : 'd-none' }}">
                <div class="searchResForRequestPartNumber">
                    <div class="searchResForRequestPartNumberHeader">
                        Аналоги в наличии на складе
                    </div>
                </div>

                <div id="crossesContainer-on-stock">
                    @include('partials.items.crossesOnStock', ['items' => $finalArr['crosses_on_stock']])
                </div>
            </div>

            <div id="section-crosses-to-order" class="{{ count($finalArr['crosses_to_order']) > 0 ? '' : 'd-none' }}">
                <div class="searchResForRequestPartNumber">
                    <div class="searchResForRequestPartNumberHeader">
                        Аналоги на заказ
                    </div>
                </div>

                <div id="crossesContainer-to-order">
                    @include('partials.items.crossesToOrder', ['items' => $finalArr['crosses_to_order']])
                </div>
            </div>
