$('.menu-item-container').on('click', function () {
    let id = $(this).attr('target');
   
    $('#content').children().each(function () {
        if($(this).attr('id') != id) {
            $(this).css({'display': 'none'});
        }
    });

    $(`#${$(this).attr('target')}`).css({'display': 'block'});
});