$(function (params) {
    
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    $('#search-btn').on('click', function(e) {
        e.preventDefault();
    
        let partNumber = $('#searchBarInput').val();
        
        $.ajax({
            data: { partNumber },
            url: "/getSparePart",
            type: "POST",
            dataType: 'json',
            success: function (response) {
                console.log(response);
                
                response.SearchResult.PartsList.Part.forEach(part => {
                    console.log(part);
                    $('#search-result-container-header').css({'display': 'flex'});

                    $('#search-result-main-container').append(
                        `
                        <div id="pre-search-result">
                            <div class="pre-search-result-item">
                                <div class="pre-search-result-item-header">
                                    ${part.name}
                                </div>
                                <div class="pre-search-result-item-caption">
                                    Бренд
                                </div>
                                <div class="pre-search-result-item-caption">
                                    Артикул/Наименование
                                </div>
                                <div class="pre-search-result-item-caption">
                                    Цена
                                </div>
                                <div class="pre-search-result-item-brand">
                                    ${part.brand}
                                </div>
                                <div class="pre-search-result-item-art">
                                    <div class="pre-search-result-item-art-art">
                                        ${part.partnumber}
                                    </div>
                                    <div class="pre-search-result-item-art-name">
                                        ${part.name}
                                    </div>
                                </div>
                                <div class="pre-search-result-item-price">
                                    <div class="pre-search-result-item-price-price">
                                        ${part.stocks.stock.price ? part.stocks.stock.price : part.stocks.stock[0].price}
                                    </div>
                                    <div class="pre-search-result-item-price-delivery">
                                        ~ ${part.stocks.stock.deliveryStart ? part.stocks.stock.deliveryStart : part.stocks.stock[0].deliveryStart}
                                    </div>
                                    <div class="pre-search-result-item-price-analogs">
                                        ${part.crosses ? '+ ' + part.crosses.Part.length + ' аналогов': ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                        `
                    )
                    
                });
            },
            error: function (error) {
                console.log(error);
            }
        });
    });
});

/*`
                        
                        ` */