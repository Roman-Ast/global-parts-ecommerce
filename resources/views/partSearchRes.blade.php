@extends('layouts.app')

@push('styles')
    <link href="{{ URL::asset('css/components/partSearchRes-mini.css') }}?v=9" rel="stylesheet">
    <link href="{{ URL::asset('css/components/partSearchRes.css') }}?v=7" rel="stylesheet">
@endpush

@section('title', 'Результат поиска')
   
@section('content')

@include('components.header')
@include('components.header-mini')

<div id="search-res-main-container" class="container">
    
    <div id="curtain-grey-searchpartres"></div>

    <div id="search-res-main-wrapper">
        <div id="search-res-filter">
            <div class="search-res-filter-item" id="filter-brands">
                <div class="search-res-filter-item-header">
                    БРЕНД
                </div>
                <div class="search-res-filter-item-content">
                    <ul>
                        {{-- id для JS: при прогрессивной загрузке $brands на сервере
                             всегда пуст (шелл рендерится до единого обращения к
                             поставщику) — список брендов собирается на лету из
                             data-brand каждой пришедшей строки, см. <script> внизу. --}}
                        <li id="brand-filter-list">
                            @foreach ($brands as $brand)
                                <div class="form-check">
                                    <input class="form-check-input brand-filter" type="checkbox" value="{{ $brand }}" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault" class="filter-brand-name">
                                        {{ $brand }}
                                    </label>
                                </div>
                            @endforeach
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    
        <div id="search-part-main-container">
            <div id="search-res-header">
                <div>Предложения для <span id="search-res-header-val">{{ $chosenBrand}} {{ $finalArr['originNumber'] }}</span></div>
                @auth
                @if(auth()->user()->user_role == "admin")
                    <div id="articles-hide-wrapper">
                        <i>скрыть артикула</i> <input type="checkbox" id="articles-hide">
                    </div>
                    {{-- Только для админа — прогресс прогрессивной подгрузки
                         (см. <script> в конце файла), обычным посетителям
                         это знать незачем. position:fixed — держится в углу
                         экрана независимо от скролла (не position:absolute,
                         тот прокрутился бы вместе со страницей — тут именно
                         "прилипание" к вьюпорту, оно и нужно).
                         Вид — как у стандартного bootstrap-тоста сайта (см.
                         showStatusChangeToast() в master.js, тот же приём:
                         bg-success/bg-danger + text-white + rounded + shadow,
                         без самого компонента .toast — он по умолчанию сам
                         прячется через пару секунд, а тут нужно, чтобы виджет
                         жил и обновлялся весь проход поиска).
                         Перетаскивание — makeSuppliersWidgetDraggable() ниже:
                         мышью на десктопе, зажатием (long-press) на мобиле,
                         чтобы можно было убрать виджет, если он что-то
                         закрывает. cursor:grab — сразу подсказывает, что
                         можно тащить. --}}
                    <div id="suppliers-progress-widget" style="position: fixed; top: 90px; right: 16px; z-index: 1050; text-align: right; cursor: grab; user-select: none;">
                        <div id="suppliers-responded-badge" class="text-white bg-success rounded shadow-sm px-3 py-2 mb-2" style="display: none; font-size: .85rem; font-weight: 600;"></div>
                        <div id="suppliers-failed-list" class="text-white bg-danger rounded shadow-sm px-3 py-2" style="display: none; font-size: .8rem;"></div>
                    </div>
                @endif
                @endauth
            </div>

            {{-- Прогресс-бар для ВСЕХ посетителей (не только админа) — пока
                 идёт прогрессивная подгрузка (см. <script> в конце файла),
                 показываем % по числу ответивших поставщиков, с иконкой-лупой
                 (Font Awesome, не вращается — только едет по краю
                 заполненной полосы вместе с ростом %), в фирменных цветах:
                 полоса — навy, лупа — красная на белом кружке. Прячется сам,
                 как только всё загрузилось. --}}
            <div id="customer-search-progress" class="my-3">
                <div class="small text-muted mb-1">
                    Ищем предложения… <span id="customer-search-progress-percent">0%</span>
                </div>
                <div style="position: relative; height: 8px; background: #e9ecef; border-radius: 999px;">
                    <div id="customer-search-progress-fill" style="height: 100%; width: 0%; background: #042D4D; border-radius: 999px; transition: width 0.4s ease;"></div>
                    <span id="customer-search-progress-icon"
                         style="position: absolute; top: 50%; left: 0%; width: 26px; height: 26px; transform: translate(-50%, -50%); transition: left 0.4s ease; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 50%; box-shadow: 0 1px 4px rgba(0,0,0,.35);">
                        <i class="fas fa-magnifying-glass" style="color: #DA251C; font-size: 13px;"></i>
                    </span>
                </div>
            </div>

            <div id="search-res-part-header">
                <div class="search-res-part-header-item">
                    Наименование
                </div>
                <div class="search-res-part-header-item">
                    Доставка
                </div>
                <div class="search-res-part-header-item">
                    Кол-во
                </div>
                <div class="search-res-part-header-item" style="text-align: center;">
                    Цена, ₸
                </div>
            </div>

            {{-- ВРЕМЕННО: прогрессивная загрузка. Скелет (шапки секций + пустые
                 контейнеры) рендерится сразу, контроллер (SparePartControllerTest::
                 getSearchedPartAndCrossesShell) при этом не делает НИ ОДНОГО запроса
                 к поставщикам. Дальше JS в конце файла параллельно тянет 2 фрагмента
                 (searchRosskoFragment/searchRestFragment — только строки, не целые
                 секции) и досыпает их в уже отрисованные контейнеры. Чтобы откатить —
                 см. git-историю этого файла до правки прогрессивной загрузки. --}}
            @include('partials.searchResultsBody', ['finalArr' => $finalArr])
            <nav aria-label="..." class="pagination-nav">
                <ul class="pagination pagination-sm">
                    <li class="page-item active">
                        <span class="page-link" page-num="1">1</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="2">2</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="3">3</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="4">4</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="5">5</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="6">6</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="7">7</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="8">8</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="9">9</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="10">10</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="11">11</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="12">12</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="13">13</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="14">14</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="15">15</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="16">16</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="17">17</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="18">18</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="19">19</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="20">20</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="21">21</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="22">22</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="23">23</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="24">24</span>
                    </li>
                    <li class="page-item">
                        <span class="page-link" page-num="25">25</span>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<div id="copy_text_wrapper">
    <button id="copy_text_btn" class="btn btn-primary">
        Копировать текст
    </button>
</div>

<textarea id="clipboard-buffer" style="position: absolute; left: -9999px;"></textarea>

{{-- Общая модалка для иконки "i" у поставщиков без встроенных данных (не Gerat).
     Одна на страницу — заполняется через AJAX по клику (CatalogController::partInfo),
     не рендерится заранее на каждую строку. Переиспользует те же CSS-классы,
     что и готовая модалка Gerat (.info-block и т.д.) — визуально то же самое,
     просто источник данных другой (parts_catalog вместо ответа поставщика). --}}
<div class="info-block" id="ajaxInfoBlock" style="display:none;">
    <div class="block-info-close-wrapper">
        <button type="button" class="btn-close block-info-item-close" aria-label="Close"></button>
    </div>
    <div class="info-block-pictures">
        <div class="info-block-pictures-name">
            <div class="info-block-pictures-name-header" id="ajaxInfoName"></div>
            <div class="info-block-pictures-name-brand"><span style="color:#bbb">Брэнд: </span><span id="ajaxInfoBrand"></span></div>
            <div class="info-block-pictures-name-article"><span style="color:#bbb">Артикул: </span><span id="ajaxInfoArticle"></span></div>
        </div>
        <div id="ajaxInfoCarousel" class="carousel slide carouselExampleControls" data-bs-ride="carousel">
            <div class="carousel-inner" id="ajaxInfoCarouselInner"></div>
            <button class="carousel-control-prev" type="button" data-bs-target="#ajaxInfoCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#ajaxInfoCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
    <div class="info-block-information">
        <ul class="nav nav-tabs" id="ajaxInfoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ajaxInfoDescription" type="button" role="tab">Описание</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ajaxInfoSpecs" type="button" role="tab">Характеристики</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#ajaxInfoApplicability" type="button" role="tab">Применимость</button>
            </li>
        </ul>
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="ajaxInfoDescription" role="tabpanel"></div>
            <div class="tab-pane fade" id="ajaxInfoSpecs" role="tabpanel"></div>
            <div class="tab-pane fade" id="ajaxInfoApplicability" role="tabpanel"></div>
        </div>
    </div>
</div>

@include('components.footer-bar-mini')
@include('components.footer')

{{-- ВРЕМЕННО: прогрессивная подгрузка. Скелет секций уже на странице (см.
     partials.searchResultsBody выше) — фрагменты теперь возвращают JSON с
     HTML ТОЛЬКО новых строк по каждой из 4 секций (searchedNumber/
     crossesInOffice/crossesOnStock/crossesToOrder), а не секцию целиком —
     это и позволяет Rossko и "остальным" садиться в ОДИН и тот же блок без
     задвоенных заголовков. Плавная посадка строк — обычный CSS-transition
     (см. <style> ниже), без сторонней JS-библиотеки: тут её негде было бы
     подключить так, чтобы не тащить лишнюю зависимость в кодовую базу, где
     таких библиотек сейчас вообще нет, а чистого fade+slide через
     transition для этого с избытком хватает. --}}
<script>
    (function () {
        const brand = {!! json_encode($chosenBrand ?? '') !!};
        const partnumber = {!! json_encode($finalArr['originNumber'] ?? '') !!};
        const guid = {!! json_encode($guid ?? '') !!};
        const rosskoNeedToSearch = {!! json_encode($rosskoNeedToSearch ?? false) !!};
        const onlyOnStock = {!! json_encode($onlyOnStock ?? false) !!};

        // Единый потолок ожидания на КАЖДОГО поставщика — не тюнинг под
        // каждого отдельно (у них и так разные таймауты внутри PHP: где-то
        // curl CONNECTION_TIMEOUT/TIMEOUT, где-то Http::timeout(15), где-то
        // SOAP вообще без явного лимита на весь вызов) — а страховка со
        // стороны браузера поверх всего этого: что бы ни случилось на
        // бэкенде у конкретного поставщика, этот шаг в цепочке не растянется
        // дольше 10 сек и не задержит следующие. Если понадобится — можно
        // сделать таймаут своим для медленных поставщиков персонально, но
        // начинать проще с одного числа на всех.
        const SUPPLIER_TIMEOUT_MS = 10000;

        // Автозакуп (Tradesoft) — реальный внешний хоп до чужого апстрима,
        // не наша локальная БД и не быстрый REST: сам Tradesoft может
        // ждать ответ от поставщика несколько секунд (см. 'timelimit' в
        // searchAvtozakup() на бэкенде — сейчас 12 сек), и если дать этому
        // шагу тот же бюджет 10 сек, что и остальным, — почти без запаса на
        // наш собственный сетевой круг + рендер. Раньше это и было причиной
        // "Автозакуп не отвечает" в бейдже: сервер честно досчитывал и
        // отдавал данные, просто уже ПОСЛЕ того, как браузер обрывал запрос
        // по таймауту. Роман подтвердил — по заказным позициям покупатель
        // готов подождать. 18 сек — с запасом сверх 12-секундного лимита на
        // стороне Tradesoft (сеть + рендер Blade), не впритык к нему.
        // Radle (radle.kz) — по их официальной доке таймаут должен быть
        // НЕ МЕНЬШЕ 60 сек: первый запрос по новому артикулу реально идёт
        // 10-15 сек и дольше (поставщики опрашиваются вживую, замер у них —
        // 12.9 сек на 1370 предложений), повтор по тому же артикулу — из их
        // кэша, доли секунды. Бэкенд поднимает Http::timeout(65) (см.
        // searchRadle) — 70 сек здесь даёт этому небольшой запас сверх ИХ
        // же таймаута плюс наш сетевой круг + рендер Blade, тем же приёмом,
        // что и у avtozakup выше (клиентский бюджет с запасом над бэкендом).
        const STEP_TIMEOUTS_MS = { avtozakup: 18000, radle: 70000 };

        // Ключ из JSON-конверта → id секции (шапка, скрыта через d-none пока
        // пусто) + id контейнера строк внутри неё (см. partials.searchResultsBody).
        const SECTIONS = {
            searchedNumber:  { sectionId: 'section-searched-number',   containerId: 'requestPartNumberContainer' },
            crossesInOffice: { sectionId: 'section-crosses-in-office', containerId: 'crossesContainer-in-office' },
            crossesOnStock:  { sectionId: 'section-crosses-on-stock',  containerId: 'crossesContainer-on-stock' },
            crossesToOrder:  { sectionId: 'section-crosses-to-order',  containerId: 'crossesContainer-to-order' },
        };

        const STEP_LABELS = {
            rossko: 'Rossko', locals: 'Локальные склады', armtek: 'Армтек',
            shatem: 'Шатэм', treid: 'Треид',
            phaeton_ast: 'Фаэтон АСТ', phaeton_local: 'Фаэтон Локал',
            forumauto: 'ФорумАвто', tiss: 'ТИСС', kulan: 'Кулан',
            febest: 'Фебест', gerat: 'Герат', autopiter: 'Автопитер',
            avtozakup: 'Автозакуп', radle: 'Радле',
        };

        // ─── Фильтр по брендам (слева) ─────────────────────────────────
        // $brands на сервере при прогрессивной загрузке всегда пуст (шелл
        // рендерится до единого обращения к поставщику) — список собираем
        // на лету из data-brand каждой пришедшей строки. Разные поставщики
        // отдают один и тот же бренд в разном регистре (BREMBO / Brembo) —
        // все data-brand уже нормализованы в верхний регистр на бэкенде
        // (strtoupper(trim(...)) в partials/items/*.blade.php), поэтому
        // "Brembo" и "BREMBO" схлопываются в одну строку фильтра и находятся
        // одним и тем же чекбоксом независимо от регистра у конкретного
        // поставщика.
        const brandFilterList = document.getElementById('brand-filter-list');
        const knownBrands = new Set();

        function registerBrand(brand) {
            if (!brand || knownBrands.has(brand) || !brandFilterList) return;
            knownBrands.add(brand);

            const id = 'brand-filter-' + knownBrands.size;

            const wrapper = document.createElement('div');
            wrapper.className = 'form-check';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input brand-filter';
            input.value = brand;
            input.id = id;

            const label = document.createElement('label');
            label.className = 'form-check-label filter-brand-name';
            label.htmlFor = id;
            label.textContent = brand;

            wrapper.appendChild(input);
            wrapper.appendChild(label);

            // Вставляем по алфавиту, а не в конец — иначе список скачет
            // вразнобой по мере прихода поставщиков одного за другим.
            const existingLabels = Array.from(brandFilterList.querySelectorAll('.filter-brand-name'));
            const next = existingLabels.find(el => el.textContent > brand);
            if (next) {
                brandFilterList.insertBefore(wrapper, next.closest('.form-check'));
            } else {
                brandFilterList.appendChild(wrapper);
            }
        }

        // Видимость строки зависит от ДВУХ независимых условий — фильтра по
        // бренду и постраничной подгрузки "искомого артикула" ниже — оба
        // пишут в свой dataset-флаг, а не сразу в style.display, иначе один
        // механизм затирал бы решение другого. Итоговый display — просто
        // "спрятана, если хоть кто-то из двух её прячет".
        function syncItemVisibility(item) {
            const hidden = item.dataset.brandHidden === '1' || item.dataset.pageHidden === '1';
            // '' (не жёстко 'grid'/'flex') — сбрасывает инлайн-стиль и
            // отдаёт управление обратно CSS-классу, который сам разный на
            // десктопе (grid) и мобиле (flex-карточка).
            item.style.display = hidden ? 'none' : '';
        }

        // Пустой выбор = показываем всё.
        function applyBrandFilter() {
            const checked = Array.from(document.querySelectorAll('.brand-filter:checked')).map(el => el.value);
            document.querySelectorAll('.requestPartNumberContainer-item[data-brand]').forEach(item => {
                item.dataset.brandHidden = (checked.length > 0 && !checked.includes(item.dataset.brand)) ? '1' : '0';
                syncItemVisibility(item);
            });
        }

        if (brandFilterList) {
            // Делегирование на #filter-brands — чекбоксы добавляются в DOM
            // динамически по мере поступления новых брендов, обычный
            // .on('change', ...) на конкретных элементах их бы не поймал.
            document.getElementById('filter-brands').addEventListener('change', function (e) {
                if (e.target.classList.contains('brand-filter')) applyBrandFilter();
            });
        }

        // ─── "N из M" под "Запрошенный артикул" ────────────────────────
        // Строки в #requestPartNumberContainer уже идут по возрастанию
        // цены (sortContainerByPrice) — просто показываем первые
        // PAGE_SIZE по DOM-порядку, это и есть "самые дешёвые". Остальное
        // скрыто до клика по ссылке, которая всегда остаётся последним
        // элементом контейнера (pinnedTail в sortContainerByPrice/insertRows
        // не даёт нормальным строкам обогнать её при пересортировке).
        const SEARCHED_NUMBER_PAGE_SIZE = 10;
        let searchedNumberVisibleCount = SEARCHED_NUMBER_PAGE_SIZE;

        function updateSearchedNumberPagination() {
            const container = document.getElementById('requestPartNumberContainer');
            const showMoreEl = document.getElementById('show-other-items');
            if (!container) return;

            const items = Array.from(container.querySelectorAll(':scope > .requestPartNumberContainer-item'));

            items.forEach((item, idx) => {
                item.dataset.pageHidden = (idx < searchedNumberVisibleCount) ? '0' : '1';
                syncItemVisibility(item);
            });

            if (!showMoreEl) return;
            const total = items.length;
            const shown = Math.min(searchedNumberVisibleCount, total);
            const link = showMoreEl.querySelector('a');

            if (total > searchedNumberVisibleCount) {
                showMoreEl.style.display = 'block';
                if (link) link.textContent = shown + ' из ' + total;
            } else {
                // Всё уже показано — прятать саму строку с "N из M" нечего
                // ждать дальше, некликабельный остаток тут только мешал бы.
                showMoreEl.style.display = 'none';
            }
        }

        const showMoreLink = document.getElementById('show-other-items-link');
        if (showMoreLink) {
            showMoreLink.addEventListener('click', function (e) {
                e.preventDefault();
                searchedNumberVisibleCount += SEARCHED_NUMBER_PAGE_SIZE;
                updateSearchedNumberPagination();
            });
        }

        function insertRows(key, html) {
            if (!html || !html.trim()) return;

            const cfg = SECTIONS[key];
            const section = document.getElementById(cfg.sectionId);
            const container = document.getElementById(cfg.containerId);
            if (!section || !container) return;

            section.classList.remove('d-none');

            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newNodes = Array.from(temp.children);

            // В requestPartNumberContainer внутри ещё лежит "Показать ещё
            // 10" (#show-other-items) — новые строки вставляем ПЕРЕД ним,
            // а не в конец контейнера.
            const showMore = container.querySelector('#show-other-items');

            // Если секция ДО этой пачки была пустой — это первая заливка
            // данных в неё, а не "новые строки среди уже видимых". Зелёная
            // подсветка тут не нужна (нечему сигналить "вот что добавилось"
            // на фоне пустоты) — включаем её только когда в контейнере уже
            // что-то отрисовано.
            const hadExistingItems = container.querySelector(':scope > .requestPartNumberContainer-item') !== null;

            // Animate.css вместо самописного CSS-transition (подключена в
            // layouts/app.blade.php) — те же классы, что и в
            // global_product.blade.php::renderOfferRow(), один визуальный
            // язык анимаций на весь сайт вместо своего для каждой страницы.
            newNodes.forEach(node => {
                // animate__* — влёт строки (везде), progressive-highlight —
                // мягкая зелёная подсветка "это только что подгрузилось",
                // только десктоп (сама анимация объявлена внутри
                // @media (min-width:1024px) в partSearchRes.css — на
                // мобиле класс просто ни на что не влияет).
                node.classList.add('animate__animated', 'animate__fadeInDown', 'animate__faster');
                if (hadExistingItems) {
                    node.classList.add('progressive-highlight');
                }
                if (showMore) {
                    container.insertBefore(node, showMore);
                } else {
                    container.appendChild(node);
                }

                // sortContainerByPrice ниже переставляет ВЕСЬ контейнер на
                // каждую новую пачку, включая уже отрисованные строки — само
                // перемещение узла в DOM (insertBefore) заставляет браузер
                // заново проиграть CSS-анимацию, если класс всё ещё на
                // узле. Без этой очистки к моменту прихода Армтека у строк
                // Rossko класс 'progressive-highlight' всё ещё висел (снят
                // никогда не был), и пересортировка "зажигала" зелёным сразу
                // все строки, а не только новые. Снимаем классы, когда
                // анимация точно уже отыграла (1.5s — подсветка, faster у
                // Animate.css короче) — тогда перестановке нечего запускать
                // заново на старых строках.
                setTimeout(() => {
                    node.classList.remove('animate__animated', 'animate__fadeInDown', 'animate__faster', 'progressive-highlight');
                }, 1700);

                registerBrand(node.dataset.brand);

                // Иконка возврата (requestPartNumber-return) несёт
                // data-bs-toggle="tooltip" — Bootstrap не активирует такие
                // атрибуты сам по себе, нужен явный new bootstrap.Tooltip()
                // на каждый элемент. Строки подгружаются пачками уже после
                // первого рендера страницы, поэтому инициализация — здесь,
                // на каждый новый узел, а не один раз при загрузке.
                node.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
            });

            sortContainerByPrice(container, showMore);

            // Если фильтр уже активен (пользователь отметил бренд раньше,
            // чем пришла эта пачка), новые строки должны сразу подчиниться
            // текущему выбору, а не мелькнуть видимыми на долю секунды.
            applyBrandFilter();

            // "N из M" пересчитывается заново на каждую пачку — секция
            // "Запрошенный артикул" пополняется не только локальным
            // поиском, но и Rossko/Армтек/... по мере ответа, общее число M
            // растёт по ходу прогрузки.
            if (key === 'searchedNumber') {
                updateSearchedNumberPagination();
            }
        }

        // Каждый шаг (Rossko, Армтек, Шатэм...) отсортирован по цене САМ
        // ВНУТРИ СЕБЯ на сервере, но пачки просто дописывались друг за
        // другом — визуально это выглядело как группировка по поставщику/
        // бренду ("один бренд — 2 предложения, второй — 3"), а не единый
        // порядок по цене. Тут пересортировываем ВЕСЬ контейнер целиком при
        // каждой новой пачке — appendChild на уже существующем узле его не
        // клонирует, а просто переставляет, так что просто заново
        // расставляем все строки в нужном порядке по data-price.
        function sortContainerByPrice(container, pinnedTail) {
            const items = Array.from(container.querySelectorAll(':scope > .requestPartNumberContainer-item'));
            items.sort((a, b) => (parseInt(a.dataset.price, 10) || 0) - (parseInt(b.dataset.price, 10) || 0));
            items.forEach(node => container.insertBefore(node, pinnedTail || null));
        }

        // Только для админа — сам элемент рендерится в разметке только под
        // user_role=admin, тут просто подстраховка на случай, если его нет
        // в DOM (напр. гость).
        const suppliersBadge = document.getElementById('suppliers-responded-badge');
        const failedListEl = document.getElementById('suppliers-failed-list');
        let respondedCount = 0;
        const failedSuppliers = [];

        // Перетаскивание виджета счётчика поставщиков — мышью на десктопе,
        // зажатием (long-press) на мобиле, чтобы Роман мог убрать его,
        // если он закрывает собой что-то на странице. Изначально виджет
        // спозиционирован через top+right (см. разметку выше) — при первом
        // же движении переключаемся на top+left в px, иначе перетаскивание
        // "прыгает" (right и left одновременно не имеют смысла). top у
        // виджета на мобиле переопределён в partSearchRes-mini.css через
        // !important (не залезать на переключатель "Только в наличии") —
        // setProperty(..., 'important') на инлайн-стиле перебивает его
        // (инлайн всегда весомее любого правила из подключённого файла).
        (function makeSuppliersWidgetDraggable() {
            const widget = document.getElementById('suppliers-progress-widget');
            if (!widget) return;

            let dragging = false;
            let offsetX = 0;
            let offsetY = 0;
            let longPressTimer = null;

            function setPos(left, top) {
                const maxLeft = window.innerWidth - widget.offsetWidth;
                const maxTop = window.innerHeight - widget.offsetHeight;
                left = Math.max(0, Math.min(maxLeft, left));
                top = Math.max(0, Math.min(maxTop, top));
                widget.style.setProperty('left', left + 'px', 'important');
                widget.style.setProperty('top', top + 'px', 'important');
                widget.style.setProperty('right', 'auto', 'important');
            }

            function startDrag(clientX, clientY) {
                dragging = true;
                const rect = widget.getBoundingClientRect();
                offsetX = clientX - rect.left;
                offsetY = clientY - rect.top;
                widget.style.cursor = 'grabbing';
            }

            function moveDrag(clientX, clientY) {
                if (!dragging) return;
                setPos(clientX - offsetX, clientY - offsetY);
            }

            function endDrag() {
                dragging = false;
                widget.style.cursor = 'grab';
            }

            // ---- десктоп ----
            widget.addEventListener('mousedown', function (e) {
                startDrag(e.clientX, e.clientY);
                e.preventDefault();
            });
            document.addEventListener('mousemove', function (e) { moveDrag(e.clientX, e.clientY); });
            document.addEventListener('mouseup', endDrag);

            // ---- мобилка: зажать, потом тащить (иначе обычный тап/скролл
            // страницы стал бы двигать виджет) ----
            const LONG_PRESS_MS = 350;
            widget.addEventListener('touchstart', function (e) {
                const touch = e.touches[0];
                longPressTimer = setTimeout(function () {
                    startDrag(touch.clientX, touch.clientY);
                }, LONG_PRESS_MS);
            }, { passive: true });

            widget.addEventListener('touchmove', function (e) {
                if (!dragging) return;
                const touch = e.touches[0];
                moveDrag(touch.clientX, touch.clientY);
                e.preventDefault(); // не даём странице скроллиться, пока тащим виджет
            }, { passive: false });

            widget.addEventListener('touchend', function () {
                clearTimeout(longPressTimer);
                endDrag();
            });
        })();

        // Прогресс-бар — для ВСЕХ посетителей, не только админа (сам
        // элемент в разметке не гейтится ролью, см. HTML выше).
        const progressWrapper = document.getElementById('customer-search-progress');
        const progressFill    = document.getElementById('customer-search-progress-fill');
        const progressIcon    = document.getElementById('customer-search-progress-icon');
        const progressPercent = document.getElementById('customer-search-progress-percent');

        function renderProgress(totalPhases) {
            const percent = Math.round((respondedCount / totalPhases) * 100);

            if (progressFill && progressIcon && progressPercent) {
                progressFill.style.width = percent + '%';
                progressIcon.style.left = percent + '%';
                progressPercent.textContent = percent + '%';

                if (percent >= 100 && progressWrapper) {
                    // Всё загрузилось — бар прячется, не занимает место над
                    // уже готовыми результатами.
                    setTimeout(() => { progressWrapper.style.display = 'none'; }, 400);
                }
            }

            // Дальше — только для админа, элементов нет в DOM у остальных.
            if (!suppliersBadge) return;
            suppliersBadge.style.display = 'inline-block';
            suppliersBadge.textContent = respondedCount + ' из ' + totalPhases + ' поставщиков ответили';

            if (!failedListEl) return;
            if (failedSuppliers.length === 0) {
                failedListEl.style.display = 'none';
                return;
            }
            failedListEl.style.display = 'block';
            failedListEl.textContent = 'Не ответили: ' + failedSuppliers.join(', ');
        }

        // loadFragment НИКОГДА не реджектится — success:false (таймаут,
        // сетевая ошибка, не-200, битый JSON) это тоже штатный исход шага,
        // цепочка шагов должна продолжаться в любом случае.
        function loadFragment(url, label, timeoutMs) {
            return fetch(url, { signal: AbortSignal.timeout(timeoutMs || SUPPLIER_TIMEOUT_MS) })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(json => {
                    Object.keys(SECTIONS).forEach(key => insertRows(key, json[key]));
                    // supplierOk есть только в ответе searchSupplierStepFragment
                    // (searchRosskoFragment/searchRestFragment его не отдают —
                    // undefined !== false, старое поведение для них не меняется).
                    // false означает "наш сервер ответил 200, но конкретный
                    // поставщик молча ничего не прислал" (пустой ответ /
                    // битый JSON / бизнес-ошибка вроде IsError у Phaeton) —
                    // раньше runParallelSuppliers в SparePartControllerTest
                    // это тоже засчитывал как "ответил", т.к. был void и
                    // ничего не сообщал наружу о реальном исходе.
                    return { success: json.supplierOk !== false };
                })
                .catch(err => {
                    console.error('Fragment load error (' + label + '):', url, err);
                    return { success: false };
                });
        }

        // Очередь неответивших — не ретраим на месте (это снова растянуло бы
        // именно ЭТОТ шаг цепочки), а копим и опрашиваем повторно ОДИН раз,
        // когда основной проход по всем поставщикам уже закончился. Менеджеру
        // важна честная финальная картина ("не ответил даже после повтора"),
        // а не "не успел с первого раза" — если сервер поставщика просто был
        // на секунду медленнее таймаута, повтор его почти наверняка вытащит.
        const failedQueue = []; // { label, url, timeoutMs }

        function markPhaseResult(label, result, url, totalPhases, timeoutMs) {
            respondedCount += 1;
            if (!result.success) {
                failedSuppliers.push(label);
                failedQueue.push({ label, url, timeoutMs });
            }
            renderProgress(totalPhases);
        }

        // Финальная проверка — вызывается РОВНО ОДИН РАЗ, когда полностью
        // закончился и основной проход по всем 14 шагам (Rossko + STEP_ORDER),
        // и повторный проход по неответившим. Если ни одна из 4 секций так и
        // не вышла из d-none — значит ни один поставщик ничего не нашёл,
        // показываем "Ничего не найдено" вместо молча пустой страницы.
        function finalizeSearch() {
            const emptyState = document.getElementById('search-no-results');
            if (!emptyState) return;

            const hasResults = Object.values(SECTIONS).some(function (cfg) {
                const section = document.getElementById(cfg.sectionId);
                return section && !section.classList.contains('d-none');
            });

            emptyState.classList.toggle('d-none', hasResults);
        }

        function runRetryQueue(queue) {
            if (queue.length === 0) {
                finalizeSearch();
                return;
            }

            const [item, ...rest] = queue;

            loadFragment(item.url, item.label + ' (повтор)', item.timeoutMs)
                .then(result => {
                    if (result.success) {
                        const idx = failedSuppliers.indexOf(item.label);
                        if (idx !== -1) failedSuppliers.splice(idx, 1);
                        renderProgress(TOTAL_PHASES);
                    }
                    runRetryQueue(rest);
                });
        }

        // Пошагово, один поставщик за раз — каждый следующий запрос уходит
        // только ПОСЛЕ того, как предыдущий отрисовался. Это специально
        // медленнее по общему времени, чем параллельный пул (curl_multi
        // внутри всё ещё используется для phaeton_ast/phaeton_local/
        // forumauto/kulan/febest/gerat, просто по одному шагу на
        // поставщика вместо всех сразу) — зато ощущается "живее": видно,
        // как последовательно подтягиваются Rossko → локальные склады →
        // Армтек → Шатэм → ...
        //
        // phaeton_ast/phaeton_local — раньше один шаг 'phaeton' с общим
        // supplierOk через ИЛИ (см. searchSupplierStepFragment), разделены
        // 2026-08-23: phaeton_local стабильно падает по IP-вайтлисту на
        // стороне Фаэтона (ждут понедельника), и объединённое ИЛИ
        // маскировало это в бейдже "N из M ответили" — Роману критично
        // видеть их по отдельности, чтобы отличать "АСТ реально ничего не
        // нашёл" от "АСТ сам глючит/не ответил".
        const STEP_ORDER = [
            'locals', 'armtek', 'shatem', 'treid',
            'phaeton_ast', 'phaeton_local', 'forumauto', 'tiss', 'kulan', 'febest', 'gerat',
            'autopiter', 'avtozakup', 'radle',
        ];

        const TOTAL_PHASES = 1 + STEP_ORDER.length; // Rossko + все шаги

        function runStepsSequentially(steps) {
            if (steps.length === 0) {
                // Основной проход закончился — теперь один повторный проход
                // по тем, кто не ответил (если такие есть).
                runRetryQueue(failedQueue.splice(0, failedQueue.length));
                return;
            }

            const [step, ...rest] = steps;
            const params = new URLSearchParams({
                brand, partnumber, step,
                only_on_stock: onlyOnStock ? '1' : '',
            });
            const url = '/test/search-step-fragment?' + params.toString();
            const label = STEP_LABELS[step] ?? step;
            const timeoutMs = STEP_TIMEOUTS_MS[step];

            loadFragment(url, label, timeoutMs)
                .then(result => {
                    markPhaseResult(label, result, url, TOTAL_PHASES, timeoutMs);
                    runStepsSequentially(rest);
                });
        }

        const rosskoParams = new URLSearchParams({
            brand, partnumber, guid,
            rossko_need_to_search: rosskoNeedToSearch ? '1' : '',
        });
        const rosskoUrl = '/test/search-rossko-fragment?' + rosskoParams.toString();
        loadFragment(rosskoUrl, STEP_LABELS.rossko)
            .then(result => {
                markPhaseResult(STEP_LABELS.rossko, result, rosskoUrl, TOTAL_PHASES);
                runStepsSequentially(STEP_ORDER);
            });
    })();
</script>
@endsection