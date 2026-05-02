//модальное окно для показа и пролистывания отзывов

$(window).on('load', function () {
   //показываем кнопку "показать еще", если список оригинальных номеров больше 10
   let counter = 0;
   $('#requestPartNumberContainer').children().each(function () {
      if(counter > 10) {
         $(this).css({'display': 'none'});
      }
      counter ++;
   });

   if (counter > 10) {
      $('#show-other-items').css({'display': 'block'});
      $('#show-other-items a').text(`Показать еще ${$('#requestPartNumberContainer').children().length - Number($('#show-other-items').attr('counter'))} из ${$('#requestPartNumberContainer').children().length} (по 10)`);
   }

   //пагинация
   const perPage = 50;

   if ($('#crossesContainer-to-order').children().length > perPage) {
      $('#crossesContainer-to-order').children().each(function (key, elem) {
         if (key > perPage) {
            $(this).css({'display': 'none'})
         }
      });

      const pageCount = Math.ceil($('#crossesContainer-to-order').children().length / perPage);
      
      $('.pagination-nav').css({'display': 'flex'});

      $('.pagination-nav ul').children().each(function (key, elem) {
         if (Number($(elem).children().first().attr('page-num')) > pageCount) {
            $(elem).css({'display': 'none'});
         }
      });
   }
   //проверка статуса returned
   $('.order_product_status').each(function (key, elem) {
      if ($(elem).find('option:selected').val() == 'returned') {
         $(this).attr('disabled', true);
         $(this).parent().next().children().first().next().attr('disabled', true);
      }
   });

   if($(location).attr('href').includes('getCatalog') && $(location).attr('href').includes('only_on_stock')) {
      $('#stock_or_order').prop('checked', 'checked');
      $('#stock_or_order').attr('disabled', 'disabled');
   } else if ($(location).attr('href').includes('getCatalog') ) {
      $('#stock_or_order').attr('disabled', 'disabled');
   };

   if ($(window).width() <= '580') {
      $('.whatsapp-fixed-btn').attr('href', 'https://wa.me/77087172549?text=Здравствуйте,%20пишу%20вам%20с%20сайта.');
      $('.wa-top-container').attr('href', 'https://wa.me/77087172549?text=Здравствуйте,%20пишу%20вам%20с%20сайта.')
   }
	
	//показ wa-qr
   $('.whatsapp-fixed-btn, .wa-top-container').on('click', function (e) {
      // Если экран больше 580, показываем QR и ОТМЕНЯЕМ переход по ссылке
      if ($(window).width() > 580) {
         e.preventDefault(); // Вот это остановит переход на ватсап на ПК
         $('#shadow-main').fadeIn();
         $('#shadow-main').css({'background-color': 'rgba(0, 0, 0, .8)'});
      }
      // Если меньше 580, e.preventDefault() не сработает, 
      // и браузер просто перейдет по ссылке из href (откроет WhatsApp)
   });
});

//очистка строки поиска
document.getElementById('search-bar-container').addEventListener('submit', function (e) {
    let input = document.getElementById('searchBarInput');
    // Удаляем всё, кроме букв и цифр
    let cleanVal = input.value.replace(/[^A-Za-z0-9]/g, '');
    input.value = cleanVal;
    
    if (cleanVal.length < 3) {
        alert("Пожалуйста, введите минимум 3 символа артикула");
        e.preventDefault();
    }
});

//открытие блока соцсетей и контактов
$('.whatsapp-fixed-btn-only-to-open-block').on('click', function () {
   $('#social-media-container').slideDown(300)
      .css({'display': 'flex'});
   $(this).fadeOut(200);
   $('#social-media-container-close').fadeIn(500).css({'right':10, 'bottom': 20});
});
//закрытие блока соцсетей и контактов
$('#social-media-container-close').on('click', function () {
   $('#social-media-container').slideUp(300);
   $('.whatsapp-fixed-btn-only-to-open-block').fadeIn(400);
});

$('.spare-part-info-show').on('click', function () {
   $('#curtain-grey-searchpartres').css({'display': 'block'});
   $(this).next().slideDown(400);
});
$('.block-info-item-close').on('click', function () {
   $(this).parent().parent().slideUp(400);
   $('#curtain-grey-searchpartres').fadeOut(600);
});
//показывать кнопку удаления текста при вводе в инпут поиска запчастей
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

//прокручивать до формы подбора
$('#scroll-to-form').on('click', function () {
   const el = document.getElementById('vin-form');
   el.scrollIntoView({behavior: "smooth", 'block': 'start'}); 
});

//показывать еще товар когда список большой
$('#show-other-items').on('click', function () {
   let counter1 = Number($(this).attr('counter'));
   let step = 10;
   counter1 += step;

   $('#show-other-items a').text(`Показать еще ${($('#requestPartNumberContainer').children().length - $(this).attr('counter')) - 10} из ${$('#requestPartNumberContainer').children().length} (по ${step})`);

   $('#requestPartNumberContainer').children().each(function (key,elem) {
      if (counter1 > $('#requestPartNumberContainer').children().length) {
         $('#show-other-items').css({'display': 'none'});
         return false;
      }
      
      if (counter1 == key) {
         $('#show-other-items').attr('counter', key)
         return false;
      } else {
         $(elem).css({'display': 'grid'});
      }
   });
});

//пагинация
$('.page-link').on('click', function () {
   $('.page-item').removeClass('active');
   $(this).parent().addClass('active');
   const perPage = 50;
   const desirePage = $(this).attr('page-num');
   const start  = (desirePage - 1) * perPage;
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
            $(elem).css({'display': 'grid'});
         } else if (choosedBrand.includes($('.requestPartNumber-brand').text().replace(/\s+/g, ''))) {
            $(elem).css({'display': 'grid'});
         }
      } else {
         if (!choosedBrand.length) {
            $(elem).css({'display': 'none'});
         } else if (choosedBrand.includes($('.requestPartNumber-brand').text().replace(/\s+/g, ''))) {
            $(elem).css({'display': 'none'});
         }
      }
   });
});

$('.form-search-item').on('click', function () {
   $('#shadow').fadeIn(400);
   $('#shadow').css({'display': 'flex'});
});

//добавление товара в корзину
$('.stock-item-cart-btn').on('click', function () {
   const regExp = /\*|%|#|\n|&|\$/g;

   let params = {
       'brand': '',
       'article': '',
       'name': '',
       'price': '',
       'qty': +$(this).next().children().first().val(),
       'deliveryTime': '',
       'stockFrom': '',
       'searchedNumber': '',
       'priceWithMargine': ''
   };
   params.brand = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.article = $(this).parent().parent().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.name = $(this).parent().parent().prev().prev().prev().prev().prev().text().replaceAll(regExp, '');;
   params.priceWithMargine = $(this).parent().parent().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.deliveryTime = $(this).parent().parent().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.stockFrom = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.searchedNumber = $('#search-res-header-val').html();
   params.qty = +$(this).next().children().first().val();
   params.price = $(this).next().next().val();

   $(this).children().first().attr('src', '/images/checkmark-green-20.png');
   $(this).css({'border': '1px solid #4bc828'});

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: params},
      reqData: params,
      url: "/cart/add",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $('#header-cart-qty').html(data.count + 'шт');
         $('.header-cart-sum').html(data.total + 'T');
         
         if(data.duplicates) {
            $("#search-part-main-container").prepend(`
               <div class="alert alert-warning alert-cart" style="align-text:center;">
                  <div style="display:flex;justify-content:flex-end;" class="close-flash">
                        &times;
                  </div>
                  Данная позиция уже добавлена, для изменения количества перейдите в корзину...
               </div>   
            `);
            
            setTimeout(() => {
               $('.alert-cart').slideUp(400);
            }, 3000);

            $('.close-flash').on('click', function () {
               $(this).parent().slideUp(400);
            })
         }
      },
      error: function (error) {
         console.log(error);

      }
   });
});

//удаление товара из корзины
$(document).on('click', '.cart-item-delete', function (e) {
    e.preventDefault();
    let btn = $(this);
    let article = btn.data('article'); // Берем артикул прямо из атрибута кнопки
    
    // Эффект нажатия на иконку внутри
    btn.find('i').css({'transform': 'scale(0.7)', 'transition': '0.2s'});

    $.ajax({
        url: "/cart/delete",
        type: "POST",
        dataType: 'json',
        data: {
            '_token': $('meta[name="csrf-token"]').attr('content'),
            'data': { 'article': article } // Передаем структуру, которую ждет контроллер
        },
        success: function (data) {
            // 1. Удаляем строку из таблицы или карточку на мобилке
            btn.closest('.cart-item-row').fadeOut(300, function() { 
                $(this).remove(); 
                
                // 2. Если товаров не осталось — перезагружаем, чтобы показать "Корзина пуста"
                if (data.count == 0) {
                    location.reload();
                }
            });

            // 3. Обновляем все цифры на странице
            let formattedTotal = new Intl.NumberFormat('ru-RU').format(data.total) + ' ₸';
            
            $('#header-cart-qty').text(data.count + " шт");
            $('.header-cart-sum').text(formattedTotal);
            $('.cart-total-display').text(formattedTotal); // Тот самый класс, что мы вводили
            $('#cart-total-checkout').text(formattedTotal);
        },
        error: function (error) {
            console.log("Ошибка удаления:", error);
        }
    });
});
//изменение кол-ва в корзине
$(document).on('change', '.cart-qty-change', function() {
    let input = $(this);
    let qty = parseInt(input.val()) || 1;
    let price = parseInt(input.data('price')) || 0;
    let article = input.data('article');

    if (qty < 1) { qty = 1; input.val(1); }

    // 1. Находим ближайшего родителя (хоть TR, хоть DIV)
    let parent = input.closest('.cart-item-row');
    
    // 2. Считаем сумму этой позиции
    let itemSubtotal = qty * price;
    
    // 3. Обновляем текст суммы строки (красивый формат)
    parent.find('.item-subtotal-display').text(new Intl.NumberFormat('ru-RU').format(itemSubtotal) + ' ₸');

    // 4. Пересчитываем ОБЩИЙ ИТОГ всей корзины
    let totalCartSum = 0;
    
    // Важно: берем только из видимых блоков (чтобы не дублировать десктоп + мобилку)
    let visibleRows = $('.d-none.d-md-block').is(':visible') ? $('.d-md-block .item-subtotal-display') : $('.d-md-none .item-subtotal-display');
    
    visibleRows.each(function() {
        let val = $(this).text().replace(/[^0-9]/g, '');
        totalCartSum += parseInt(val) || 0;
    });

    // 5. Выводим итоговую сумму во все блоки с этим классом
    let finalFormatted = new Intl.NumberFormat('ru-RU').format(totalCartSum) + ' ₸';
    
    // Обновляем везде: и в модалке, и в боковой панели, и в мобильном блоке
    $('.cart-total-display').html(finalFormatted);
    $('#cart-total-checkout').html(finalFormatted);
    $('.header-cart-sum').html(finalFormatted);

    // 6. Отправляем на сервер, чтобы сессия обновилась
    $.ajax({
        url: '/cart/update',
        method: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            article: article,
            qty: qty
        },
        success: function(response) {
            console.log('Корзина обновлена в сессии');
        }
    });
});

//изменение цены товара в корзине
$('.newPriceWithMargine').on('input', function () {
   let data = {
      'article': $(this).parent().prev().prev().prev().text(),
      'priceWithMargine': $(this).val()
   };
   
   $(this).parent().next().next().text($(this).val() * $(this).parent().next().children().first().val());

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/cart/updatePrice",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $('#header-cart-qty').text(new Intl.NumberFormat('ru-RU').format(data.count) + ' шт');
         $('#header-cart-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
         $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 2}).format(data.total,) + ' T');
         console.log(data);
      },
      error: function (error) {
         console.log(error);
      }
   });
});

//открытие формы подтверждения заказа
$('#modal-show').on('click', function () {
   $('#order-confirmation-form').fadeIn(400);
   $('#cart-shadow').fadeIn(300);
});

$('.modal-close').on('click', function () {
   $('#order-confirmation-form').fadeOut(300);
   $('#cart-shadow').fadeOut(400);
});

//подтверждение отправки формы
$('#order-confirm').on('click', function () {
   $('#order-btn-submit').click();
   $(this).attr('disabled', true);
});
$('#order-cancel').on('click', function () {
   $('#order-confirmation-form').fadeOut(300);
   $('#cart-shadow').fadeOut(400);
});

//загрузка данных заказа
$('.settlement-item-id').on('click', function (e) {
   let data = {
      'order_id': $(this).children().first().val()
   };

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
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
               
               $(table).append(
                  `
                  <tr>
                     <td>${item.article}</td>
                     <td>${item.brand}</td>
                     <td>${item.name}</td>
                     <td class="${item.status}">${statuses[item.status]}</td>
                     <td>${item.qty}шт</td>
                     <td>${item.priceWithMargine * item.qty}</td>
                     <td>${item.fromStock}</td>
                  </tr>
                  `
               );
            });

            $(changedBackground).css({'border': '1px solid #aaa'});
            $(changedBorder).css({'background-color': '#ebe8e2'});
         } else {
            $(table).empty();
            $(changedBackground).css({'border': 'none'});
            $(changedBorder).css({'background-color': 'transparent'});
         }
      },
      error: function (error) {
         console.log(error);
      }
   });
});

//смена статуса продукта
$('.change_status_submit').on('click', function () {
   let productId = $(this).prev().val();
   let newStatus = $(this).parent().prev().children().first().val();

   if (!newStatus) {
      alert('Игорь, смени статус бля!');
      return;
   }

   let data = {
      'product_id': productId,
      'new_status': newStatus
   }

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/product/change_status",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $("#admin-main-container").append(`
            <div class="alert alert-success" style="align-text:center;position: absolute;top:0, left:0;width:100%">
               <div style="display:flex;justify-content:flex-end;" class="close-flash">
                     &times;
               </div>
               ${data.message}
            </div>   
         `);

         $('.close-flash').on('click', function (params) {
            $(this).parent().slideUp();
         });

         if (data.status == 'returned') {
            $(this).prev().children().first().css({'pointer-events': 'none'});
         }
      },
      error: function (data) {
         $("#admin-main-container").append(`
            <div class="alert alert-success" style="align-text:center;position: absolute;top:0, left:0;width:100%">
               <div style="display:flex;justify-content:flex-end;" class="close-flash">
                     &times;
               </div>
               ${data.message}
            </div>   
         `);

         $('.close-flash').on('click', function (params) {
            $(this).parent().slideUp();
         });

         if (data.status == 'returned') {
            $(this).prev().children().first().css({'pointer-events': 'none'});
         }
      }
   });
});

$('.menu-item-name').on('mouseenter', function () {
   $(this).css({'scale': '1.1'});
   $(this).css({'cursor': 'pointer'});
});
$('.menu-item-name').on('mouseleave', function () {
   $(this).css({'scale': '1'});
});

//скрытие/появление картинок гур/рейка
setInterval(function () {
   $('#steering-reika').toggle('slow');
   $('#steering-gur').toggle('slow');
}, 5000)
//смена призыва к действию возле кнопки ватсап

//показ wa-qr
$('.whatsapp-fixed-btn, .wa-top-container').on('click', function () {
   if ($(window).width() > '580') {
      $('#shadow-main').fadeIn();
      $('#shadow-main').css({'background-color': 'rgba(0, 0, 0, .8)'});
   }
});
$('#footer-wa').on('click', function () {
   $('#shadow-main').fadeIn();
   $('#shadow-main').css({'background-color': 'rgba(0, 0, 0, .8)'});
});
$('#footer-phone').on('click', function () {
   $('#shadow-main').fadeIn();
   $('#shadow-main').css({'background-color': 'rgba(0, 0, 0, .8)'});
});
$('#model-qr').on('click', function () {
   $('#shadow-main').fadeOut();
});
$('#shadow-main').on('click', function () {
   $('#shadow-main').fadeOut();
});

//фильтр по брендам
$('.brand-filter').on('change', function () {
   let choosedBrand = [];
   $('.brand-filter').each(function (key, elem) {
      if (elem.checked) {
         choosedBrand.push($(elem).val());
      }
   });
   
   $('.requestPartNumber-brand').each(function (key, elem) {
      if (!choosedBrand.includes($(elem).text().replace(/\s+/g, ''))) {
         $(elem).parent().css({'display': 'none'});
      } else {
         $(elem).parent().css({'display': 'grid'});
      }

      if (!choosedBrand.length) {
         $(elem).parent().css({'display': 'grid'});
      }
   });
});

//перемещение фильтра за прокруткой страницы
$(window).on('scroll', function (params) {
   let searchFilter = $('#search-res-filter');
   let elemOffsetTop = 0; 

   if (searchFilter.length > 0) {
      elemOffsetTop = searchFilter.offset().top;
   } 
   
   let windowYoffset = $(this).scrollTop();
   
   if (elemOffsetTop > windowYoffset) {
      $('#search-res-filter').css({'position': 'sticky', 'top': '110px'});
   } else {
      $('#search-res-filter').removeClass('sticky');
   }
});

//закрыть алерт
$('.close-flash').on('click', function (params) {
   $(this).parent().slideUp();
});

//закрыть каспи рекламу
$('#close-kaspi-ads').on('click', function () {
   $(this).parent().slideUp(400, function () {
      $('#main-header').css({'border-bottom': '3px solid #ccc'})
   });
})

//открыть side-bar на телефоне
$('#three-dots-wrapper').on('click', function () {
   $('#main-mini-shadow').fadeIn();
   $('#side-bar-right-mini').slideDown(500);
});

//закрыть side-bar на телефоне
$('#side-bar-right-mini-close-container').on('click', function () {
   $('#main-mini-shadow').fadeOut(400);
   $('#side-bar-right-mini').slideUp(500);
});

//скрыть форму отправки запроса по вин
$('#feedback-form-close-container').on('click', function () {
   if ($(this).attr('status') == 'open') {
      $(this).next().slideUp('400', function () {
         $('#feedback-form-close-container').children().first().next().attr('src', '/images/plus-24.png');
      });
      $(this).attr('status', 'close');
   } else {
      $(this).next().slideDown('400', function () {
         $('#feedback-form-close-container').children().first().next().attr('src', '/images/minus-24.png')
      });
      $(this).attr('status', 'open');
   }
});

//скрыть/показать артикула в результатах поиска
$('#articles-hide').on('change', function () {
   if($(this).prop('checked')) {
      $('.requestPartNumber-partnumber').css({'visibility': 'hidden'});
   } else {
      $('.requestPartNumber-partnumber').css({'visibility': 'visible'});
   }
});

//увеличение отзыва при наведении
$('.review-item').on('mouseenter', function () {
   $(this).css({'transform': 'scale(1.1)', 'transition': 'all 0.5s'});
});
$('.review-item').on('mouseleave', function () {
   $(this).css({'transform': 'scale(1)'});
});

//редактирование номера телефона под формат КЗ
const phoneInput = document.getElementById("phone");

if (phoneInput) {
   phoneInput.addEventListener("input", function () {
      let input = phoneInput.value.replace(/\D/g, ""); // убираем всё кроме цифр

      if (input.startsWith("8")) {
         input = "7" + input.slice(1); // 8 → 7
      }

      if (input.length > 11) {
         input = input.slice(0, 11); // максимум 11 цифр
      }

      let formatted = "+7";
      if (input.length > 1) formatted += " (" + input.slice(1, 4);
      if (input.length >= 4) formatted += ") " + input.slice(4, 7);
      if (input.length >= 7) formatted += "-" + input.slice(7, 9);
      if (input.length >= 9) formatted += "-" + input.slice(9, 11);

      phoneInput.value = formatted;

      // убираем ошибку при корректном вводе
      if (input.length === 11) {
         document.getElementById("error").textContent = "";
         phoneInput.style.border = "";
      }
   });
}

//валидация VIN номера
function validatePhone() {
    const raw = phoneInput.value.replace(/\D/g, "");
    const error = document.getElementById("error");

    // номер должен быть введён полностью
    if (raw.length !== 11) {
        error.textContent = "Убедитесь, что номер телефона введён полностью.";
        phoneInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // допустимые коды операторов Казахстана
    const valid = /^7\d{10}$/.test(raw);

    if (!valid) {
      error.textContent = "Проверьте номер телефона.";
      phoneInput.style.border = "1px solid #d32f2f";
      return false;
   }
   
   error.textContent = "";
   phoneInput.style.border = "";
   return true;
}

//проверка винкода
function validateVin() {
    const vinInput = document.getElementById("vin");
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

    // Если VIN пустой — это теперь нормально.
    // Обязательность VIN/фото проверяет validateVinOrPhoto()
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

//валидация списка запчастей в запросе по вин
function validateParts() {
    const partsInput = document.getElementById("parts"); // id поля списка запчастей
    const value = partsInput.value.trim();
    let error = partsInput.nextElementSibling;

    // создаём блок ошибки при необходимости
    if (!error || !error.classList.contains("parts-error")) {
        error = document.createElement("div");
        error.className = "parts-error";
        error.style.fontSize = "12px";
        error.style.fontStyle = "italic";
        error.style.color = "#d32f2f";
        error.style.marginTop = "4px";
        partsInput.after(error);
    }

    // минимум символов
    if (value.length < 10) {
        error.textContent = "Опишите список запчастей подробнее (минимум 10 символов).";
        partsInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // не только цифры
    if (/^\d+$/.test(value)) {
        error.textContent = "Добавьте описание к списку запчастей.";
        partsInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // всё ок
    error.textContent = "";
    partsInput.style.border = "";
    return true;
}

//выводим окно ожидания после отправки запроса по винкоду на почту
function showWaitongWindow() {
   $('#shadow').show();
   $('#shadow').addClass('d-flex');
   $('#loading').text('Ваш запрос отправляется, пожалуйста ожидайте...');
   $(this).removeClass('btn-success').addClass('btn-secondary');

   return true;
}

document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('vin-request-form');
      
      // Запускаем всё только если форма существует на странице
      if (form) {
          const fileInput = document.getElementById('tech_passport');
          const previewList = document.getElementById('photo-preview-list');
          const fileNameBlock = document.getElementById('selected-file-name');
          const vinInput = document.getElementById('vin');
          const vinPhotoError = document.getElementById('vin-photo-error');
          const submitBtn = document.getElementById('send-vin-search-btn');

          // Проверяем наличие всех внутренних элементов
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

          function clearVinPhotoError() { vinPhotoError.textContent = ''; }
          function showVinPhotoError(message) { vinPhotoError.textContent = message; }

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
                   if (isPdf) { reader.onload({ target: { result: null } }); } else { reader.readAsDataURL(file); }
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
                         if (!blob) { resolve(file); return; }
                         const compressedFile = new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', {
                               type: 'image/jpeg',
                               lastModified: Date.now()
                         });
                         resolve(compressedFile);
                      }, 'image/jpeg', quality);
                   };
                   img.onerror = () => resolve(file);
                   reader.readAsDataURL(file);
             });
          }

          fileInput.addEventListener('change', function () { handleFiles(this); });
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
                   (window.validatePhone && !validatePhone())) return;
             submitBtn.disabled = true;
             submitBtn.innerHTML = 'Обработка... <span class="spinner-border spinner-border-sm"></span>';
             try {
                   if (selectedFiles.length > 0) {
                      showFileMessage('Сжимаем изображения...');
                      const processed = [];
                      for (const file of selectedFiles) { processed.push(await compressImage(file)); }
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
      } else {
          // Если формы нет, просто пишем в консоль и НЕ прерываем скрипт
          //console.log('VIN request form not found on this page, skipping.');
      }
});


function addToCartFromApi(button, itemData) {
    // 1. Визуальный отклик
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    // 2. Отправка данных
    $.ajax({
        url: "/cart/add",
        type: "POST",
        data: {
            '_token': $('meta[name="csrf-token"]').attr('content'),
            'data': itemData // Передаем готовый объект
        },
        success: function (data) {
            // Меняем кнопку на зеленую галочку
            button.innerHTML = '<i class="fas fa-check text-white"></i>';
            button.classList.replace('btn-primary', 'btn-success');
            
            // Обновляем шапку (твои стандартные ID)
            $('#header-cart-qty').html(data.count + 'шт');
            $('.header-cart-sum').html(data.total + 'T');
        },
        error: function (err) {
            console.error('Ошибка корзины:', err);
            button.innerHTML = 'Ошибка';
            button.disabled = false;
        }
    });
}

$(document).on('click', '.api-buy-btn', function() {
    const btn = $(this);
    
    // Получаем данные напрямую через .attr или .data()
    // Важно: если в HTML написано data-qty, в .data() это будет qty
    const brand = btn.attr('data-brand');
    const article = btn.attr('data-article');
    const priceMargine = btn.attr('data-price-margine');
    const stockQty = parseInt(btn.attr('data-qty')) || 0; 
    const name = btn.attr('data-name');
    const supplier = btn.attr('data-supplier');
    const delivery = btn.attr('data-delivery');
    const priceBase = btn.attr('data-price');

    // Отладка: нажми F12 в браузере и посмотри, что выведет при клике
    console.log('Пытаемся добавить:', {brand, article, stockQty});

    // 1. Проверка количества
    if (stockQty <= 0) {
        alert('К сожалению, этого товара нет в наличии на выбранном складе.');
        return;
    }

    // Блокируем кнопку
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
            // Обновляем кругляшок корзины в хедере
            if ($('#header-cart-qty').length) {
                $('#header-cart-qty').text(json.cart_count).show();
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

$(document).on('change', '.copy_text', function() {
   // Считаем, сколько чекбоксов с классом .copy_text сейчас отмечено
   let checkedCount = $('.copy_text:checked').length;

   if (checkedCount > 0) {
      // Если есть хотя бы один — показываем обертку с кнопкой
      $('#copy_text_wrapper').show(); 
      console.log("Выбрано позиций: " + checkedCount);
   } else {
      // Если 0 — скрываем
      $('#copy_text_wrapper').fadeOut();
      console.log("Ничего не выбрано");
   }
});

$(document).on('click', '#copy_text_btn', function() {
    let selectedText = "";
    
    // 1. Ищем все отмеченные чекбоксы
    let checkedBoxes = $('.copy_text:checked');

    if (checkedBoxes.length === 0) {
        alert("Сначала выберите хотя бы одну запчасть!");
        return;
    }

    // 2. Проходимся по каждой строке, где стоит галочка
    checkedBoxes.each(function(index) {
        // Находим родительский контейнер всей строки
        let row = $(this).closest('.requestPartNumberContainer-item');
        
        // Вытаскиваем данные (используем твои классы)
        let brand = row.find('.requestPartNumber-brand').text().trim();
        let price = row.find('.requestPartNumber-price').text().trim();
        let delivery = row.find('.requestPartNumber-delivery').text().trim();

        // Если это первый чекбокс в списке (index === 0), берем название
        if (index === 0) {
            mainName = row.find('.requestPartNumber-name').text().trim();
            selectedText += `⚙️ *${mainName}*\n\n`; // Заголовок жирным шрифтом
        }

        selectedText += `✔ ${brand} — Цена: ${price} ₸\n`;
        //selectedText += ` | 📦 Срок: ${delivery}\n\n`;
    });

    // Добавим финальную подпись
    selectedText += "\nGlobal Parts Astana — Запчасти в наличии и на заказ.";

    // 3. Копируем в буфер обмена
    let buffer = $('#clipboard-buffer');
    buffer.val(selectedText).select();
    
    try {
        document.execCommand('copy');
        
        // Визуальное подтверждение для менеджера
        let originalText = $(this).html();
        $(this).removeClass('btn-primary').addClass('btn-success').html('✅ Скопировано!');
        
        setTimeout(() => {
            $(this).removeClass('btn-success').addClass('btn-primary').html(originalText);
        }, 2000);
        
    } catch (err) {
        alert('Ошибка при копировании. Попробуйте вручную.');
    }
});

$(document).ready(function(){
    $('.recommended-slider').slick({
        dots: false,
        infinite: true,
        speed: 500,
        slidesToShow: 5,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 3000,
        arrows: true,
        prevArrow: '<button type="button" class="slick-prev shadow-sm">←</button>',
        nextArrow: '<button type="button" class="slick-next shadow-sm">→</button>',
        responsive: [
            {
                breakpoint: 1024,
                settings: { slidesToShow: 3 }
            },
            {
                breakpoint: 600,
                settings: { slidesToShow: 2 }
            }
        ]
    });
});