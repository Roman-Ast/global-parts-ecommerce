<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анализ спроса — Global Parts</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-6 bg-slate-100 min-h-screen">
    <nav class="mb-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">
        <a href="/admin" class="hover:text-slate-700 transition-colors">Главная</a>
        <span class="mx-1.5 text-slate-300">/</span>
        <span class="text-slate-600">Анализ спроса</span>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Анализ спроса (demand_signals)</h1>
        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
            Всего записей: {{ $totalSignals }} — наполняется еженедельно вручную (whatsapp:import-analyzed-demand)
        </p>
    </div>

    {{-- Воронка КП — считается от lead_requests + реального статуса CRM, не от LLM-анализа --}}
    <div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">
        Воронка КП (по реальному статусу CRM, не по выводу ЛЛМ)
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="rounded-xl border p-4 bg-white border-slate-200">
            <div class="text-3xl font-black text-slate-700">{{ $quoteFunnel['totalRequests'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Заявок обрабатываем всего</div>
        </div>
        <div class="rounded-xl border p-4 bg-indigo-50 border-indigo-200">
            <div class="text-3xl font-black text-indigo-700">{{ $quoteFunnel['kpSent'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-indigo-600">КП отправлено (и дальше по воронке)</div>
        </div>
        <div class="rounded-xl border p-4 bg-emerald-50 border-emerald-200">
            <div class="text-3xl font-black text-emerald-700">{{ $quoteFunnel['bought'] }}</div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Купили</div>
        </div>
        <div class="rounded-xl border p-4 bg-amber-50 border-amber-200">
            <div class="text-3xl font-black text-amber-700">{{ $quoteFunnel['conversionPct'] !== null ? $quoteFunnel['conversionPct'] . '%' : '—' }}</div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-amber-600">% закрытия (купили / КП отправлено)</div>
        </div>
    </div>
    <p class="text-[10px] text-slate-400 mb-8 -mt-6">
        "КП отправлено" — это статус "КП Отправлено" и все статусы ДАЛЬШЕ по воронке (работа с возражениями, оплата,
        купил/ждёт, продано), плюс отказы с причиной "дорого"/"передумал" (КП физически уже должно было уйти клиенту
        на этих стадиях). Статусы ДО отправки КП ("не нашли деталь", "не отвечает", "нет в наличии") в счёт не идут.
        Если граница не так, как считаешь правильным — скажи, поправим классификацию статусов.
    </p>

    {{-- Сводка по исходам СОГЛАСНО ЛЛМ --}}
    <div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">Исход согласно ЛЛМ</div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach($byOutcome as $row)
            @php
                $labels = ['bought' => 'Купили', 'declined' => 'Отказались', 'silent' => 'Замолчали', 'pending' => 'Ещё не ясно'];
                $colors = ['bought' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'declined' => 'bg-rose-50 text-rose-700 border-rose-200', 'silent' => 'bg-slate-50 text-slate-600 border-slate-200', 'pending' => 'bg-amber-50 text-amber-700 border-amber-200'];
            @endphp
            <div class="rounded-xl border p-4 {{ $colors[$row->outcome] ?? 'bg-white' }}">
                <div class="text-3xl font-black">{{ $row->cnt }}</div>
                <div class="text-[10px] font-bold uppercase tracking-widest">{{ $labels[$row->outcome] ?? $row->outcome }}</div>
            </div>
        @endforeach
    </div>

    {{-- Сводка по исходам СОГЛАСНО МЕНЕДЖЕРУ (реальный статус CRM на момент анализа, не вывод ЛЛМ) --}}
    <div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">Исход согласно менеджеру (реальный статус CRM)</div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @forelse($byManagerStatus as $row)
            <div class="rounded-xl border p-4 bg-indigo-50 text-indigo-700 border-indigo-200">
                <div class="text-3xl font-black">{{ $row->cnt }}</div>
                <div class="text-[10px] font-bold uppercase tracking-widest">{{ $row->manager_status_label }}</div>
            </div>
        @empty
            <div class="text-xs text-slate-400 col-span-full">Нет данных — manager_status_label не заполнен ни у одной записи.</div>
        @endforelse
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- ABC-анализ по названию детали --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <h2 class="font-black text-slate-800 uppercase text-sm">Что чаще всего спрашивают (ABC)</h2>
                <p class="text-[10px] text-slate-500">A — верх до 80% запросов, B — до 95%, C — остальное</p>
            </div>
            <table class="w-full text-sm">
                <thead class="text-[10px] uppercase text-slate-400 font-bold">
                    <tr>
                        <th class="text-left px-5 py-2">Деталь</th>
                        <th class="text-right px-3 py-2">Кол-во</th>
                        <th class="text-right px-3 py-2">%</th>
                        <th class="text-right px-5 py-2">Тир</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($byPart as $row)
                        <tr>
                            <td class="px-5 py-2">{{ $row->part_name }}</td>
                            <td class="text-right px-3 py-2 font-bold">{{ $row->cnt }}</td>
                            <td class="text-right px-3 py-2 text-slate-400">{{ $row->pct }}%</td>
                            <td class="text-right px-5 py-2">
                                <span class="inline-block w-5 h-5 rounded text-[10px] font-black text-white text-center leading-5
                                    {{ $row->tier === 'A' ? 'bg-emerald-600' : ($row->tier === 'B' ? 'bg-amber-500' : 'bg-slate-400') }}">
                                    {{ $row->tier }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- По марке авто --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <h2 class="font-black text-slate-800 uppercase text-sm">По марке авто</h2>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach($byBrand as $row)
                        <tr>
                            <td class="px-5 py-2">{{ $row->brand }}</td>
                            <td class="text-right px-5 py-2 font-bold">{{ $row->cnt }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- По модели авто --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <h2 class="font-black text-slate-800 uppercase text-sm">По модели авто</h2>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach($byCarModel as $row)
                        <tr>
                            <td class="px-5 py-2">{{ $row->brand }} {{ $row->car_model }}</td>
                            <td class="text-right px-5 py-2 font-bold">{{ $row->cnt }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Причины отказа --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
                <h2 class="font-black text-slate-800 uppercase text-sm">Причины отказа</h2>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @php
                        $reasonLabels = [
                            'no_stock_wont_wait' => 'Нет в наличии, ждать не готов',
                            'in_stock_too_expensive' => 'Есть в наличии, но дорого',
                            'part_not_found' => 'Не нашли такую деталь',
                            'changed_mind' => 'Передумал',
                        ];
                    @endphp
                    @foreach($byDeclineReason as $row)
                        <tr>
                            <td class="px-5 py-2">{{ $reasonLabels[$row->decline_reason] ?? $row->decline_reason }}</td>
                            <td class="text-right px-5 py-2 font-bold">{{ $row->cnt }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Горящий сигнал для склада --}}
    <div class="bg-white rounded-xl border border-rose-200 overflow-hidden mb-8">
        <div class="px-5 py-3 bg-rose-50 border-b border-rose-200">
            <h2 class="font-black text-rose-800 uppercase text-sm">Упущенный спрос — склад/цена</h2>
            <p class="text-[10px] text-rose-600">Спросили, но либо не было в наличии (и ждать не готовы), либо дорого — прямой сигнал на закупку/пересмотр наценки</p>
        </div>
        <table class="w-full text-sm">
            <thead class="text-[10px] uppercase text-slate-400 font-bold">
                <tr>
                    <th class="text-left px-5 py-2">Деталь</th>
                    <th class="text-left px-3 py-2">Причина</th>
                    <th class="text-right px-5 py-2">Раз</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($lostToStockOrPrice as $row)
                    <tr>
                        <td class="px-5 py-2">{{ $row->part_name }}</td>
                        <td class="px-3 py-2 text-slate-500">{{ $row->decline_reason === 'no_stock_wont_wait' ? 'Нет в наличии' : 'Дорого' }}</td>
                        <td class="text-right px-5 py-2 font-bold">{{ $row->cnt }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
