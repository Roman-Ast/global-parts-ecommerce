
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

      const pageCount = Math.floor($('#crossesContainer-to-order').children().length / perPage);
      
      $('.pagination-nav').css({'display': 'flex'});

      $('.pagination-nav ul').children().each(function (key, elem) {
         if (Number($(elem).children().first().attr('page-num')) > pageCount) {
            $(elem).css({'display': 'none'});
         }
      });
   }
   //проверка сатуса returned
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
      $('#whatsapp-container').attr('href', 'https://wa.me/+77087172549')
   }
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
   params.originNumber = $('#originNumber').val();
   params.qty = +$(this).next().children().first().val();
   params.price = $(this).next().next().val();
   
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

//изменение цвета корзины после добавления товара
$('.stock-item-cart-btn').on('click', function () {
   $(this).children().first().attr('src', '/images/checkmark-green-20.png');
   $(this).css({'border': '1px solid #4bc828'});
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
         console.log(data);
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

//показ wa-qr
$('#whatsapp-container img').on('click', function () {
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



