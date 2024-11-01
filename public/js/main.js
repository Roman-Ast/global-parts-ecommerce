
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
       'stockFrom': ''
   };
   params.brand = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');
   params.article = $(this).parent().parent().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.name = $(this).parent().parent().prev().prev().prev().prev().prev().text().replaceAll(regExp, '');;
   params.price = $(this).parent().parent().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.deliveryTime = $(this).parent().parent().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;
   params.stockFrom = $(this).parent().parent().prev().prev().prev().prev().prev().prev().prev().prev().text().replaceAll(' ', '').replaceAll(regExp, '');;

   console.log(params);


   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: params},
      reqData: params,
      url: "/cart/add",
      type: "POST",
      dataType: 'json',
      success: function (res) {
         console.log(res);
      },
      error: function (error) {
         console.log(error);
      }
   });
});

//удаление товара из корзины
$('.cart-item-delete').on('click', function () {
   let data = {
      'article': $(this).prev().prev().prev().prev().prev().prev().text()
   };

   $.ajax({
      data: {'_token': $('meta[name="csrf-token"]').attr('content'), data: data},
      reqData: data,
      url: "/cart/delete",
      type: "POST",
      dataType: 'json',
      success: function (res) {
         console.log(res);
      },
      error: function (error) {
         console.log(error);
      }
   });
});
