

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
        'shtm': 'Шатэ-М',
        'rssk': 'Росско',
        'trd': 'Автотрейд',
        'tss': 'Тисс',
        'rmtk': 'Армтек',
        'phtn': 'Фаэтон',
        'atptr': 'Автопитер',
        'rlm': 'Рулим',
        'leopart': 'Леопарт', 
        'fbst': 'Фебест',
        'frmt': 'Форумавто',
        'Krn': 'Корея',
        'thr':  'Сторонние',
        'china_ata': 'Китайцы Алматы'
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
        products: []
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

    let allowToOrder = true;

    $('.manually-order-parts-list-item-content').children().each(function (productId, elem) {
        if (!$(elem).val()) {
            allowToOrder = false;
            warning_msg = 'Не все поля заполнены!'
            return;
        }
        if (!$('#manualy_order_sale_channel').val()) {
            allowToOrder = false;
            warning_msg = 'Не заполнен канал продаж!'
            return;
        }
    });
    if (!allowToOrder) {
        $('#alert-admin').addClass('alert-warning');
        $('#alert-admin').html(warning_msg);
        $('#alert-admin').slideDown();
        setTimeout(() => {
            $('#alert-admin').slideUp()
        }, 3000);
        return;
    }
    
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
    $('#manualy-order-total-prime-cost-sum-inner').html(primeCostSum);
    $('#manualy-order-total-qty-inner').html(totalQty);
});
