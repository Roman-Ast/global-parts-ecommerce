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
$('.cart-item-delete img').on('click', function () {
   $(this).css({'transform': 'scale(0.7)'});

   let data = {
      'article': $(this).parent().prev().prev().prev().prev().prev().prev().text()
   };
   $(this).parent().parent().remove();

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/cart/delete",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $('#header-cart-qty').text(new Intl.NumberFormat('ru-RU').format(data.count) + "шт");
         $('.header-cart-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + 'T');
         $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
      },
      error: function (error) {
         console.log(error);
      }
   });
});
//изменение кол-ва в корзине
$('.cart-qty-change').on('input', function () {
   if ($(this).val() < 1) {
      $(this).css({'border': '1px solid red'});
      return;
   }
   $(this).css({'border': '1px solid #ccc'})
   let data = {
      'article': $(this).parent().prev().prev().prev().prev().text(),
      'qty': $(this).val()
   };
   
   if ($(this).parent().prev().children().first().hasClass('newPriceWithMargine')) {
      $(this).parent().next().text($(this).val() * $(this).parent().prev().children().first().val());
   } else {
      $(this).parent().next().text($(this).val() * $(this).parent().prev().children().first().text());
   }

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/cart/update",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $('#header-cart-qty').text(new Intl.NumberFormat('ru-RU').format(data.count) + ' шт');
         $('#header-cart-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
         $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 2}).format(data.total,) + ' T');
      },
      error: function (error) {
         console.log(error);
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
   let elemOffsetTop = $('#search-res-filter').offset().top;
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
    let error = vinInput.nextElementSibling;

    // создаём блок ошибки, если его ещё нет
    if (!error || !error.classList.contains("vin-error")) {
        error = document.createElement("div");
        error.className = "vin-error";
        error.style.fontSize = "12px";
        error.style.fontStyle = "italic";
        error.style.color = "#d32f2f";
        error.style.marginTop = "4px";
        vinInput.after(error);
    }

    // длина 8-17 символов
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

    // кириллица
    const cyrillicMatches = value.match(/[А-Яа-яЁёЀ-ӿ]/g);
    if (cyrillicMatches) {
        error.textContent = `В VIN / номере кузова нельзя использовать кириллицу! Найдено: ${cyrillicMatches.join(', ')}`;
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // минимум 2 латинские буквы
    const latinLetters = (value.match(/[A-Za-z]/g) || []).length;
    if (latinLetters < 2) {
        error.textContent = "VIN / номер кузова должен содержать минимум 2 латинские буквы.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // минимум 1 цифра
    const digitsCount = (value.match(/\d/g) || []).length;
    if (digitsCount < 1) {
        error.textContent = "VIN / номер кузова должен содержать хотя бы одну цифру.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // запрет I, O, Q для 17-символьного VIN
    if (value.length === 17 && /[IOQ]/i.test(value)) {
        error.textContent = "VIN не должен содержать буквы I, O, Q.";
        vinInput.style.border = "1px solid #d32f2f";
        return false;
    }

    // всё ок
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

//увеличение и пролистывание отзывов
$(document).ready(function() {
      const $modal = $('#review-modal');
      const $modalImg = $('#modal-img');
      const $imgs = $('.review-img');
      let currentIndex = -1;
  
      function openModal(index) {
        currentIndex = index;
        $modalImg.attr('src', $imgs.eq(currentIndex).attr('src'));
        $modal.fadeIn(200);
      }
  
      function closeModal() {
        $modal.fadeOut(200);
      }
  
      function showNext() {
        currentIndex = (currentIndex + 1) % $imgs.length;
        $modalImg.attr('src', $imgs.eq(currentIndex).attr('src'));
      }
  
      function showPrev() {
        currentIndex = (currentIndex - 1 + $imgs.length) % $imgs.length;
        $modalImg.attr('src', $imgs.eq(currentIndex).attr('src'));
      }
  
      $imgs.on('click', function() {
        openModal($imgs.index(this));
      });
  
      $('.modal-close, .modal-overlay').on('click', closeModal);
      $('.modal-nav .next').on('click', showNext);
      $('.modal-nav .prev').on('click', showPrev);
  
      $(document).on('keydown', function(e) {
        if ($modal.is(':visible')) {
          if (e.key === 'ArrowRight') showNext();
          if (e.key === 'ArrowLeft') showPrev();
          if (e.key === 'Escape') closeModal();
        }
      });
  
      // свайпы
      var hammer = new Hammer(document.getElementById('review-modal'));
      hammer.on('swipeleft', showNext);
      hammer.on('swiperight', showPrev);
});


 // переключение форм
const switcher = document.getElementById("searchModeSwitch");
const formNoVin = document.getElementById("form-no-vin");
const formVin = document.getElementById("form-vin");
const switchLabel = document.querySelector("label[for='searchModeSwitch']");

// Инициализация тултипа
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl, {
    trigger: 'manual', // показываем вручную
    delay: { "show": 0, "hide": 2000 } // задержка скрытия 2 сек
  })
})

// получаем сам tooltip по id
const tooltipEl = document.querySelector('[data-bs-toggle="tooltip"]');
const tooltip = bootstrap.Tooltip.getInstance(tooltipEl);

switcher.addEventListener("change", () => {
   if (switcher.checked) {
      formNoVin.classList.add("d-none");
      formVin.classList.remove("d-none");
      tooltip.setContent({ '.tooltip-inner': "Поиск по VIN — точность выше, но не 100%." });
   } else {
      formVin.classList.add("d-none");
      formNoVin.classList.remove("d-none");
      tooltip.setContent({ '.tooltip-inner': "Поиск без VIN — результат менее точный, но попробовать можно." });
   }

   tooltip.show();
   setTimeout(() => tooltip.hide(), 4000); // держим 4 секунды
});

// Подсказка при первой загрузке (по умолчанию без VIN)
document.addEventListener("DOMContentLoaded", () => {
  showTooltip("Без VIN: точность ниже, но попробовать можно 👌");
});

// ИИ поиск без винкода
$('#ai-no-vin-form-btn').on('click', function () {
   const resultsDiv = document.getElementById("ai-search-results");
   $('#ai-no-vin-form-btn').css({'border': '1px solid #bbb'});

   if($('#ai-no-vin-input').val().length < 20) {
      $('#ai-no-vin-input').css({'border': '1px solid red'});
      $(resultsDiv).css({'color': 'red'});
      $(resultsDiv).text('Введите все необходимые данные!');
      return;
   }
   
   $(this).attr('disabled', true);

   resultsDiv.innerHTML = `
      <div class="text-center text-muted">
            <div class="spinner-border text-success" role="status"></div>
          <p class="mt-2">Подбираем запчасти...</p>
      </div>
   `;

   const dataFromInput = $('#ai-no-vin-input').val();
   console.log(dataFromInput);
   
   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: dataFromInput},
      url: "/simpleAISearchWithoutVin",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         
         
         $('#ai-no-vin-form-btn').attr('disabled', false);
         const parsedData = JSON.parse(data);

         $('#ai-search-results').empty();
         $('#ai-no-vin-input').empty();

         const resultsDiv = document.getElementById("ai-search-results");
         let answer = parsedData.choices?.[0]?.message?.content || "Нет ответа от сервера.";
         
         // Заменяем переносы строк на <br> и экранируем HTML-символы
         answer = answer
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\n/g, "<br>");

         resultsDiv.innerHTML = `<div class="card card-body bg-light text-dark">${answer}</div>`;
      },
      error: function (error) {
         $('#ai-no-vin-form-btn').attr('disabled', false);
         $('#ai-search-results').empty();
         $('#ai-no-vin-input').empty();
         const resultsDiv = document.getElementById("ai-search-results");

         resultsDiv.innerHTML = `<div class="card card-body bg-light text-danger">${error}</div>`;
      }
   });
});

// ИИ поиск с винкодом
$('#ai-vin-form-btn').on('click', function () {
   const resultsDiv = document.getElementById("ai-search-results");
   $('#ai-vin-input').css({'border': '1px solid #bbb'});

   if($('#ai-vin-input').val().length < 12) {
      $('#ai-vin-input').css({'border': '1px solid red'});
      $(resultsDiv).css({'color': 'red'});
      $(resultsDiv).text('Введите все необходимые данные!');
      return;
   }
   if($('#ai-vin-part-input').val().length < 5) {
      $('#ai-vin-part-input').css({'border': '1px solid red'});
      $(resultsDiv).css({'color': 'red'});
      $(resultsDiv).text('Введите все необходимые данные!');
      return;
   }
   
   $(this).attr('disabled', true);

   resultsDiv.innerHTML = `
      <div class="text-center text-muted">
            <div class="spinner-border text-success" role="status"></div>
          <p class="mt-2">Подбираем запчасти...</p>
      </div>
   `;

   const VIN = $('#ai-vin-input').val();
   const part = $('#ai-vin-part-input').val();

   const dataFromInput = {
      VIN: VIN,
      part: part
   };
   
   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: dataFromInput},
      url: "/simpleAIVinSearch",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         $('#ai-vin-part-input').css({'border': '1px solid #bbb'});
         $('#ai-vin-input').css({'border': '1px solid #bbb'});

         $('#ai-vin-form-btn').attr('disabled', false);

         // очищаем инпуты
         $('#ai-vin-input').val('');
         $('#ai-vin-part-input').val('');

         const resultsDiv = document.getElementById("ai-search-results");
         let answer = data.answer || "Нет ответа от сервера.";

         // экранируем HTML
         answer = answer
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");

         // Markdown → HTML
        // Заголовки ###
         answer = answer.replace(/^### (.*$)/gim, "<h5 class='mt-3 mb-2 fw-bold text-start'>$1</h5>");
         // Заголовки ##
         answer = answer.replace(/^## (.*$)/gim, "<h4 class='mt-3 mb-2 fw-bold text-start'>$1</h4>");
         // Заголовки #
         answer = answer.replace(/^# (.*$)/gim, "<h3 class='mt-3 mb-2 fw-bold text-start'>$1</h3>");
         // Жирный текст **...**
         answer = answer.replace(/\*\*(.*?)\*\*/gim, "<strong>$1</strong>");
         // Списки
         answer = answer.replace(/^\d+\. (.*$)/gim, "<li>$1</li>");
         answer = answer.replace(/^- (.*$)/gim, "<li>$1</li>");
         // Переносы строк
         answer = answer.replace(/\n/g, "<br>");

         // оборачиваем списки <li> в <ul>
         answer = answer.replace(/(<li>.*<\/li>)/gims, "<ul class='ms-3 mb-2'>$1</ul>");

         resultsDiv.innerHTML = `
            <div class="card card-body bg-light text-dark text-start">
               ${answer}
            </div>
         `;
      },
      error: function (error) {
         $('#ai-vin-form-btn').attr('disabled', false);
         $('#ai-search-results').empty();
         $('#ai-vin-input').empty();
         $('#ai-vin-part-input').empty();
         const resultsDiv = document.getElementById("ai-search-results");

         resultsDiv.innerHTML = `<div class="card card-body bg-light text-danger">${error}</div>`;
      }
   });
});
//открытие блока с информацией и картинками товара






