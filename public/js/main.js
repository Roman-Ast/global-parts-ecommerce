
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
       'searchedNumber': ''
   };
   params.brand = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.article = $(this).parent().parent().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.name = $(this).parent().parent().prev().prev().prev().prev().prev().text().replaceAll(regExp, '');;
   params.price = $(this).parent().parent().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.deliveryTime = $(this).parent().parent().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.stockFrom = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.originNumber = $('#originNumber').val();
   params.qty = +$(this).next().children().first().val();

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: params},
      reqData: params,
      url: "/cart/add",
      type: "POST",
      dataType: 'json',
      success: function (data) { 
         console.log(data);

         $('#header-cart-qty').text('кол-во: ' + new Intl.NumberFormat('ru-RU').format(data.count));
         $('#header-cart-sum').text('сумма: ' + new Intl.NumberFormat('ru-RU').format(data.total) + ' T');

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
$('.cart-item-delete').on('click', function () {
   $(this).css({'transform': 'scale(0.7)'});

   let data = {
      'article': $(this).prev().prev().prev().prev().prev().prev().text()
   };
   $(this).parent().remove();

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/cart/delete",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         console.log(data);
         $('#header-cart-qty').text('кол-во: ' + new Intl.NumberFormat('ru-RU').format(data.count));
         $('#header-cart-sum').text('сумма: ' + new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
         $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
      },
      error: function (error) {
         console.log(error);
      }
   });
});

$('.cart-qty-change').on('input', function () {
   let data = {
      'article': $(this).parent().prev().prev().prev().prev().text(),
      'qty': $(this).val()
   };
   
   $(this).parent().next().text($(this).val() * $(this).parent().prev().text());

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      url: "/cart/update",
      type: "POST",
      dataType: 'json',
      success: function (data) {
         console.log(data);
         $('#header-cart-qty').text('кол-во: ' + new Intl.NumberFormat('ru-RU').format(data.count));
         $('#header-cart-sum').text('сумма: ' + new Intl.NumberFormat('ru-RU').format(data.total) + ' T');
         $('#cart-header-sum').text(new Intl.NumberFormat('ru-RU', {maximumFractionDigits: 2}).format(data.total,) + ' T');
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


