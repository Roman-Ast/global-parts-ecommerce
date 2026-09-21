
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Каналы продаж с отложенной оплатой через маркетплейс (просьба Романа
// 2026-09-21, "как на Kaspi") — оплата в момент оформления заказа
// намеренно зануляется (см. "Ручное создание заказа"), деньги приходят
// позже, автоматически, при выдаче заказа (см. AdminPanelController::
// changeStatus() -> AUTO_PAYOUT_MARKETPLACES). Каждому каналу — свой счёт
// для авто-выбора в форме, найденный по ТЕКСТУ опции (не id — id счёта
// может отличаться между окружениями). Для Ozon это тот же счёт, что и
// у Kaspi Pay — Роман указал его при регистрации на Ozon.
const DEFERRED_MARKETPLACE_ACCOUNTS = {
    kaspi: { include: /kaspi\s*pay/i, exclude: /безнал/i },
    ozon: { include: /kaspi\s*pay/i, exclude: /безнал/i },
    halyk_market: { include: /halyk\s*pay/i, exclude: null },
};

$('.close-flash').on('click', function () {
    $(this).parent().slideUp();
});
$(document).on('click', '.menu-item-container', function () {
    let id = $(this).attr('target');

    $('#content').children().each(function () {
        if($(this).attr('id') != id) {
            $(this).css({'display': 'none'});
        }
    });

    $(`#${id}`).css({'display': 'block'});
});

$('#orders-filter-user').on('change', function () {
    $('#orders-filter-customer option[value="null"]').prop('selected', 'true');
});
$('#orders-filter-customer').on('change', function () {
    $('#orders-filter-user option[value="null"]').prop('selected', 'true');
});

$('#order-filter-btn-submit').on('click', function () {
    data = {
        'customer_phone': $(this).prev().children().first().val(),
        'user_id': $(this).prev().prev().children().first().val(),
        'date_from': $(this).prev().prev().prev().children().first().val(),
        'date_to': $(this).prev().prev().prev().children().first().next().val()
    };

    $.ajax({
        data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
        url: "/orders/filter",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            $('.admin-order-item-wrapper').remove();

            const statuses = {
                'payment_waiting':'ожидание оплаты', 'processing': 'принято в работу', 'supplier_refusal': 'отказ поставщика',
                'arrived_at_the_point_of_delivery': "поступило в ПВЗ", 'issued': "выдано"
            };

           data.filtered_orders.forEach(elem => {
                $('#orders').append(
                    `
                    <div class="admin-order-item-wrapper" aria-target="${elem['id']}">
                        <div class="order-item-header">
                            <div class="order-item-id">0000${elem['id']}</div>
                            <div class="order-item-user-name">
                                <span>${elem['user_name']}</span> 
                                <span style="font-size: 0.7em">${elem['customer_phone'] ? elem['customer_phone']: ''}</span> 
                            </div>
                            <div class="order-item-status">${elem['status']} <img src="/images/clock-wait-16.png"></div>
                            <div class="order-item-date">${elem['date']}</div>
                            <div class="order-item-time">${elem['time']}</div>
                            <div class="admin-order-item-sum">
                                <span style="font-weight: 600;color:green">${elem['sum_with_margine']}</span>
                                <span style="font-style: italic;color:red;font-size: 0.7em">
                                    ${elem['sum']}
                                    %${Math.round((elem['sum_with_margine'] - elem['sum']) * 100 / elem['sum_with_margine'])}
                                </span>
                            </div>
                        </div>
                    </div>
                    `
                );

                elem.products.forEach(element => {
                    $(`.admin-order-item-wrapper[aria-target="${elem['id']}"]`).append(
                        `
                        <div class="admin-order-item-products-content">
                            <div class="order-products-searched_number">
                                <div class="order-products-searched_number">${element['searched_number']}</div>
                            </div>
                            <div class="order-products-article">
                                ${element['article']}
                            </div>
                            <div class="order-products-brand">
                                ${element['brand']}
                            </div>
                            <div class="order-products-name">
                                ${element['name']}
                            </div>
                            <div class="order-products-qty">
                                ${element['qty']}
                            </div>
                            <div class="order-products-price">
                                ${element['priceWithMargine']}
                            </div>
                            <div class="order-products-item_sum">
                                ${element['itemSumWithMargine']}
                            </div>
                            <div class="order-products-fromStock">
                                ${element['fromStock']}
                            </div>
                            <div class="order-products-deliveryTime">
                                ${element['deliveryTime']}
                            </div>
                            <div class="order-products-status">
                                <select name="order_product_status" class="order_product_status form-select">
                                    
                                </select>
                            </div>
                            <div class="change_status">
                                <input type="hidden" value=" ${element['id']}">
                                <button class="btn btn-sm btn-info change_status_submit">Сменить</button>
                            </div>
                        </div>
                        `
                    );
               });
               $.each(statuses, function (i, item) {
                $('.order_product_status').append($('<option>', {
                  value: i, 
                  text: item
                }));
            });
               
           });
        },
        error: function (error) {
           console.log(error);
        }
     });
});

$('#order-filter-btn-drop').on('click', function () {
    
    $.ajax({
        data: {'_token': $('meta[name="csrf-token"]').attr('content')},
        url: "/orders/filter/drop",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            $('.admin-order-item-wrapper').remove();

            const statuses = {
                'payment_waiting':'ожидание оплаты', 'processing': 'принято в работу', 'supplier_refusal': 'отказ поставщика',
                'arrived_at_the_point_of_delivery': "поступило в ПВЗ", 'issued': "выдано"
            };

           data.orders.forEach(elem => {
                $('#orders').append(
                    `
                    <div class="admin-order-item-wrapper" aria-target="${elem['id']}">
                        <div class="order-item-header">
                            <div class="order-item-id">0000${elem['id']}</div>
                            <div class="order-item-user-name">
                                <span>${elem['user_name']}</span> 
                                <span style="font-size: 0.7em">${elem['customer_phone'] ? elem['customer_phone']: ''}</span> 
                            </div>
                            <div class="order-item-status">${elem['status']} <img src="/images/clock-wait-16.png"></div>
                            <div class="order-item-date">${elem['date']}</div>
                            <div class="order-item-time">${elem['time']}</div>
                            <div class="admin-order-item-sum">
                                <span style="font-weight: 600;color:green">${elem['sum_with_margine']}</span>
                                <span style="font-style: italic;color:red;font-size: 0.7em">
                                    ${elem['sum']}
                                    %${Math.round((elem['sum_with_margine'] - elem['sum']) * 100 / elem['sum_with_margine'])}
                                </span>
                            </div>
                        </div>
                    </div>
                    `
                );

                elem.products.forEach(element => {
                    $(`.admin-order-item-wrapper[aria-target="${elem['id']}"]`).append(
                        `
                        <div class="admin-order-item-products-content">
                            <div class="order-products-searched_number">
                                <div class="order-products-searched_number">${element['searched_number']}</div>
                            </div>
                            <div class="order-products-article">
                                ${element['article']}
                            </div>
                            <div class="order-products-brand">
                                ${element['brand']}
                            </div>
                            <div class="order-products-name">
                                ${element['name']}
                            </div>
                            <div class="order-products-qty">
                                ${element['qty']}
                            </div>
                            <div class="order-products-price">
                                ${element['priceWithMargine']}
                            </div>
                            <div class="order-products-item_sum">
                                ${element['itemSumWithMargine']}
                            </div>
                            <div class="order-products-fromStock">
                                ${element['fromStock']}
                            </div>
                            <div class="order-products-deliveryTime">
                                ${element['deliveryTime']}
                            </div>
                            <div class="order-products-status">
                                <select name="order_product_status" class="order_product_status form-select">
                                    
                                </select>
                            </div>
                            <div class="change_status">
                                <input type="hidden" value=" ${element['id']}">
                                <button class="btn btn-sm btn-info change_status_submit">Сменить</button>
                            </div>
                        </div>
                        `
                    );
               });
               $.each(statuses, function (i, item) {
                $('.order_product_status').append($('<option>', {
                  value: i, 
                  text: item
                }));
            });
               
           });

           
        },
        error: function (error) {
           console.log(error);
        }
     });
});

$('#add_parts_list_item').on('click', function (params) {
    $('#manually-order-parts-list').append(
        `
        <div class="manually-order-parts-list-item">
            <div class="manually-order-parts-list-item-header">
                <label class="form-label parts-list-item">Артикул</label>
                <label class="form-label">Бренд</label>
                <label class="form-label">Наименование</label>
                <label class="form-label">Кол-во</label>
                <label class="form-label">С/С</label>
                <label class="form-label">Розница</label>
                <label class="form-label">Поставщик</label>
                <label class="form-label">Доставка</label>
            </div>
            <div class="manually-order-parts-list-item-content">
                <input type="text" class="form-control" name="article" required>
                <input type="text" class="form-control" name="brand" required>
                <input type="text" class="form-control" name="name" required>
                <input type="number" class="form-control manually-order-parts-list-item-qty" name="qty" required>
                <input type="number" class="form-control manually-order-parts-list-price" name="price" required>
                <input type="number" class="form-control manually-order-parts-list-price-with-margine" name="priceWithMargine" required>
                    <select name="from_stock" class="order_product_item_supplier">
                        <option disabled selected>Выбери поставщика</option>
                    </select>
                <input type="date" class="form-control" name="deliveryTime" required>
            </div>
         </div>               
        `
    );

    const $newSelect = $('.order_product_item_supplier').last();
    $.each(window.suppliersList, function (i, supplier) {
        $newSelect.append($('<option>', { value: supplier.id, text: supplier.name }));
    });
});

// ---- маска телефона под казахстанский формат +7 (7XX) XXX-XX-XX ----
$(document).on('input', '#manually-order-customer-phone', function () {
    let digits = $(this).val().replace(/\D/g, '');

    if (digits.startsWith('8')) {
        digits = '7' + digits.slice(1);
    }
    if (digits.length && !digits.startsWith('7')) {
        digits = '7' + digits;
    }
    digits = digits.slice(0, 11);

    let formatted = digits.length ? '+7' : '';
    if (digits.length > 1) formatted += ' (' + digits.slice(1, 4);
    if (digits.length >= 4) formatted += ') ' + digits.slice(4, 7);
    if (digits.length >= 7) formatted += '-' + digits.slice(7, 9);
    if (digits.length >= 9) formatted += '-' + digits.slice(9, 11);

    $(this).val(formatted);
});

$(document).on('focus', '#manually-order-customer-phone', function () {
    if (!$(this).val()) {
        $(this).val('+7 (');
    }
});

$('#manually-order-submit').on('click', function () {
    let data = {
        orderInfo: [],
        products: [],
        paymentInfo: [],
        // Не в orderInfo намеренно — тот массив строго позиционный
        // (orderInfo[4] = sale_channel и т.д. по всему контроллеру),
        // добавление туда нового поля сдвинуло бы все существующие индексы.
        kaspiBypassed: $('#manualy_order_kaspi_bypassed').is(':checked') ? 1 : 0,
    };

    $('.manually-order-main-info').each(function (key, elem) {
        data.orderInfo.push($(elem).val());
    });

    $('.manually-order-parts-list-item-content').each(function (productId, elem) {
        data.products[productId] = [];

        let arr = $(elem).children();
        $.each(arr, function (key, elem) {
            data.products[productId].push($(elem).val());
        });
    });

    $('#manualy-order-payment-details-body').each(function (productId, elem) {
        let arr = $(elem).children();
        $.each(arr, function (key, elem) {
            data.paymentInfo.push($(elem).val());
        });
    });

    let allowToOrder = true;
    let warning_msg = '';

    // проверка полей заказа (клиент, канал продаж и т.д.)
    $('.manually-order-main-info').each(function () {
        const val = $(this).val();
        const name = $(this).attr('name');

        if (!val || val.toString().trim() === '') {
            allowToOrder = false;
            warning_msg = 'Не все поля заполнены в основной информации о заказе!';
            return;
        }

        if (name === 'customer_phone') {
            const digitsOnly = val.replace(/\D/g, '');
            if (digitsOnly.length < 10) {
                allowToOrder = false;
                warning_msg = 'Некорректный номер телефона клиента!';
            }
        }
    });

    // проверка товарных позиций
    $('.manually-order-parts-list-item').each(function (rowIndex) {
        const row = $(this);

        row.find('.manually-order-parts-list-item-content').children().each(function () {
            const val = $(this).val();
            if (!val || val.toString().trim() === '') {
                allowToOrder = false;
                warning_msg = `Не все поля заполнены в товаре #${rowIndex + 1}!`;
            }
        });

        const qty = parseFloat(row.find('input[name="qty"]').val());
        const price = parseFloat(row.find('input[name="price"]').val());
        const priceWithMargine = parseFloat(row.find('input[name="priceWithMargine"]').val());

        if (isNaN(qty) || qty <= 0) {
            allowToOrder = false;
            warning_msg = `Товар #${rowIndex + 1}: количество должно быть больше 0!`;
        }
        if (isNaN(price) || price <= 0) {
            allowToOrder = false;
            warning_msg = `Товар #${rowIndex + 1}: С/С должна быть больше 0!`;
        }
        if (isNaN(priceWithMargine) || priceWithMargine <= 0) {
            allowToOrder = false;
            warning_msg = `Товар #${rowIndex + 1}: розничная цена должна быть больше 0!`;
        }
    });

    if (!$('#manualy_order_sale_channel').val()) {
        allowToOrder = false;
        warning_msg = 'Не заполнен канал продаж!';
    }

    // проверка деталей оплаты
    $('#manualy-order-payment-details-body').children().each(function () {
        const name = $(this).attr('name');
        const val = $(this).val();

        if (name === 'comments') {
            return true;
        }

        if (!val || val.toString().trim() === '') {
            allowToOrder = false;
            warning_msg = 'Не все поля заполнены в деталях оплаты!';
            return false;
        }
    });

    // Для маркетплейсов с отложенной оплатой (Kaspi/Ozon/Halyk Market)
    // сумма оплаты в момент оформления заказа ВСЕГДА 0 — деньги приходят
    // только когда заказ реально выдан клиенту (см. changeStatus() ->
    // 'issued'), не в момент создания заказа. Старая проверка "сумма > 0"
    // осталась от старой логики (до разделения "заказ создан" и "оплата
    // получена") и блокировала корректно заполненный 0 как будто это
    // пустое поле.
    const paymentSaleChannel = $('#manualy_order_sale_channel').val();
    const paymentAmount = parseFloat($('#manualy-order-payment-details-amount').val());
    // 0 допустим только для НАСТОЯЩЕГО заказа маркетплейса (ждём оплату
    // после выдачи) — заказ "мимо маркетплейса" платится сразу, как любой
    // другой канал (см. DEFERRED_MARKETPLACE_ACCOUNTS ниже).
    const isRealKaspiDeferred = (paymentSaleChannel in DEFERRED_MARKETPLACE_ACCOUNTS) && !data.kaspiBypassed;
    const paymentAmountInvalid = isNaN(paymentAmount)
        || paymentAmount < 0
        || (paymentAmount === 0 && !isRealKaspiDeferred);

    if (paymentAmountInvalid) {
        allowToOrder = false;
        warning_msg = 'Сумма оплаты должна быть больше 0!';
    }

    if (!allowToOrder) {
        $('#alert-admin').removeAttr('class');
        $('#alert-admin').addClass('alert alert-warning');
        $('#alert-admin').html(warning_msg);
        $('#alert-admin').slideDown();
        setTimeout(() => {
            $('#alert-admin').slideUp()
        }, 3000);
        return;
    }

    console.log(data);

    $.ajax({
        data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
        url: "/manually_make_order",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            $('#alert-admin').removeAttr('class');
            $('#alert-admin').addClass('alert alert-success');
            $('#alert-admin').html(data.message + ' страница будет перезагружена...');
            $('#alert-admin').slideDown();
            setTimeout(() => {
                $('#alert-admin').slideUp();
            }, 3000);

            setTimeout(() => {
                location.reload();
            }, 1000);
        },
        error: function (data) {
            console.log(data);
        }
    });
});

//cкрываем/показываем счета при возврате
$('#supplier_refund_mode').on('change', function () {
    if ($(this).val() === 'credit') {
        $('#account_id_in_wrapper').hide();
        $('#account_id_in').prop('required', false);
    } else {
        $('#account_id_in_wrapper').show();
        $('#account_id_in').prop('required', true);
    }
});

//скрыть/ показать статистику по каналам продаж
$('#show-close-admin-panel-statistic-wrapper').on('click', function () {
    if ($(this).parent().next().attr('status') == 'closed') {
       $(this).parent().next().slideDown('400', function () {
        $('#admin-panel-orders-by-channel-header').children().first().next().attr('src', '/images/minus-24.png')
     });
       $(this).parent().next().attr('status', 'opened');
    } else {
       $(this).parent().next().slideUp('400', function () {
        $('#admin-panel-orders-by-channel-header').children().first().next().attr('src', '/images/plus-24.png')
     });
       $(this).parent().next().attr('status', 'closed');
    }
 });

 $('#show-close-admin-panel-graphics').on('click', function () {
    if ($(this).parent().next().attr('status') == 'closed') {
       $(this).parent().next().slideDown('200', function () {
        $('#stats_graphics_header').children().first().next().attr('src', '/images/minus-24.png')
     });
       $(this).parent().next().attr('status', 'opened');
    } else {
       $(this).parent().next().slideUp('200', function () {
        $('#stats_graphics_header').children().first().next().attr('src', '/images/plus-24.png')
     });
       $(this).parent().next().attr('status', 'closed');
    }
 });

 //изменить кол-во и/или цену в товарах в офисе
 $('.good_in_office_delete').on('click', function () {
    let deletingItemId = $(this).parent().parent().children().first().val();
    data = {
        deletingItemId: deletingItemId
    }
    
    $.ajax({
        data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
        url: "/delete_good_in_office",
        type: "POST",
        dataType: 'json',
        success: function (data) {
            $('#alert-admin-goods-in-office').removeAttr('class');
            $('#alert-admin-goods-in-office').addClass('alert alert-success');
            $('#alert-admin-goods-in-office').html(' товар успешно удален...');
            $('#alert-admin-goods-in-office').slideDown();
            setTimeout(() => {
                $('#alert-admin-goods-in-office').slideUp();
                
            }, 3000);

            setTimeout(() => {
                location.reload();
            }, 2500);
        },
        error: function (data) {
            console.log(data);
        }
     });    
});

//подсчет итогов в админке при создании заказа в ручную
$(document).on('input', '.manually-order-parts-list-item-qty, .manually-order-parts-list-price, .manually-order-parts-list-price-with-margine',function () {
    let sumWithMargine = 0;
    let primeCostSum = 0;
    let totalQty = 0;
    let arr = [];

    $('.manually-order-parts-list-item-qty').each(function () {
        sumWithMargine += $(this).val() * $(this).next().next().val();
        primeCostSum += $(this).val() * $(this).next().val();
        totalQty += +$(this).val();
    });

    $('#manualy-order-total-sum-with-margine-num').html(sumWithMargine);

    // Маркетплейсы с отложенной оплатой (Kaspi/Ozon/Halyk Market) платят
    // только после того, как клиент получит заказ (см. логику
    // changeStatus() на статус "выдано") — раньше это поле всегда
    // автоматически подставляло полную сумму заказа, и для Kaspi это
    // приводило к тому, что деньги в кассе "приходили" сразу при
    // оформлении заказа, хотя Kaspi ещё ничего не заплатил. Живой случай
    // 2026-08-31: заказ #56 показал 58000 прихода в день оформления.
    // Если клиент пришёл через маркетплейс, но оформился МИМО него
    // (checkbox "kaspi_bypassed") — оплата реально уже получена сразу,
    // как у любого другого канала, ждать нечего (просьба Романа 2026-09-18).
    const saleChannel = $('#manualy_order_sale_channel').val();
    const kaspiBypassed = $('#manualy_order_kaspi_bypassed').is(':checked');
    const isDeferredMarketplace = (saleChannel in DEFERRED_MARKETPLACE_ACCOUNTS) && !kaspiBypassed;
    $('#manualy-order-payment-details-amount').val(isDeferredMarketplace ? 0 : sumWithMargine);

    $('#manualy-order-total-prime-cost-sum-inner').html(primeCostSum);
    $('#manualy-order-total-qty-inner').html(totalQty);
});

// При смене канала продаж на маркетплейс с отложенной оплатой (или с
// него) сразу пересчитываем сумму оплаты по тому же правилу — иначе поле
// остаётся с суммой, введённой до переключения канала.
$(document).on('change', '#manualy_order_sale_channel', function () {
    const channel = $(this).val();
    const accountMatcher = DEFERRED_MARKETPLACE_ACCOUNTS[channel];
    const isDeferredChannel = !!accountMatcher;

    // Чекбокс "оформлено мимо маркетплейса" имеет смысл только для каналов
    // из DEFERRED_MARKETPLACE_ACCOUNTS — для остальных скрываем и сбрасываем,
    // чтобы случайно не протащить его состояние с прошлого выбора.
    $('#manualy_order_kaspi_bypassed_wrapper').toggle(isDeferredChannel);
    if (!isDeferredChannel) {
        $('#manualy_order_kaspi_bypassed').prop('checked', false);
    }

    $('.manually-order-parts-list-item-qty').first().trigger('input');

    // Такой канал всегда платит через один и тот же счёт — просьба Романа
    // 2026-09-18 (Kaspi), расширено на Ozon/Halyk Market 2026-09-21, чтобы
    // не выбирать его руками каждый раз. Ищем по ТЕКСТУ опции (не по id —
    // id счёта может отличаться между окружениями), не жёстко "точное
    // совпадение", чтобы не сломаться от лишнего пробела/регистра в
    // названии счёта. Поле остаётся обычным select — можно поменять руками,
    // если понадобится другой счёт. Не подставляем счёт, если заказ
    // оформлен МИМО маркетплейса (см. выше) — деньги в этом случае пришли
    // не через него.
    if (isDeferredChannel && !$('#manualy_order_kaspi_bypassed').is(':checked')) {
        const $accountSelect = $('#manualy_order_account');
        const $matchedOption = $accountSelect.find('option').filter(function () {
            const text = $(this).text();
            return accountMatcher.include.test(text) && !(accountMatcher.exclude && accountMatcher.exclude.test(text));
        }).first();

        if ($matchedOption.length) {
            $accountSelect.val($matchedOption.val());
        }
    }
});

// Тот же пересчёт суммы оплаты нужен и при переключении самого чекбокса
// (не только канала) — иначе поле "Сумма" не обновится, если поменять
// галочку уже ПОСЛЕ того, как канал Kaspi выбран.
$(document).on('change', '#manualy_order_kaspi_bypassed', function () {
    $('.manually-order-parts-list-item-qty').first().trigger('input');
});

//хуки для фильтрации полей в создании ДДС
$('.cft-direction').on('change', function () {
    if ($(this).val() == 'in') {
        $('.expense-categories').attr('disabled', true);
    } else {
        $('.expense-categories').attr('disabled', false);
    }
});

$('.cft-direction').on('change', function () {
    let direction = $(this).val();
    const $categorySelect = $('.cashflow-categories');
    const previouslySelected = $categorySelect.val();
    let selectedOptionStillValid = false;

    $categorySelect.find('option').each(function () {
        let optionDirection = $(this).attr('data-direction');

        if (optionDirection && optionDirection !== direction) {
            $(this).prop('disabled', true);
        } else {
            $(this).prop('disabled', false);
        }
        if ($(this).val() == 'initial') {
            $(this).prop('disabled', true);
        }
        if ($(this).val() == previouslySelected && !$(this).prop('disabled')) {
            selectedOptionStillValid = true;
        }
    });

    // Раньше выбранная категория могла остаться "выбранной" визуально,
    // даже после того как её саму отключили (не подходит новому
    // направлению) — форма тогда тихо уходила с пустым
    // cashflow_category_id вместо того, чтобы потребовать выбрать заново
    // (2026-09-02, реальный случай — расход "еда" сохранился без
    // категории). Явно сбрасываем на плейсхолдер, чтобы required снова
    // сработал как надо.
    if (!selectedOptionStillValid) {
        $categorySelect.val('initial');
        $categorySelect.trigger('change');
    }
});

$('.cashflow-categories').on('change', function () {
    if ($(this).val() != 2) {
        $('.expense-categories').attr('disabled', true);
        //$('.subcategory').attr('disabled', true);
    } else {
        $('.expense-categories').attr('disabled', false);
        $('.subcategory').attr('disabled', false);
    }
    
    if ($(this).val() == 3 || $(this).val() == 4 ) {
        // Роман 2026-09-21: завёл "Оплата поставщику" без выбора поставщика,
        // форма сохранила запись — поле было только disabled/enabled, но
        // никогда не required, браузер не блокировал отправку без выбора.
        $('.suppliers').attr('disabled', false).attr('required', true);
    } else {
        $('.suppliers').attr('disabled', true).removeAttr('required');
    }

    if ($(this).val() == 1) {

        $.ajax({
            url: "/additional-payment",
            type: "GET",
            dataType: 'json',
            success: function (data) {
                let options = '<option selected disabled>Выбери заказ</option>';

                data.forEach(function(order){
                    let d = new Date(order.date);

                    let date = d.toLocaleDateString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: '2-digit'
                    });

                    options += `
                        <option value="${order.id}">
                            #${order.id} | ${order.customer_phone} | ${order.sum_with_margine}₸ | ${date}
                        </option>
                    `;

                });

                $('.cashflow-categories').parent().append(`
                    <div class="mb-3" id="orders-by-request">
                        <label class="cft-header-item form-label">Заказ</label>
                        <select class="orders form-select" name="order_id">
                            ${options}
                        </select>
                    </div>
                `);
            },
            error: function (data) {
                console.log(data);
            }
        });
    } else if ($(this).val() == 3) {
        // Оплата поставщику — подписываем операцию сама, чтобы "Описание"
        // в "Последние операции" на дашборде не оставалось пустым, если
        // забыл вписать вручную (живой случай 2026-08-31).
        $('#orders-by-request').remove();
        $('.subcategory').val('Оплата поставщику');
    } else if ($(this).val() == 8) {
        // Личное изъятие — тот же живой случай, что и с оплатой поставщику.
        $('#orders-by-request').remove();
        $('.subcategory').val('Личное изъятие');
    } else {
        $('#orders-by-request').remove();
        $('.subcategory').val('');
    }
});

// Оплата поставщику — при выборе поставщика подставляем в сумму весь его
// текущий долг (data-debt на <option>), дальше руками правится до нужной
// частичной суммы, если платим не полностью.
$(document).on('change', '.suppliers', function () {
    if ($('.cashflow-categories').val() != 3) {
        return;
    }

    const debt = $(this).find('option:selected').data('debt');

    if (debt) {
        $('.cft-amount').val(debt);
    }
});

$(document).on('change', '.orders', function () {
    let orderId = $(this).val();
    let text = `Доплата по заказу #${orderId}`;
    let phonetext = $(this).find('option:selected').text();
    let phone = phonetext.split('|')[1].trim();

    $('.counterparty').val(phone);
    $('input[name="subcategory"]').val('Доплата по заказу');
    $('input[name="comment"]').val(text);
});


//подтягиваем данные заказа в форму возврата от клиента
$('#cr_order_id').on('change', function () {
    let data = {
        order_id: $(this).val()
    };

    //заполняем товары из заказа
    $.ajax({
        url: "/choose_products_from_order",
        type: "POST",
        dataType: "json",
        data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
        success: function (products) {
            let select = $('#cr_order_products');
            select.find('option').remove();
            select.append('<option value="">Выберите товар</option>');

            products.forEach(function(product){
                select.append(
                    `<option value="${product.id}" 
                    data-supplier_name="${product.fromStock}" 
                    data-supplier_id="${product.supplier_id}" 
                    data-qty="${product.qty}"
                    data-price="${product.price}"
                    data-iswm="${product.itemSumWithMargine}"
                    >
                        ${product.article} | ${product.brand} | ${product.name}
                    </option>`
                );
            });
        },
        error: function (xhr) {
            console.log(xhr);
        }
    });

    //заполняем данные клиента
    $('#customer_data').val($(this).find('option:selected').data('customer-data'));
    $('#customer_phone').val($(this).find('option:selected').data('customer-phone'));
    $('#customer_id').val($(this).find('option:selected').data('customer-id'));
});

//заполняем данные товара
$(document).on('change','#cr_order_products', function () {
    $('#cr_supplier_name').val($(this).find('option:selected').data('supplier_name'));
    $('#cr_supplier_id').val($(this).find('option:selected').data('supplier_id'));
    $('#cr_qty').val($(this).find('option:selected').data('qty'));
    //вставим контрольное значение кол-ва товара, чтоб не могли вернуть больше чем продали
    $('#control_cr_qty').val($(this).find('option:selected').data('qty'));
    $('#cr_product_price').val($(this).find('option:selected').data('iswm') / $(this).find('option:selected').data('qty'));
    $('#customer_refund_amount').val($(this).find('option:selected').data('iswm'));

    //зполняем данные по поставщику
    $('#supplier_purchase_price').val($(this).find('option:selected').data('price'));
    $('#supplier_refund_amount').val($(this).find('option:selected').data('price') * $(this).find('option:selected').data('qty'));
});

//проверка, чтоб кол-во возвращаемого товара не было больше отпущенного
$('#cr_qty').on('input', function () {
    if (parseFloat($(this).val()) > parseFloat($('#control_cr_qty').val()) || $(this).val() == '') {
        $('#cr_qty_error').text('Кол-во возврата превышает проданное или поле пустое');
        $('#cr_qty').addClass('is-invalid');
        $(this).val($('#control_cr_qty').val());
        
    } else {
        $('#cr_qty').removeClass('is-invalid');
        $('#cr_qty_error').text('');
    }
    
});

//проверка чтоб сумма возвращенных средств не была больше факта
$('#customer_refund_paid').on('input', function () {
    if (parseFloat($(this).val()) > parseFloat($('#customer_refund_amount').val()) || parseFloat($(this).val()) <= 0) {
        $('#customer_refund_paid').addClass('is-invalid');
        $('#cr_customer_refund_paid_error').text('сумма возврата не может быть больше оплаченной!');
        $(this).val($('#customer_refund_amount').val());
    } else {
        $('#customer_refund_paid').removeClass('is-invalid');
        $('#cr_customer_refund_paid_error').text('');
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const qtyInput = document.querySelector('.qty-input');
    const salePriceInput = document.querySelector('.sale-price-input');
    const customerRefundAmountInput = document.querySelector('.customer-refund-amount-input');
    const supplierPurchasePriceInput = document.querySelector('.supplier-purchase-price-input');
    const supplierRefundAmountInput = document.querySelector('.supplier-refund-amount-input');

    function recalcAmounts() {
        const qty = parseFloat(qtyInput?.value || 0);
        const salePrice = parseFloat(salePriceInput?.value || 0);
        const purchasePrice = parseFloat(supplierPurchasePriceInput?.value || 0);

        if (customerRefundAmountInput && document.activeElement !== customerRefundAmountInput) {
            customerRefundAmountInput.value = (qty * salePrice).toFixed(2);
        }

        if (supplierRefundAmountInput && document.activeElement !== supplierRefundAmountInput) {
            supplierRefundAmountInput.value = (qty * purchasePrice).toFixed(2);
        }
    }

    qtyInput?.addEventListener('input', recalcAmounts);
    salePriceInput?.addEventListener('input', recalcAmounts);
    supplierPurchasePriceInput?.addEventListener('input', recalcAmounts);
});



