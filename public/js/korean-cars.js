$('.santafe18-21container-item').on('click', function () {
    $(this).submit();
});
setInterval(() => {
    $('#cant-search-part').fadeIn(600);
}, 2000);
setInterval(() => {
    $('#cant-search-part').fadeOut(600);
}, 10000);