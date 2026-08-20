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
                        <li>
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
                         "прилипание" к вьюпорту, оно и нужно). --}}
                    <div id="suppliers-progress-widget" style="position: fixed; top: 90px; right: 16px; z-index: 1050; text-align: right;">
                        <span id="suppliers-responded-badge" class="badge rounded-pill bg-success" style="display: none;"></span>
                        <div id="suppliers-failed-list" class="small text-danger mt-1" style="display: none;"></div>
                    </div>
                @endif
                @endauth
            </div>

            {{-- Прогресс-бар для ВСЕХ посетителей (не только админа) — пока
                 идёт прогрессивная подгрузка (см. <script> в конце файла),
                 показываем % по числу ответивших поставщиков, с иконкой
                 логотипа (шестерёнка+машина, без текста — полный логотип с
                 текстом слишком вытянутый для такого), едущей по краю
                 заполненной полосы. Прячется сам, как только всё загрузилось. --}}
            <div id="customer-search-progress" class="my-3">
                <div class="small text-muted mb-1">
                    Ищем запчасти у поставщиков… <span id="customer-search-progress-percent">0%</span>
                </div>
                <div style="position: relative; height: 8px; background: #e9ecef; border-radius: 999px;">
                    <div id="customer-search-progress-fill" style="height: 100%; width: 0%; background: #0d6efd; border-radius: 999px; transition: width 0.4s ease;"></div>
                    <img id="customer-search-progress-icon" src="{{ asset('images/favicon-master-512.png') }}" alt=""
                         style="position: absolute; top: 50%; left: 0%; width: 26px; height: 26px; transform: translate(-50%, -50%); transition: left 0.4s ease; filter: drop-shadow(0 1px 3px rgba(0,0,0,.35)); animation: customer-progress-spin 1.8s linear infinite;">
                </div>
            </div>
            <style>
                @keyframes customer-progress-spin {
                    from { transform: translate(-50%, -50%) rotate(0deg); }
                    to   { transform: translate(-50%, -50%) rotate(360deg); }
                }
            </style>

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
        const STEP_TIMEOUTS_MS = { avtozakup: 18000 };

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
            shatem: 'Шатэм', treid: 'Треид', phaeton: 'Фаэтон',
            forumauto: 'ФорумАвто', tiss: 'ТИСС', kulan: 'Кулан',
            febest: 'Фебест', gerat: 'Герат', autopiter: 'Автопитер',
            avtozakup: 'Автозакуп',
        };

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
            });

            sortContainerByPrice(container, showMore);
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
                    // Всё загрузилось — иконка останавливается и бар прячется,
                    // не занимает место над уже готовыми результатами.
                    progressIcon.style.animation = 'none';
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
                    return { success: true };
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

        function runRetryQueue(queue) {
            if (queue.length === 0) return;

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
        // внутри всё ещё используется для phaeton/forumauto/tiss/kulan/
        // febest/gerat, просто по одному шагу на поставщика вместо всех
        // сразу) — зато ощущается "живее": видно, как последовательно
        // подтягиваются Rossko → локальные склады → Армтек → Шатэм → ...
        const STEP_ORDER = [
            'locals', 'armtek', 'shatem', 'treid',
            'phaeton', 'forumauto', 'tiss', 'kulan', 'febest', 'gerat',
            'autopiter', 'avtozakup',
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