<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Тест прогрессивного поиска</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 16px; }
        form { display: flex; gap: 8px; margin-bottom: 16px; }
        input { padding: 8px; font-size: 14px; }
        input[name="brand"] { width: 160px; }
        input[name="partnumber"] { width: 220px; }
        button { padding: 8px 16px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; font-size: 14px; }
        th { background: #f5f5f5; }
        tr.rossko { background: #eaffea; }
        .status { font-size: 13px; color: #666; margin-top: 8px; }
        .status span { margin-right: 16px; }
        .pending { color: #b8860b; }
        .done { color: #2e7d32; }
    </style>
</head>
<body>
    <div class="d-flex justify-content-between align-items-start">
        <h1>Тест: прогрессивный поиск (Rossko первым)</h1>
        <span id="offersBadge" class="badge rounded-pill bg-success" style="font-size: 0.85rem; padding: 8px 14px; display: none;">
            Найдено 0 предложений
        </span>
    </div>
    <p>Rossko рисуется сразу, как только ответит (обычно 1-2 сек). Остальные поставщики
       догружаются отдельным запросом параллельно и домешиваются в таблицу — вся таблица
       пересортировывается по цене от дешёвого к дорогому.</p>

    <form id="searchForm">
        <input type="text" name="brand" placeholder="Бренд, напр. NGK" required>
        <input type="text" name="partnumber" placeholder="Артикул" required>
        <button type="submit">Искать</button>
    </form>

    <div class="status">
        <span id="statusRossko" class="pending">Rossko: ожидание…</span>
        <span id="statusRest" class="pending">Остальные: ожидание…</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Источник</th>
                <th>Бренд</th>
                <th>Артикул</th>
                <th>Название</th>
                <th>Кол-во</th>
                <th>Цена</th>
            </tr>
        </thead>
        <tbody id="offersBody"></tbody>
    </table>

    <script>
        let offers = [];

        function render() {
            offers.sort((a, b) => (a.priceWithMargine || 0) - (b.priceWithMargine || 0));

            const badge = document.getElementById('offersBadge');
            if (offers.length > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = `Найдено ${offers.length} предложени${offers.length === 1 ? 'е' : (offers.length < 5 ? 'я' : 'й')}`;
            } else {
                badge.style.display = 'none';
            }

            const tbody = document.getElementById('offersBody');
            tbody.innerHTML = offers.map(o => `
                <tr class="${o.__source === 'rossko' ? 'rossko' : ''}">
                    <td>${o.__source}</td>
                    <td>${o.brand ?? ''}</td>
                    <td>${o.article ?? ''}</td>
                    <td>${o.name ?? ''}</td>
                    <td>${o.qty ?? ''}</td>
                    <td>${Number(o.priceWithMargine || 0).toLocaleString()} ₸</td>
                </tr>
            `).join('');
        }

        document.getElementById('searchForm').addEventListener('submit', function (e) {
            e.preventDefault();

            offers = [];
            render();

            const brand = this.brand.value;
            const partnumber = this.partnumber.value;

            const statusRossko = document.getElementById('statusRossko');
            const statusRest = document.getElementById('statusRest');
            statusRossko.textContent = 'Rossko: ищем…';
            statusRossko.className = 'pending';
            statusRest.textContent = 'Остальные: ищем…';
            statusRest.className = 'pending';

            const tRossko = performance.now();
            fetch(`/test/search-rossko?brand=${encodeURIComponent(brand)}&partnumber=${encodeURIComponent(partnumber)}`)
                .then(r => r.json())
                .then(json => {
                    const took = ((performance.now() - tRossko) / 1000).toFixed(1);
                    (json.offers || []).forEach(o => offers.push({ ...o, __source: 'rossko' }));
                    statusRossko.textContent = `Rossko: готово за ${took} сек (${(json.offers || []).length})`;
                    statusRossko.className = 'done';
                    render();
                })
                .catch(() => {
                    statusRossko.textContent = 'Rossko: ошибка';
                });

            const tRest = performance.now();
            fetch(`/test/search-rest?brand=${encodeURIComponent(brand)}&partnumber=${encodeURIComponent(partnumber)}`)
                .then(r => r.json())
                .then(json => {
                    const took = ((performance.now() - tRest) / 1000).toFixed(1);
                    (json.offers || []).forEach(o => offers.push({ ...o, __source: o.supplier_name || 'other' }));
                    statusRest.textContent = `Остальные: готово за ${took} сек (${(json.offers || []).length})`;
                    statusRest.className = 'done';
                    render();
                })
                .catch(() => {
                    statusRest.textContent = 'Остальные: ошибка';
                });
        });
    </script>
</body>
</html>
