   document.getElementById('start-api-search').addEventListener('click', function() {
    const btn = this;
    const placeholder = document.getElementById('api-offers-placeholder');
    const loader = document.getElementById('api-offers-loader');
    const content = document.getElementById('api-offers-content');
    const tbody = document.getElementById('api-offers-tbody');

    // 1. Состояние "Загрузка"
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Ищем...';
    
    placeholder.style.display = 'none';
    content.style.display = 'none';
    loader.style.display = 'block';

    // article и brand у тебя уже должны быть объявлены выше в скрипте
    fetch(`/api/search-prices?article=${encodeURIComponent(article)}&brand=${encodeURIComponent(brand)}`)
    .then(response => {
        if (!response.ok) throw new Error('Ошибка сервера (500)');
        return response.json();
    })
    .then(json => {
        loader.style.display = 'none';
        content.style.display = 'block';
        
        let html = '';
        const allOffers = [...(json.on_stock || []), ...(json.to_order || [])];

        if (allOffers.length > 0) {
            allOffers.forEach(offer => {
                const qty = parseInt(offer.qty) || 0;
                const price = Number(offer.priceWithMargine || 0).toLocaleString();
                const delivery = offer.delivery_time || offer.delivery_date || '1-2 дня';

                html += `
                <tr>
                    <td class="ps-4 py-3">
                        <div class="fw-bold text-primary">${offer.brand}</div>
                        <div class="small text-muted">${offer.article}</div>
                        <div class="extra-small text-muted" style="font-size: 0.7rem;">${offer.name || ''}</div>
                    </td>
                    <td class="text-center align-middle fw-medium">${delivery}</td>
                    <td class="text-center align-middle">
                        <span class="badge ${qty > 0 ? 'bg-success' : 'bg-secondary'}">${qty} шт.</span>
                    </td>
                    <td class="align-middle fw-bold h5 text-nowrap">${price} ₸</td>
                    <td class="pe-4 text-end align-middle">
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3">В корзину</button>
                    </td>
                </tr>`;
            });
            tbody.innerHTML = html;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Обновлено';
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">Ни один поставщик не ответил. Попробуйте позже.</td></tr>';
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Повторить';
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        loader.style.display = 'none';
        placeholder.style.display = 'block';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Ошибка. Повторить?';
        alert('Сервер не успел ответить (Timeout). Нажмите кнопку еще раз.');
    });
});