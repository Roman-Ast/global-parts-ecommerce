
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$('.close-flash').on('click', function () {
    $(this).parent().slideUp();
});
$('.menu-item-container').on('click', function () {
    let id = $(this).attr('target');
   
    $('#content').children().each(function () {
        if($(this).attr('id') != id) {
            $(this).css({'display': 'none'});
        }
    });

    $(`#${$(this).attr('target')}`).css({'display': 'block'});
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
                'arrived_at_the_point_of_delivery': "поступило в ПВЗ", 'issued': "выдано", 'returned': 'возвращено'
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
                'arrived_at_the_point_of_delivery': "поступило в ПВЗ", 'issued': "выдано", 'returned': 'возвращено'
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
    const suppliers = {
        '1' : 'Шатэ-М',
            '2' : 'Росско',
            '3' : 'Автотрейд',
            '4' : 'Тисс',
            '5' : 'Армтек',
            '6' : 'Фаэтон',
            '7' : 'Автопитер',
            '8' : 'Автозакуп',
            '9' : 'emex',
            '10' : 'Рулим',
            '11' : 'Radle', 
            '12' : 'Фебест',
            '13' : 'Корея Танат',
            '14' : 'Кулан',
            '15' : 'Форумавто',
            '16' : 'Китайцы Алматы',
            '17' : 'Китай Игорь',
            '18' : 'Вольтаж Астана',
            '19' : 'КЗ стартер',
            '20' : 'СС моторс Талгат',
            '21' : 'Герат Астана',
            '22' : 'Кайнар Тима',
            '23' : 'заказ авто',
            '24' : 'Кореан Автопартс',
            '25' : 'Алемавто',
            '26': 'Ердос Автомарт ',
            '27' : 'Сторонние'
    };

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

    $.each(suppliers, function (val, elem) {
        $('.order_product_item_supplier').append($('<option>', { value: val, text: elem }));
    });
});

$('#manually-order-submit').on('click', function () {
    let data = {
        orderInfo: [],
        products: [],
        paymentInfo: [],
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

    $('.manually-order-parts-list-item-content').children().each(function (productId, elem) {
        if (!$(elem).val()) {
            allowToOrder = false;
            warning_msg = 'Не все поля заполнены в информации о товарах!'
            return;
        }
        if (!$('#manualy_order_sale_channel').val()) {
            allowToOrder = false;
            warning_msg = 'Не заполнен канал продаж!'
            return;
        }
    });

   /*$('#manualy-order-payment-details-body').children().each(function (productId, elem) {
        if (!$(elem).val()) { 
            if ($(elem).attr('name') == 'comments') {
                return true;    
            }
            allowToOrder = false;
            warning_msg = 'Не все поля заполнены в деталях оплаты!'
            return false;
        }
    });*/

    if (!allowToOrder) {
        $('#alert-admin').addClass('alert-warning');
        $('#alert-admin').html(warning_msg);
        $('#alert-admin').slideDown();
        setTimeout(() => {
            $('#alert-admin').slideUp()
        }, 3000);
        return;
    }
    console.log(data);
    //return;
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
    $('#manualy-order-payment-details-amount').val(sumWithMargine);
    $('#manualy-order-total-prime-cost-sum-inner').html(primeCostSum);
    $('#manualy-order-total-qty-inner').html(totalQty);
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

    $('.cashflow-categories option').each(function () {
        let optionDirection = $(this).attr('data-direction');
        
        if (optionDirection && optionDirection !== direction) {
            $(this).prop('disabled', true);
        } else {
            $(this).prop('disabled', false);
        }
        if ($(this).val() == 'initial') {
            $(this).prop('disabled', true);
        }
    });
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
        $('.suppliers').attr('disabled', false);
        
    } else {
        $('.suppliers').attr('disabled', true);
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
    } else {
        $('#orders-by-request').remove();
        $('.subcategory').val('');
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



