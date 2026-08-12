// =========================================================
// master.js — деминифицированная и исправленная версия
// Основной фикс: document.getElementById('search-bar-container')
// мог вернуть null на страницах без поисковой формы, что рушило
// выполнение всего остального скрипта (TypeError на верхнем уровне
// файла останавливает исполнение всех последующих строк).
// Добавлены null-проверки везде, где раньше их не было.
// =========================================================

// ---- фикс кнопки "Назад": браузер восстанавливает страницу из bfcache
// (снимок DOM на момент ухода со страницы, без повторного запроса к серверу)
// — если корзина была пустой на момент захода на страницу, а потом товар
// добавили через AJAX и ушли дальше, при возврате назад показывается тот
// самый старый снимок с пустой корзиной в хедере. Принудительно
// перезагружаем страницу в этом случае — тогда хедер отрендерится заново
// на сервере с актуальным состоянием корзины из сессии. ----
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        location.reload();
    }
});

$(window).on('load', function () {

    // ---- "показать ещё" в блоке requestPartNumberContainer ----
    let counter = 0;
    $('#requestPartNumberContainer').children().each(function () {
        if (counter > 10) {
            $(this).css({ 'display': 'none' });
        }
        counter++;
    });

    if (counter > 10) {
        $('#show-other-items').css({ 'display': 'block' });
        $('#show-other-items a').text(
            `Показать еще ${$('#requestPartNumberContainer').children().length - Number($('#show-other-items').attr('counter'))} из ${$('#requestPartNumberContainer').children().length} (по 10)`
        );
    }

    // ---- пагинация кроссов "под заказ" ----
    const perPage = 50;
    if ($('#crossesContainer-to-order').children().length > perPage) {
        $('#crossesContainer-to-order').children().each(function (key, elem) {
            if (key > perPage) {
                $(this).css({ 'display': 'none' });
            }
        });

        const pageCount = Math.ceil($('#crossesContainer-to-order').children().length / perPage);
        $('.pagination-nav').css({ 'display': 'flex' });
        $('.pagination-nav ul').children().each(function (key, elem) {
            if (Number($(elem).children().first().attr('page-num')) > pageCount) {
                $(elem).css({ 'display': 'none' });
            }
        });
    }

    // ---- статус "возвращено" — блокировка селекта ----
    $('.order_product_status').each(function (key, elem) {
        if ($(elem).find('option:selected').val() == 'returned') {
            $(this).attr('disabled', true);
            $(this).parent().next().children().first().next().attr('disabled', true);
        }
    });

    // ---- чекбокс "в наличии" по параметрам URL ----
    if ($(location).attr('href').includes('getCatalog') && $(location).attr('href').includes('only_on_stock')) {
        $('#stock_or_order').prop('checked', 'checked');
        $('#stock_or_order').attr('disabled', 'disabled');
    } else if ($(location).attr('href').includes('getCatalog')) {
        $('#stock_or_order').attr('disabled', 'disabled');
    }

    // ---- WhatsApp кнопка на мобильных ----
    if ($(window).width() <= '580') {
        $('.whatsapp-fixed-btn').attr('href', 'https://wa.me/77087172549?text=Здравствуйте,%20пишу%20вам%20с%20сайта.');
        $('.wa-top-container').attr('href', 'https://wa.me/77087172549?text=Здравствуйте,%20пишу%20вам%20с%20сайта.');
    }

    $('.whatsapp-fixed-btn, .wa-top-container').on('click', function (e) {
        if ($(window).width() > 580) {
            e.preventDefault();
            $('#shadow-main').fadeIn();
            $('#shadow-main').css({ 'background-color': 'rgba(0, 0, 0, .8)' });
        }
    });

});

// =========================================================
// ФИКС: раньше это ломало весь остальной скрипт, если на
// странице не было #search-bar-container (например, в админке).
// =========================================================
const searchBarForm = document.getElementById('search-bar-container');
if (searchBarForm) {
    searchBarForm.addEventListener('submit', function (e) {
        let input = document.getElementById('searchBarInput');
        if (!input) return;

        let cleanVal = input.value.replace(/[^A-Za-z0-9]/g, '');
        input.value = cleanVal;

        if (cleanVal.length < 3) {
            alert("Пожалуйста, введите минимум 3 символа артикула");
            e.preventDefault();
        }
    });
}

$('.whatsapp-fixed-btn-only-to-open-block').on('click', function () {
    $('#social-media-container').slideDown(300).css({ 'display': 'flex' });
    $(this).fadeOut(200);
    $('#social-media-container-close').fadeIn(500).css({ 'right': 10, 'bottom': 20 });
});

$('#social-media-container-close').on('click', function () {
    $('#social-media-container').slideUp(300);
    $('.whatsapp-fixed-btn-only-to-open-block').fadeIn(400);
});

$('.spare-part-info-show').on('click', function () {
    $('#curtain-grey-searchpartres').css({ 'display': 'block' });
    $(this).next().slideDown(400);
});

$('.block-info-item-close').on('click', function () {
    $(this).parent().parent().slideUp(400);
    $('#curtain-grey-searchpartres').fadeOut(600);
});

// ---- иконка "i" для поставщиков БЕЗ встроенных данных (не Gerat) ----
// В отличие от .spare-part-info-show (готовая модалка уже в DOM рядом с
// иконкой — данные от Gerat приходят вместе с ответом поиска), тут модалка
// одна на страницу (#ajaxInfoBlock) и заполняется по клику через AJAX
// (CatalogController::partInfo → таблица parts_catalog).
function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : str).html();
}

$(document).on('click', '.spare-part-info-lookup', function () {
    const article = $(this).data('article');
    const brand = $(this).data('brand');

    $('#curtain-grey-searchpartres').css({ 'display': 'block' });
    $('#ajaxInfoName').text('Загрузка...');
    $('#ajaxInfoBrand').text(brand);
    $('#ajaxInfoArticle').text(article);
    $('#ajaxInfoCarouselInner').html('');
    $('#ajaxInfoDescription').html('');
    $('#ajaxInfoSpecs').html('');
    $('#ajaxInfoApplicability').html('');
    $('#ajaxInfoBlock').slideDown(400);

    $.ajax({
        url: '/api/part-info',
        method: 'GET',
        data: { article: article, brand: brand },
        dataType: 'json',
        success: function (data) {
            if (!data.found) {
                $('#ajaxInfoName').text('Информация пока недоступна');
                $('#ajaxInfoCarouselInner').html('<div class="carousel-item active"><div class="text-center text-muted py-5">Нет изображений</div></div>');
                $('#ajaxInfoDescription').html('<p class="text-muted">По этой детали пока нет фото, описания и характеристик в нашей базе — мы постепенно наполняем каталог. Загляните позже или уточните в WhatsApp.</p>');
                $('#ajaxInfoSpecs').html('<p class="text-muted">Характеристики отсутствуют.</p>');
                $('#ajaxInfoApplicability').html('<p class="text-muted">Данные о применимости отсутствуют.</p>');
                return;
            }

            $('#ajaxInfoName').text(data.name || '');
            $('#ajaxInfoBrand').text(data.brand || brand);
            $('#ajaxInfoArticle').text(data.article || article);

            if (data.images && data.images.length > 0) {
                let picturesHtml = '';
                data.images.forEach(function (src, i) {
                    picturesHtml += '<div class="carousel-item' + (i === 0 ? ' active' : '') + '"><img src="' + src + '" class="carousel-item-img" alt="sparepart-picture"></div>';
                });
                $('#ajaxInfoCarouselInner').html(picturesHtml);
            } else {
                $('#ajaxInfoCarouselInner').html('<div class="carousel-item active"><div class="text-center text-muted py-5">Нет изображений</div></div>');
            }

            $('#ajaxInfoDescription').html(data.description || '<p class="text-muted">Описание отсутствует.</p>');
            $('#ajaxInfoApplicability').html(data.applicability || '<p class="text-muted">Данные о применимости отсутствуют.</p>');

            if (data.specifications && data.specifications.length > 0) {
                let specsHtml = '';
                data.specifications.forEach(function (section) {
                    specsHtml += '<div class="mb-3"><div class="fw-semibold small text-uppercase text-muted mb-1">' + escapeHtml(section.name) + '</div><table class="table table-sm table-bordered"><tbody>';
                    (section.features || []).forEach(function (feature) {
                        const values = (feature.featureValues || []).map(function (v) { return v.value; }).join(', ');
                        specsHtml += '<tr><td class="text-muted" style="width:280px;">' + escapeHtml(feature.name) + '</td><td>' + escapeHtml(values) + '</td></tr>';
                    });
                    specsHtml += '</tbody></table></div>';
                });
                $('#ajaxInfoSpecs').html(specsHtml);
            } else {
                $('#ajaxInfoSpecs').html('<p class="text-muted">Характеристики отсутствуют.</p>');
            }
        },
        error: function () {
            $('#ajaxInfoName').text('Ошибка загрузки');
            $('#ajaxInfoDescription').html('<p class="text-danger">Не удалось загрузить данные, попробуйте ещё раз.</p>');
        }
    });
});

$('#searchBarInput').on('input', function () {
    $('#search-input-text-delete').fadeIn(200);
    if ($(this).val().length == 0) {
        $('#search-input-text-delete').fadeOut(300);
    }
});

$('#search-input-text-delete').on('click', function () {
    $('#searchBarInput').val('');
    $(this).fadeOut(100);
});

$('#scroll-to-form').on('click', function () {
    const el = document.getElementById('vin-form');
    if (el) el.scrollIntoView({ behavior: "smooth", 'block': 'start' });
});

$('.start-api-search').on('click', function () {
    const el = document.getElementById('api-searchres-header');
    if (el) el.scrollIntoView({ behavior: "smooth", 'block': 'start' });
});

$('#show-other-items').on('click', function () {
    let counter1 = Number($(this).attr('counter'));
    let step = 10;
    counter1 += step;

    $('#show-other-items a').text(
        `Показать еще ${($('#requestPartNumberContainer').children().length - $(this).attr('counter')) - 10} из ${$('#requestPartNumberContainer').children().length} (по ${step})`
    );

    $('#requestPartNumberContainer').children().each(function (key, elem) {
        if (counter1 > $('#requestPartNumberContainer').children().length) {
            $('#show-other-items').css({ 'display': 'none' });
            return false;
        }
        if (counter1 == key) {
            $('#show-other-items').attr('counter', key);
            return false;
        } else {
            $(elem).css({ 'display': 'grid' });
        }
    });
});

$('.page-link').on('click', function () {
    $('.page-item').removeClass('active');
    $(this).parent().addClass('active');

    const perPage = 50;
    const desirePage = $(this).attr('page-num');
    const start = (desirePage - 1) * perPage;
    const end = desirePage * perPage;

    let choosedBrand = [];
    $('.brand-filter').each(function (key, elem) {
        if (elem.checked) {
            choosedBrand.push($(elem).val());
        }
    });

    $('#crossesContainer-to-order').children().each(function (key, elem) {
        if (key >= start && key <= end) {
            if (!choosedBrand.length) {
                $(elem).css({ 'display': 'grid' });
            } else if (choosedBrand.includes($('.requestPartNumber-brand').text().replace(/\s+/g, ''))) {
                $(elem).css({ 'display': 'grid' });
            }
        } else {
            if (!choosedBrand.length) {
                $(elem).css({ 'display': 'none' });
            } else if (choosedBrand.includes($('.requestPartNumber-brand').text().replace(/\s+/g, ''))) {
                $(elem).css({ 'display': 'none' });
            }
        }
    });
});

$('.form-search-item').on('click', function () {
    $('#shadow').fadeIn(400);
    $('#shadow').css({ 'display': 'flex' });
});

// ---- бейдж корзины в хедере: всегда навешиваем стили заново, а не только
// текст — иначе если хедер изначально отрендерился с пустой корзиной (branch
// без bootstrap-классов в header.blade.php), после AJAX-добавления бейдж
// показывает голый текст без оформления ----
function updateHeaderCartBadge(count, total) {
    $('#header-cart-qty')
        .attr('class', 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white')
        .css('font-size', '0.65rem')
        .text(count + ' шт');
    $('.header-cart-sum')
        .attr('class', 'header-cart-sum ms-2 fw-bold text-primary d-none d-xl-block')
        .css('font-size', '0.85rem')
        .text(total + ' ₸');
}

// ---- не даём ввести в поле количества больше, чем реально в наличии (атрибут max) ----
$(document).on('input change', '.stock-item-cart-qty input[type="number"]', function () {
    const max = parseInt($(this).attr('max'));
    const min = parseInt($(this).attr('min')) || 1;
    let val = parseInt($(this).val()) || min;

    if (max && val > max) val = max;
    if (val < min) val = min;

    $(this).val(val);
});

// ---- добавление товара в корзину (обычный поиск) ----
$('.stock-item-cart-btn').on('click', function () {
    const regExp = /\*|%|#|\n|&|\$/g;

    const qtyInput = $(this).next().children().first();
    const maxQty = parseInt(qtyInput.attr('max'));
    let requestedQty = parseInt(qtyInput.val()) || 1;

    if (maxQty && requestedQty > maxQty) {
        requestedQty = maxQty;
        qtyInput.val(maxQty);
    }

    let params = {
        'brand': '',
        'article': '',
        'name': '',
        'price': '',
        'qty': requestedQty,
        'deliveryTime': '',
        'stockFrom': '',
        'searchedNumber': '',
        'priceWithMargine': ''
    };

    params.brand = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
    params.article = $(this).parent().parent().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
    params.name = $(this).parent().parent().prev().prev().prev().prev().prev().text().replaceAll(regExp, '');
    params.priceWithMargine = $(this).parent().parent().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
    params.deliveryTime = $(this).parent().parent().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
    params.stockFrom = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
    params.searchedNumber = $('#search-res-header-val').html();
    params.price = $(this).next().next().val();

    $(this).children().first().removeClass('fa-cart-shopping').addClass('fa-check').css('color', '#4bc828');
    $(this).css({ 'border': '1px solid #4bc828' });

    $.ajax({
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), data: params },
        reqData: params,
        url: "/cart/add",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            updateHeaderCartBadge(data.count, data.total);

            if (data.duplicates) {
                $("#search-part-main-container").prepend(`
                    <div class="alert alert-warning alert-cart" style="align-text:center;">
                        <div style="display:flex;justify-content:flex-end;" class="close-flash">&times;</div>
                        Данная позиция уже добавлена, для изменения количества перейдите в корзину...
                    </div>
                `);
                setTimeout(() => { $('.alert-cart').slideUp(400); }, 3000);
                $('.close-flash').on('click', function () { $(this).parent().slideUp(400); });
            }
        },
        error: function (error) {
            console.log(error);
        }
    });
});

// ---- удаление позиции из корзины ----
$(document).on('click', '.cart-item-delete', function (e) {
    e.preventDefault();
    let btn = $(this);
    let article = btn.data('article');
    btn.find('i').css({ 'transform': 'scale(0.7)', 'transition': '0.2s' });

    $.ajax({
        url: "/cart/delete",
        type: "POST",
        dataType: 'json',
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), 'data': { 'article': article } },
        success: function (data) {
            btn.closest('.cart-item-row').fadeOut(300, function () {
                $(this).remove();
                if (data.count == 0) {
                    location.reload();
                }
            });

            let formattedTotal = new Intl.NumberFormat('ru-RU').format(data.total) + ' ₸';
            $('#header-cart-qty').text(data.count + " шт");
            $('.header-cart-sum').text(formattedTotal);
            $('.cart-total-display').text(formattedTotal);
            $('#cart-total-checkout').text(formattedTotal);
        },
        error: function (error) {
            console.log("Ошибка удаления:", error);
        }
    });
});

// ---- изменение количества в корзине ----
$(document).on('change', '.cart-qty-change', function () {
    let input = $(this);
    let qty = parseInt(input.val()) || 1;
    let price = parseInt(input.data('price')) || 0;
    let article = input.data('article');

    if (qty < 1) {
        qty = 1;
        input.val(1);
    }

    let parent = input.closest('.cart-item-row');
    let itemSubtotal = qty * price;
    parent.find('.item-subtotal-display').text(new Intl.NumberFormat('ru-RU').format(itemSubtotal) + ' ₸');

    let totalCartSum = 0;
    let visibleRows = $('.d-none.d-md-block').is(':visible')
        ? $('.d-md-block .item-subtotal-display')
        : $('.d-md-none .item-subtotal-display');

    visibleRows.each(function () {
        let val = $(this).text().replace(/[^0-9]/g, '');
        totalCartSum += parseInt(val) || 0;
    });

    let finalFormatted = new Intl.NumberFormat('ru-RU').format(totalCartSum) + ' ₸';
    $('.cart-total-display').html(finalFormatted);
    $('#cart-total-checkout').html(finalFormatted);
    $('.header-cart-sum').html(finalFormatted);

    $.ajax({
        url: '/cart/update',
        method: 'POST',
        data: { _token: $('meta[name="csrf-token"]').attr('content'), article: article, qty: qty },
        success: function (response) {
            console.log('Корзина обновлена в сессии');
        }
    });
});

// ---- ручное изменение цены с наценкой в корзине ----
$('.newPriceWithMargine').on('input', function () {
    let data = {
        'article': $(this).parent().prev().prev().prev().text(),
        'priceWithMargine': $(this).val()
    };

    $(this).parent().next().next().text($(this).val() * $(this).parent().next().children().first().val());

    $.ajax({
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), data: data },
        url: "/cart/updatePrice",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            $('#header-cart-qty').text(new Intl.NumberFormat('ru-RU').format(data.count) + ' шт');
            $('#header-cart-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
            $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 }).format(data.total) + ' T');
            console.log(data);
        },
        error: function (error) {
            console.log(error);
        }
    });
});

// ---- модалка подтверждения заказа ----
$('#modal-show').on('click', function () {
    $('#order-confirmation-form').fadeIn(400);
    $('#cart-shadow').fadeIn(300);
});

$('.modal-close').on('click', function () {
    $('#order-confirmation-form').fadeOut(300);
    $('#cart-shadow').fadeOut(400);
});

$('#order-confirm').on('click', function () {
    $('#order-btn-submit').click();
    $(this).attr('disabled', true);
});

$('#order-cancel').on('click', function () {
    $('#order-confirmation-form').fadeOut(300);
    $('#cart-shadow').fadeOut(400);
});

// ---- раскрытие состава заказа в списке расчётов ----
$('.settlement-item-id').on('click', function (e) {
    let data = { 'order_id': $(this).children().first().val() };

    $.ajax({
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), data: data },
        url: "/order/products",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            let searchedElem = $(`input[class~=order_${data.orderId}]`);
            let table = $(searchedElem).parent().parent().next().children().first();
            let changedBorder = $(searchedElem).parent().parent();
            let changedBackground = $(searchedElem).parent().parent().parent();

            if ($(table).html() == '') {
                data.products.forEach(item => {
                    let statuses = {
                        'processing': 'в работе',
                        'returned': 'возвращено',
                        'payment_waiting': 'ожидание оплаты',
                        'supplier_refusal': 'отказ поставщика',
                        'arrived_at_the_point_of_delivery': 'поступило в ПВЗ',
                        'issued': 'выдано'
                    };

                    $(table).append(`
                        <tr>
                            <td>${item.article}</td>
                            <td>${item.brand}</td>
                            <td>${item.name}</td>
                            <td class="${item.status}">${statuses[item.status]}</td>
                            <td>${item.qty}шт</td>
                            <td>${item.priceWithMargine * item.qty}</td>
                            <td>${item.fromStock}</td>
                        </tr>
                    `);
                });

                $(changedBackground).css({ 'border': '1px solid #aaa' });
                $(changedBorder).css({ 'background-color': '#ebe8e2' });
            } else {
                $(table).empty();
                $(changedBackground).css({ 'border': 'none' });
                $(changedBorder).css({ 'background-color': 'transparent' });
            }
        },
        error: function (error) {
            console.log(error);
        }
    });
});

// ---- смена статуса заказа (админка) ----
$('.change_status_submit').on('click', function () {
    let productId = $(this).prev().val();
    let newStatus = $(this).parent().prev().children().first().val();

    if (!newStatus) {
        alert('Игорь, смени статус бля!');
        return;
    }

    let data = { 'product_id': productId, 'new_status': newStatus };

    let $btn = $(this);

    $.ajax({
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), data: data },
        url: "/product/change_status",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            // Bootstrap toast с подтверждением успешной смены статуса
            showStatusChangeToast('success', 'Статус успешно изменён' + (data.message ? ': ' + data.message : ''));

            if (data.status == 'returned') {
                $btn.prev().children().first().css({ 'pointer-events': 'none' });
            }
        },
        error: function (jqXHR) {
            // ФИКС: раньше здесь тоже показывался alert-success (баг копипаста),
            // теперь при ошибке показывается alert-danger с сообщением от сервера.
            let errMsg = (jqXHR.responseJSON && jqXHR.responseJSON.message)
                ? jqXHR.responseJSON.message
                : 'Ошибка при смене статуса';
            showStatusChangeToast('danger', errMsg);
        }
    });
});

// Bootstrap toast-уведомление о результате смены статуса заказа.
// type: 'success' | 'danger'
function showStatusChangeToast(type, message) {
    let toastId = 'status-toast-' + Date.now();

    let $toast = $(`
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0 position-fixed"
             style="top: 20px; right: 20px; z-index: 1080; min-width: 280px;"
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `);

    $('body').append($toast);

    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toastInstance = new bootstrap.Toast($toast[0], { delay: 3000 });
        toastInstance.show();
        $toast.on('hidden.bs.toast', function () { $(this).remove(); });
    } else {
        // Фолбэк, если bootstrap.js (JS-бандл) не подключён на странице
        $toast.fadeIn();
        setTimeout(() => { $toast.fadeOut(400, function () { $(this).remove(); }); }, 3000);
    }
}

$('.menu-item-name').on('mouseenter', function () {
    $(this).css({ 'scale': '1.1' });
    $(this).css({ 'cursor': 'pointer' });
});

$('.menu-item-name').on('mouseleave', function () {
    $(this).css({ 'scale': '1' });
});

setInterval(function () {
    $('#steering-reika').toggle('slow');
    $('#steering-gur').toggle('slow');
}, 5000);

$('.whatsapp-fixed-btn, .wa-top-container').on('click', function () {
    if ($(window).width() > '580') {
        $('#shadow-main').fadeIn();
        $('#shadow-main').css({ 'background-color': 'rgba(0, 0, 0, .8)' });
    }
});

$('#footer-wa').on('click', function () {
    $('#shadow-main').fadeIn();
    $('#shadow-main').css({ 'background-color': 'rgba(0, 0, 0, .8)' });
});

$('#footer-phone').on('click', function () {
    $('#shadow-main').fadeIn();
    $('#shadow-main').css({ 'background-color': 'rgba(0, 0, 0, .8)' });
});

$('#model-qr').on('click', function () {
    $('#shadow-main').fadeOut();
});

$('#shadow-main').on('click', function () {
    $('#shadow-main').fadeOut();
});

$('.brand-filter').on('change', function () {
    let choosedBrand = [];
    $('.brand-filter').each(function (key, elem) {
        if (elem.checked) {
            choosedBrand.push($(elem).val());
        }
    });

    $('.requestPartNumber-brand').each(function (key, elem) {
        if (!choosedBrand.includes($(elem).text().replace(/\s+/g, ''))) {
            $(elem).parent().css({ 'display': 'none' });
        } else {
            $(elem).parent().css({ 'display': 'grid' });
        }

        if (!choosedBrand.length) {
            $(elem).parent().css({ 'display': 'grid' });
        }
    });
});

$(window).on('scroll', function () {
    let searchFilter = $('#search-res-filter');
    let elemOffsetTop = 0;

    if (searchFilter.length > 0) {
        elemOffsetTop = searchFilter.offset().top;
    }

    let windowYoffset = $(this).scrollTop();

    if (elemOffsetTop > windowYoffset) {
        $('#search-res-filter').css({ 'position': 'sticky', 'top': '110px' });
    } else {
        $('#search-res-filter').removeClass('sticky');
    }
});

$('.close-flash').on('click', function () {
    $(this).parent().slideUp();
});

$('#close-kaspi-ads').on('click', function () {
    $(this).parent().slideUp(400, function () {
        $('#main-header').css({ 'border-bottom': '3px solid #ccc' });
    });
});

$('#three-dots-wrapper').on('click', function () {
    $('#main-mini-shadow').fadeIn();
    $('#side-bar-right-mini').slideDown(500);
});

$('#side-bar-right-mini-close-container').on('click', function () {
    $('#main-mini-shadow').fadeOut(400);
    $('#side-bar-right-mini').slideUp(500);
});

$('#feedback-form-close-container').on('click', function () {
    if ($(this).attr('status') == 'open') {
        $(this).next().slideUp('400', function () {
            $('#feedback-form-close-container').children().first().next().attr('src', '/images/plus-24.png');
        });
        $(this).attr('status', 'close');
    } else {
        $(this).next().slideDown('400', function () {
            $('#feedback-form-close-container').children().first().next().attr('src', '/images/minus-24.png');
        });
        $(this).attr('status', 'open');
    }
});

$('#articles-hide').on('change', function () {
    if ($(this).prop('checked')) {
        $('.requestPartNumber-partnumber').css({ 'visibility': 'hidden' });
    } else {
        $('.requestPartNumber-partnumber').css({ 'visibility': 'visible' });
    }
});

$('.review-item').on('mouseenter', function () {
    $(this).css({ 'transform': 'scale(1.1)', 'transition': 'all 0.5s' });
});

$('.review-item').on('mouseleave', function () {
    $(this).css({ 'transform': 'scale(1)' });
});

// =========================================================
// Маска телефона (уже была с проверкой на null — оставлено как есть)
// =========================================================
const phoneInput = document.getElementById("phone");
if (phoneInput) {
    phoneInput.addEventListener("input", function () {
        let input = phoneInput.value.replace(/\D/g, "");

        if (input.startsWith("8")) {
            input = "7" + input.slice(1);
        }
        if (input.length > 11) {
            input = input.slice(0, 11);
        }

        let formatted = "+7";
        if (input.length > 1) formatted += " (" + input.slice(1, 4);
        if (input.length >= 4) formatted += ") " + input.slice(4, 7);
        if (input.length >= 7) formatted += "-" + input.slice(7, 9);
        if (input.length >= 9) formatted += "-" + input.slice(9, 11);

        phoneInput.value = formatted;

        if (input.length === 11) {
            const errorEl = document.getElementById("error");
            if (errorEl) errorEl.textContent = "";
            phoneInput.style.border = "";
        }
    });
}

// ФИКС: добавлена проверка на существование #phone и #error,
// чтобы функция не падала, если её вызвать на странице без этих полей.
function validatePhone() {
    if (!phoneInput) return true;

    const raw = phoneInput.value.replace(/\D/g, "");
    const error = document.getElementById("error");

    if (raw.length !== 11) {
        if (error) error.textContent = "Убедитесь, что номер телефона введён полностью.";
        phoneInput.style.border = "1px solid #d32f2f";
        return false;
    }

    const valid = /^7\d{10}$/.test(raw);
    if (!valid) {
        if (error) error.textContent = "Проверьте номер телефона.";
        phoneInput.style.border = "1px solid #d32f2f";
        return false;
    }

    if (error) error.textContent = "";
    phoneInput.style.border = "";
    return true;
}

// ФИКС: добавлена проверка на существование #vin
function validateVin() {
    const vinInput = document.getElementById("vin");
    if (!vinInput) return true;

    const value = vinInput.value.trim();
    let error = vinInput.parentElement.querySelector(".vin-error");

    if (!error) {
        error = document.createElement("div");
        error.className = "vin-error";
        error.style.fontSize = "12px";
        error.style.fontStyle = "italic";
        error.style.color = "#d32f2f";
        error.style.marginTop = "4px";
        vinInput.after(error);
    }

    if (value === "") {
        error.textContent = "";
        vinInput.style.border = "";
        return true;
    }

    if (value.length < 8) {
        error.textContent = "VIN / номер кузова должен быть не менее 8 символов.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    if (value.length > 17) {
        error.textContent = "VIN / номер кузова не может быть длиннее 17 символов.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    const cyrillicMatches = value.match(/[А-Яа-яЁёЀ-ӿ]/g);
    if (cyrillicMatches) {
        error.textContent = `В VIN / номере кузова нельзя использовать кириллицу! Найдено: ${cyrillicMatches.join(', ')}`;
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    const latinLetters = (value.match(/[A-Za-z]/g) || []).length;
    if (latinLetters < 2) {
        error.textContent = "VIN / номер кузова должен содержать минимум 2 латинские буквы.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    const digitsCount = (value.match(/\d/g) || []).length;
    if (digitsCount < 1) {
        error.textContent = "VIN / номер кузова должен содержать хотя бы одну цифру.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    if (value.length === 17 && /[IOQ]/i.test(value)) {
        error.textContent = "VIN не должен содержать буквы I, O, Q.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    error.textContent = "";
    vinInput.style.border = "";
    return true;
}

// ФИКС: добавлена проверка на существование #parts
function validateParts() {
    const partsInput = document.getElementById("parts");
    if (!partsInput) return true;

    const value = partsInput.value.trim();
    let error = partsInput.nextElementSibling;

    if (!error || !error.classList.contains("parts-error")) {
        error = document.createElement("div");
        error.className = "parts-error";
        error.style.fontSize = "12px";
        error.style.fontStyle = "italic";
        error.style.color = "#d32f2f";
        error.style.marginTop = "4px";
        partsInput.after(error);
    }

    if (value.length < 10) {
        error.textContent = "Опишите список запчастей подробнее (минимум 10 символов).";
        partsInput.style.border = "1px solid #d32f2f";
        return false;
    }

    if (/^\d+$/.test(value)) {
        error.textContent = "Добавьте описание к списку запчастей.";
        partsInput.style.border = "1px solid #d32f2f";
        return false;
    }

    error.textContent = "";
    partsInput.style.border = "";
    return true;
}

function showWaitongWindow() {
    $('#shadow').show();
    $('#shadow').addClass('d-flex');
    $('#loading').text('Ваш запрос отправляется, пожалуйста ожидайте...');
    $(this).removeClass('btn-success').addClass('btn-secondary');
    return true;
}

// =========================================================
// Форма поиска по VIN с загрузкой фото/PDF
// (уже была полностью обёрнута в проверки — оставлено как есть)
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('vin-request-form');
    if (!form) return;

    const fileInput = document.getElementById('tech_passport');
    const previewList = document.getElementById('photo-preview-list');
    const fileNameBlock = document.getElementById('selected-file-name');
    const vinInput = document.getElementById('vin');
    const vinPhotoError = document.getElementById('vin-photo-error');
    const submitBtn = document.getElementById('send-vin-search-btn');

    if (!fileInput || !previewList || !fileNameBlock || !vinInput || !vinPhotoError || !submitBtn) {
        console.log('VIN form detected but some internal elements are missing');
        return;
    }

    let selectedFiles = [];
    let isSubmitting = false;
    const MAX_FILES = 5;
    const MAX_WIDTH = 1600;
    const IMAGE_QUALITY = 0.72;

    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }

    function showFileMessage(message, isError = false) {
        fileNameBlock.textContent = message;
        fileNameBlock.className = isError ? 'small mt-2 text-danger' : 'form-text mt-2';
    }

    function clearVinPhotoError() {
        vinPhotoError.textContent = '';
    }

    function showVinPhotoError(message) {
        vinPhotoError.textContent = message;
    }

    function updateFileText() {
        if (selectedFiles.length === 0) {
            showFileMessage('Можно прикрепить до 5 файлов (фото или PDF)');
            return;
        }
        showFileMessage(`Выбрано файлов: ${selectedFiles.length}`);
    }

    function renderPreviews() {
        previewList.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const isPdf = file.type === 'application/pdf';
            const reader = new FileReader();

            reader.onload = function (e) {
                const previewContent = isPdf
                    ? `<div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded mb-2 w-100" style="height: 140px; font-size: 40px;">📄</div>`
                    : `<img src="${e.target.result}" alt="preview" class="img-fluid rounded mb-2 w-100" style="height: 140px; object-fit: cover;">`;

                previewList.insertAdjacentHTML('beforeend', `
                    <div class="col-6 col-md-4">
                        <div class="border rounded-3 p-2 h-100 bg-light">
                            ${previewContent}
                            <div class="small text-muted mb-2 text-truncate">${escapeHtml(file.name || 'Файл')}</div>
                            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-photo-btn" data-index="${index}">Удалить</button>
                        </div>
                    </div>
                `);
            };

            if (isPdf) {
                reader.onload({ target: { result: null } });
            } else {
                reader.readAsDataURL(file);
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function validateVinOrPhoto() {
        const vin = vinInput.value.trim();
        if (vin === '' && selectedFiles.length === 0) {
            showVinPhotoError('Укажите VIN или прикрепите хотя бы 1 файл');
            return false;
        }
        clearVinPhotoError();
        return true;
    }

    function removePhoto(index) {
        selectedFiles.splice(index, 1);
        syncInputFiles();
        updateFileText();
        renderPreviews();
    }

    function handleFiles(input) {
        const newFiles = Array.from(input.files);
        if (newFiles.length === 0) return;

        if (selectedFiles.length + newFiles.length > MAX_FILES) {
            showFileMessage(`Максимум ${MAX_FILES} файлов`, true);
            input.value = '';
            return;
        }

        newFiles.forEach(file => {
            if (file.type.startsWith('image/') || file.type === 'application/pdf') {
                selectedFiles.push(file);
            }
        });

        syncInputFiles();
        updateFileText();
        renderPreviews();
        clearVinPhotoError();
        input.value = '';
    }

    async function compressImage(file, maxWidth = MAX_WIDTH, quality = IMAGE_QUALITY) {
        if (file.type === 'application/pdf') return file;

        return new Promise((resolve) => {
            const reader = new FileReader();
            const img = new Image();

            reader.onload = e => img.src = e.target.result;

            img.onload = function () {
                let width = img.width;
                let height = img.height;

                if (width > maxWidth) {
                    height = Math.round(height * (maxWidth / width));
                    width = maxWidth;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(blob => {
                    if (!blob) {
                        resolve(file);
                        return;
                    }
                    const compressedFile = new File(
                        [blob],
                        file.name.replace(/\.\w+$/, '') + '.jpg',
                        { type: 'image/jpeg', lastModified: Date.now() }
                    );
                    resolve(compressedFile);
                }, 'image/jpeg', quality);
            };

            img.onerror = () => resolve(file);
            reader.readAsDataURL(file);
        });
    }

    fileInput.addEventListener('change', function () {
        handleFiles(this);
    });

    vinInput.addEventListener('input', function () {
        if (vinInput.value.trim() !== '' || selectedFiles.length > 0) clearVinPhotoError();
    });

    previewList.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-photo-btn');
        if (btn) removePhoto(Number(btn.dataset.index));
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (isSubmitting || !validateVinOrPhoto()) return;

        if ((window.validateVin && !validateVin()) ||
            (window.validateParts && !validateParts()) ||
            (window.validatePhone && !validatePhone())) {
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Обработка... <span class="spinner-border spinner-border-sm"></span>';

        try {
            if (selectedFiles.length > 0) {
                showFileMessage('Сжимаем изображения...');
                const processed = [];
                for (const file of selectedFiles) {
                    processed.push(await compressImage(file));
                }
                selectedFiles = processed;
                syncInputFiles();
            }

            if (window.showWaitongWindow) showWaitongWindow();
            isSubmitting = true;
            form.submit();
        } catch (error) {
            console.error(error);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Получить подбор';
            showFileMessage('Ошибка при обработке файлов', true);
        }
    });
});

// =========================================================
// Добавление в корзину товаров, найденных через внешние API
// =========================================================
function addToCartFromApi(button, itemData) {
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    $.ajax({
        url: "/cart/add",
        type: "POST",
        data: { '_token': $('meta[name="csrf-token"]').attr('content'), 'data': itemData },
        success: function (data) {
            button.innerHTML = '<i class="fas fa-check text-white"></i>';
            button.classList.replace('btn-primary', 'btn-success');
            updateHeaderCartBadge(data.count, data.total);
        },
        error: function (err) {
            console.error('Ошибка корзины:', err);
            button.innerHTML = 'Ошибка';
            button.disabled = false;
        }
    });
}

$(document).on('click', '.api-buy-btn', function () {
    const btn = $(this);
    const brand = btn.attr('data-brand');
    const article = btn.attr('data-article');
    const priceMargine = btn.attr('data-price-margine');
    const stockQty = parseInt(btn.attr('data-qty')) || 0;
    const name = btn.attr('data-name');
    const supplier = btn.attr('data-supplier');
    const delivery = btn.attr('data-delivery');
    const priceBase = btn.attr('data-price');

    console.log('Пытаемся добавить:', { brand, article, stockQty });

    if (stockQty <= 0) {
        alert('К сожалению, этого товара нет в наличии на выбранном складе.');
        return;
    }

    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    fetch('/cart/add-api', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: JSON.stringify({
            brand: brand,
            article: article,
            name: name,
            price: priceBase,
            retail_price: priceMargine,
            supplier: supplier,
            delivery: delivery,
            quantity: 1
        })
    })
        .then(res => res.json())
        .then(json => {
            if (json.success) {
                if ($('#header-cart-qty').length) {
                    $('#header-cart-qty')
                        .attr('class', 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white')
                        .css('font-size', '0.65rem')
                        .text(json.cart_count + ' шт');
                }
                btn.removeClass('btn-primary btn-success')
                    .addClass('btn-outline-secondary')
                    .html('<i class="fas fa-check"></i> В корзине');
            } else {
                alert('Ошибка при добавлении: ' + (json.message || 'неизвестная ошибка'));
                btn.prop('disabled', false).text('Купить');
            }
        })
        .catch(err => {
            console.error('Ошибка fetch:', err);
            btn.prop('disabled', false).text('Ошибка');
        });
});

// ---- копирование выбранных позиций в WhatsApp/буфер ----
$(document).on('change', '.copy_text', function () {
    let checkedCount = $('.copy_text:checked').length;
    if (checkedCount > 0) {
        $('#copy_text_wrapper').show();
        console.log("Выбрано позиций: " + checkedCount);
    } else {
        $('#copy_text_wrapper').fadeOut();
        console.log("Ничего не выбрано");
    }
});

$(document).on('click', '#copy_text_btn', function () {
    let selectedText = "";
    let checkedBoxes = $('.copy_text:checked');

    if (checkedBoxes.length === 0) {
        alert("Сначала выберите хотя бы одну запчасть!");
        return;
    }

    let mainName = "";
    checkedBoxes.each(function (index) {
        let row = $(this).closest('.requestPartNumberContainer-item');
        let brand = row.find('.requestPartNumber-brand').text().trim();
        let price = row.find('.requestPartNumber-price').text().trim();
        let delivery = row.find('.requestPartNumber-delivery').text().trim();

        if (index === 0) {
            mainName = row.find('.requestPartNumber-name').text().trim();
            selectedText += `⚙️ *${mainName}*\n\n`;
        }

        selectedText += `✔ ${brand} — Цена: ${price} ₸\n`;
    });

    selectedText += "\nGlobal Parts Astana — Запчасти в наличии и на заказ.";

    let buffer = $('#clipboard-buffer');
    buffer.val(selectedText).select();

    try {
        document.execCommand('copy');
        let originalText = $(this).html();
        $(this).removeClass('btn-primary').addClass('btn-success').html('✅ Скопировано!');
        setTimeout(() => {
            $(this).removeClass('btn-success').addClass('btn-primary').html(originalText);
        }, 2000);
    } catch (err) {
        alert('Ошибка при копировании. Попробуйте вручную.');
    }
});

$('.santafe18-21container-item').on('click', function () {
    $(this).submit();
});

setInterval(() => { $('#cant-search-part').fadeIn(600); }, 2000);
setInterval(() => { $('#cant-search-part').fadeOut(600); }, 10000);
