$('.santafe18-21container-item').on('click', function () {
    $(this).submit();
});
setInterval(() => {
    $('#cant-search-part').fadeIn(600);
}, 3000);
setInterval(() => {
    $('#cant-search-part').fadeOut(600);
}, 6000);