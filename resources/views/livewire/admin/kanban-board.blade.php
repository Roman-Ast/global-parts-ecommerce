

<div class="p-6 bg-slate-100 min-h-screen" wire:poll.8s.visible="pollTick">
    {{-- Хлебные крошки (просьба Романа 2026-09-17) --}}
    <nav class="mb-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">
        <a href="/admin" class="hover:text-slate-700 transition-colors">Главная</a>
        <span class="mx-1.5 text-slate-300">/</span>
        <span class="text-slate-600">СРМ</span>
    </nav>

    {{-- Заголовок и счетчик --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Канбан CRM (Global Parts)</h1>
            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Управление воронкой продаж</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Поиск по переписке прямо с доски (просьба Романа 2026-09-22
                 — "остаёмся на канбане строго", отдельная страница
                 /admin/whatsapp вообще не используется). Найденный чат
                 открывается ТОЙ ЖЕ шторкой, что и обычный клик по карточке
                 (openChat() — он же сбрасывает searchQuery после открытия).
                 @click.away закрывает дропдаун И чистит поле — иначе
                 забытый текст в закрытом дропдауне впустую пересчитывался
                 бы на каждом wire:poll.8s.visible. --}}
            <div x-data="{ searchOpen: false }" @click.away="searchOpen = false; $wire.set('searchQuery', '')" @keydown.escape.window="searchOpen = false; $wire.set('searchQuery', '')" class="relative">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex items-center gap-2 px-4 py-2 w-72">
                    <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="searchQuery"
                        @focus="searchOpen = true"
                        placeholder="Поиск по переписке или номеру"
                        class="flex-1 min-w-0 text-sm border-0 outline-none bg-transparent placeholder:text-slate-400"
                    >
                    @if($searchQuery)
                        <button type="button" wire:click="$set('searchQuery', '')" class="text-slate-400 hover:text-slate-600 flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    @endif
                </div>

                <div x-show="searchOpen" x-transition class="absolute z-50 mt-2 w-96 max-h-[70vh] overflow-y-auto bg-white rounded-2xl shadow-xl border border-slate-200" style="display: none;">
                    @if(trim($searchQuery) === '')
                        <div class="p-6 text-center text-slate-400 text-xs">Начните вводить текст сообщения или номер</div>
                    @elseif($searchResults->isEmpty())
                        <div class="p-6 text-center text-slate-400 text-xs">Ничего не найдено по «{{ $searchQuery }}»</div>
                    @else
                        @foreach($searchResults as $lead)
                            <div
                                wire:click="openChat({{ $lead->id }})"
                                @click="searchOpen = false"
                                class="cursor-pointer p-3 border-b border-slate-100 hover:bg-slate-50 transition-colors last:border-0"
                            >
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-xs font-bold text-slate-800 truncate">+{{ $lead->phone }}</span>
                                        @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                    </span>
                                    <span class="text-[9px] text-slate-400 flex-shrink-0 uppercase font-bold tracking-wide">{{ \App\Support\LeadStatuses::labelFor($lead->status) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 line-clamp-2">
                                    {!! \App\Support\WhatsappMessageSearch::highlight($lead->search_snippet, $searchQuery) ?: 'Нет сообщений' !!}
                                </p>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="bg-white px-5 py-2 rounded-2xl shadow-sm border border-slate-200">
                <span class="text-[10px] text-slate-400 font-black uppercase block">Всего лидов</span>
                <span class="text-2xl font-black text-slate-900 leading-none">{{ $totalCount }}</span>
            </div>

            {{-- "Корзина" для спама (просьба Романа 2026-09-17) — НЕ стадия
                 воронки, поэтому не отдельная колонка со списком карточек:
                 просто счётчик + Sortable drop-зона (id="status-spam" +
                 class="kanban-column" — initKanban() находит её тем же общим
                 селектором, что и обычные колонки). Содержимое смотрим
                 изредка напрямую в БД, отдельную вьюху под это не делаем. --}}
            <div
                id="status-spam"
                data-status="spam"
                class="kanban-column flex items-center gap-2 bg-rose-50 px-4 py-2 rounded-2xl shadow-sm border border-dashed border-rose-300 min-h-[52px]"
                title="Перетащите сюда, чтобы пометить лид как спам"
            >
                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                <div>
                    <span class="text-[10px] text-rose-400 font-black uppercase block leading-none">Спам</span>
                    <span class="text-sm font-black text-rose-600 leading-none">{{ $spamCount }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Воронка --}}
    <div class="flex overflow-x-auto pb-8 gap-4 items-start h-[calc(100vh-180px)] custom-scrollbar px-2">
        @foreach($statuses as $key => $info)
            @if(isset($info['sub']))
                {{-- ШИРОКАЯ КОЛОНКА С ПОДСТАТУСАМИ — раньше проверялось строго
                     $key === 'thinking' (единственная такая группа была одна);
                     обобщено на isset($info['sub']), чтобы новые группы с
                     подпричинами (напр. 'lost' — "Не купили") автоматически
                     получали ту же раскладку без правки блейда. --}}
                <div class="flex-shrink-0 w-[900px] flex flex-col h-full min-h-0 bg-slate-200/30 rounded-[2.5rem] p-4 border border-slate-300/50" wire:key="group-{{ $key }}">
                    <div class="flex items-center justify-center mb-4 py-2.5 {{ $info['color'] }} text-white rounded-2xl shadow-md">
                        <span class="text-sm font-black uppercase tracking-[0.15em]">{{ $info['title'] }}</span>
                    </div>

                    {{-- grid-cols жёстко было 4 — верно для "Работы с возражениями",
                         но "Не купили" 2026-09-22 доросла до 5 подпричин
                         (добавлена "Не отвечает") и 5-я колонка вываливалась за
                         пределы сетки. Число колонок теперь берётся из реального
                         count($info['sub']) — подстраивается под любую группу
                         автоматически, без ручной правки при следующем добавлении
                         подпричины. Работает с Tailwind CDN (кладём в HTML разметку
                         сразу нужный класс, интерпретатор сканирует итоговый DOM). --}}
                    <div class="grid grid-cols-{{ count($info['sub']) }} gap-3 h-full min-h-0">
                        @foreach($info['sub'] as $subKey => $subTitle)
                            @php
                                $subUnreadCount = isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->where('has_new', true)->count() : 0;
                                $subReminderCount = isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->where('needs_reminder', true)->count() : 0;
                                // "Залежался в Не отвечает" (просьба Романа 2026-09-22) —
                                // is_stale_no_response всегда false вне статуса
                                // 'no_response' (см. KanbanBoard::render()), так что для
                                // остальных 4 подпричин "Не купили" счётчик просто не
                                // покажется, отдельного условия по $subKey не нужно.
                                $subStaleCount = isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->where('is_stale_no_response', true)->count() : 0;
                            @endphp
                            {{-- wire:key отсутствовал на этой обёртке (в отличие от всех
                                 остальных @foreach-элементов в файле) — найдено при разборе
                                 жалобы Романа 2026-09-18 "в Работе с возражениями рамка не
                                 пульсирует, а в обычных колонках пульсирует". Без стабильного
                                 ключа Livewire на каждый wire:poll.8s не может надёжно
                                 сопоставить узел сам с собой и может пересоздавать его —
                                 CSS-анимация при этом перезапускается с нуля каждые ~3
                                 секунды, не успевая визуально проиграться (тот же класс
                                 бага, что раньше ломал сам драг, см. initKanban() выше). --}}
                            <div wire:key="sub-col-{{ $subKey }}" class="flex flex-col h-full min-h-0">
                                <div class="flex items-center justify-center gap-1 text-[9px] font-black text-slate-500 uppercase text-center mb-2 tracking-tighter">
                                    <span>{{ $subTitle }} ({{ isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->count() : 0 }})</span>
                                    @if($subUnreadCount > 0)
                                        <span wire:key="unread-badge-{{ $subKey }}-{{ $subUnreadCount }}" class="kanban-unread-badge flex items-center justify-center min-w-[14px] h-3.5 px-1 rounded-full bg-rose-500 text-white text-[8px] font-black leading-none shadow-[0_0_6px_rgba(244,63,94,0.7)]">
                                            {{ $subUnreadCount }}
                                        </span>
                                    @endif
                                    {{-- Счётчик "пора напомнить" (просьба Романа 2026-09-21) — считается
                                         автоматически через needs_reminder, для колонок вне
                                         REMINDER_ELIGIBLE_STATUSES всегда 0 и просто не отрисуется. --}}
                                    @if($subReminderCount > 0)
                                        <span wire:key="reminder-badge-{{ $subKey }}-{{ $subReminderCount }}" class="kanban-unread-badge flex items-center gap-0.5 justify-center min-w-[14px] h-3.5 px-1 rounded-full bg-amber-500 text-white text-[8px] font-black leading-none shadow-[0_0_6px_rgba(245,158,11,0.7)]">
                                            <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ $subReminderCount }}
                                        </span>
                                    @endif
                                    {{-- Счётчик "залежался больше суток" — только у "Не
                                         отвечает" (просьба Романа 2026-09-22, кандидаты на
                                         повторный ручной контакт, как сегодняшняя продажа
                                         на 103000₸ из старой заявки). Серый, не красный/
                                         жёлтый — это не тревога, это просто "стоит глянуть". --}}
                                    @if($subStaleCount > 0)
                                        <span wire:key="stale-badge-{{ $subKey }}-{{ $subStaleCount }}" class="kanban-unread-badge flex items-center gap-0.5 justify-center min-w-[14px] h-3.5 px-1 rounded-full bg-slate-400 text-white text-[8px] font-black leading-none" title="Молчат больше суток">
                                            <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            {{ $subStaleCount }}
                                        </span>
                                    @endif
                                </div>

                                {{-- min-h-0 по всей цепочке flex/grid-родителей выше — классический
                                     баг: overflow-y-auto здесь не работал (скроллилась вся страница),
                                     потому что flex/grid-контейнеры по умолчанию не дают дочернему
                                     элементу сжаться меньше содержимого (min-height: auto), из-за
                                     чего оверфлоу "вытекал" наверх вместо локального скролла
                                     (просьба Романа 2026-09-17). --}}
                                <div
                                    id="status-{{ $subKey }}"
                                    data-status="{{ $subKey }}"
                                    class="kanban-column flex-grow min-h-0 overflow-y-auto space-y-3 p-2 bg-white/40 rounded-2xl border border-dashed border-slate-300/50"
                                    style="min-height: 150px;"
                                >
                                    @if(isset($leadsByStatus[$subKey]))
                                        @foreach($leadsByStatus[$subKey] as $lead)
                                            <div wire:key="card-{{ $lead->id }}-{{ $lead->lastMessage->id ?? 'none' }}" data-id="{{ $lead->id }}" class="kanban-card {{ $lead->has_new ? 'kanban-card-unread' : '' }} {{ $lead->has_new ? 'kanban-card-pulse' : '' }} {{ $lead->needs_reminder ? 'kanban-card-reminder' : '' }} {{ $lead->is_stale_no_response ? 'kanban-card-stale' : '' }} bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div class="flex items-center space-x-2">
                                                        @if($lead->has_new)
                                                            <span class="inline-flex rounded-full h-3 w-3 bg-green-500 flex-shrink-0"></span>
                                                        @endif
                                                        @if($lead->needs_reminder)
                                                            <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Пора напомнить"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        @endif
                                                        @if($lead->is_stale_no_response)
                                                            <svg class="w-3 h-3 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Молчит больше суток — кандидат на повторный контакт"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        @endif
                                                        <span class="text-xs font-bold text-slate-800 tracking-tighter">+{{ $lead->phone }}</span>
                                                        @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                                    </div>
                                                    {{-- Срок последнего сообщения (просьба Романа 2026-09-19) — та же
                                                         диффа, что уже была только на карточках обычных колонок; здесь
                                                         (широкая секция "Работа с возражениями"/"Не купили") её не было
                                                         вообще. --}}
                                                    <span class="flex-shrink-0 text-[9px] text-slate-400 font-medium whitespace-nowrap">{{ $lead->updated_at->diffForHumans() }}</span>
                                                </div>
                                                @if($lead->lastMessage)
                                                    <div class="text-[10px] text-slate-500 bg-slate-50 p-2 rounded-lg mb-3 line-clamp-2 italic">
                                                        {{ $lead->lastMessage->message_text }}
                                                    </div>
                                                @endif
                                                <button @click="$dispatch('open-chat-side-panel')" wire:click="openChat({{ $lead->id }})" wire:loading.attr="disabled" wire:target="openChat({{ $lead->id }})" class="w-full py-2 flex items-center justify-center space-x-1 border border-slate-200 text-slate-400 hover:bg-slate-900 hover:text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition-all disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="openChat({{ $lead->id }})">Открыть чат</span>
                                                    <svg wire:loading wire:target="openChat({{ $lead->id }})" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                </button>
                                                @include('livewire.admin.partials.status-select', ['lead' => $lead, 'statuses' => $statuses, 'disabled' => $lead->has_new])
                                            </div>
                                        @endforeach
                                    @endif
                                    {{-- Лэйзилоадинг (просьба Романа 2026-09-23) — sentinel вне
                                         Sortable-управляемых карточек (сам div не .kanban-card,
                                         draggable у Sortable ограничен этим селектором — см.
                                         initKanban()), initLazyLoad() наблюдает за ним через
                                         IntersectionObserver и дёргает loadMoreForStatus() при
                                         скролле именно ЭТОЙ колонки до низа. --}}
                                    @if(($statusCounts[$subKey] ?? 0) > (isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->count() : 0))
                                        <div class="kanban-loadmore-sentinel py-2 text-center text-[9px] text-slate-400 font-bold uppercase tracking-wide" data-loadmore-status="{{ $subKey }}">Загрузка ещё…</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- ОБЫЧНАЯ КОЛОНКА. Первая колонка ("Новые") закреплена слева, как
                     "заморозка" первого столбца в Excel (просьба Романа 2026-09-16) —
                     колонок уже больше, чем влезает на экран, и будет больше: удобнее
                     тащить карточку ИЗ неё, прокручивая доску к нужной колонке, не
                     теряя источник из виду. position:sticky работает прямо в этом
                     flex+overflow-x-auto контейнере без доп. обёрток — просто пиннится
                     к левому краю ВИДИМОЙ области скролла (с учётом px-2 на самом
                     контейнере), остальные колонки скроллятся под неё как обычно.
                     Свой непрозрачный фон обязателен (иначе будет просвечивать то, что
                     "проезжает" под ней при скролле), z-10 — чтобы тень действительно
                     легла ПОВЕРХ соседней колонки, а не под неё. --}}
                <div class="flex-shrink-0 w-[320px] flex flex-col h-full min-h-0 {{ $loop->first ? 'sticky left-0 z-10 bg-slate-100 shadow-[8px_0_12px_-8px_rgba(0,0,0,0.15)]' : '' }}" wire:key="status-col-{{ $key }}">
                    @php
                        $columnUnreadCount = isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->where('has_new', true)->count() : 0;
                        $columnReminderCount = isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->where('needs_reminder', true)->count() : 0;
                    @endphp
                    <div class="flex items-center justify-between mb-3 px-3 py-2.5 rounded-xl {{ $info['color'] }} border border-black/5 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-black uppercase text-[10px] tracking-widest">{{ $info['title'] }}</h3>
                            {{-- Бейдж непрочитанных на КАЖДОЙ колонке (просьба Романа
                                 2026-09-18) — раньше искать, кто написал после того, как
                                 карточку уже перетащили из "Новые", приходилось вручную по
                                 колонкам. wire:key завязан на само число — при изменении
                                 счётчика Livewire пересоздаёт узел заново (не просто меняет
                                 текст), а свежевставленный узел сам переигрывает CSS-анимацию
                                 badge-pop — вспышка при получении нового сообщения, без
                                 отдельного JS для отслеживания "изменилось/не изменилось". --}}
                            @if($columnUnreadCount > 0)
                                <span wire:key="unread-badge-{{ $key }}-{{ $columnUnreadCount }}" class="kanban-unread-badge flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-rose-500 text-white text-[9px] font-black leading-none shadow-[0_0_6px_rgba(244,63,94,0.7)]">
                                    {{ $columnUnreadCount }}
                                </span>
                            @endif
                            @if($columnReminderCount > 0)
                                <span wire:key="reminder-badge-{{ $key }}-{{ $columnReminderCount }}" class="kanban-unread-badge flex items-center gap-0.5 justify-center min-w-[16px] h-4 px-1 rounded-full bg-amber-500 text-white text-[9px] font-black leading-none shadow-[0_0_6px_rgba(245,158,11,0.7)]">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $columnReminderCount }}
                                </span>
                            @endif
                        </div>
                        <div class="bg-white/40 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->count() : 0 }}
                        </div>
                    </div>

                    {{-- Вкладки "Все"/"Непрочитанные" — только у колонки "Новые" (по просьбе
                         Романа 2026-09-14). has_new уже посчитан в KanbanBoard::render(). --}}
                    @if($key === 'new')
                        @php
                            $newAllCount = isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->count() : 0;
                            $newUnreadCount = isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->where('has_new', true)->count() : 0;
                        @endphp
                        <div class="flex gap-1 mb-2 p-1 bg-slate-100 rounded-lg">
                            <button wire:click="setNewLeadsTab('all')" class="flex-1 py-1.5 rounded-md text-[9px] font-black uppercase tracking-wider transition-all {{ $newLeadsTab === 'all' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">
                                Все <span class="opacity-60">{{ $newAllCount }}</span>
                            </button>
                            <button wire:click="setNewLeadsTab('unread')" class="flex-1 py-1.5 rounded-md text-[9px] font-black uppercase tracking-wider transition-all {{ $newLeadsTab === 'unread' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">
                                Непрочитанные <span class="opacity-60">{{ $newUnreadCount }}</span>
                            </button>
                        </div>
                    @endif

                    <div id="status-{{ $key }}" data-status="{{ $key }}" class="kanban-column flex-grow min-h-0 overflow-y-auto space-y-3 p-2 bg-slate-100/50 rounded-2xl border border-slate-200/50 transition-all custom-scrollbar" style="min-height: 200px;">
                        @if(isset($leadsByStatus[$key]))
                            @php
                                $columnLeads = ($key === 'new' && $newLeadsTab === 'unread')
                                    ? $leadsByStatus[$key]->where('has_new', true)
                                    : $leadsByStatus[$key];
                            @endphp
                            @foreach($columnLeads as $lead)
                                @php
                                    $lastMsg = $lead->lastMessage;
                                    $cardKey = "card-{$lead->id}-" . ($lastMsg ? $lastMsg->id : 'none');
                                @endphp
                                {{-- Вёрстка ряда — по образцу списка чатов в самом WhatsApp
                                     (просьба Романа 2026-09-17, аватар добавлен 2026-09-22
                                     — "давай сделаем чтобы колонка новые была как в
                                     ватсапе список чатов", только для колонки "Новые",
                                     см. $key === 'new' ниже — остальные колонки менее
                                     "переписочные", там компактный вид без аватара
                                     остаётся как раньше). Клик по всей строке открывает
                                     шторку (раньше была отдельная кнопка "Открыть чат" на
                                     всю ширину — занимала место и делала карточки
                                     "бледными"/разреженными). wire:click на самом ряду не
                                     мешает Sortable — драг отличается от клика по порогу
                                     смещения мыши, тот же приём уже используется в других
                                     местах на сайте. --}}
                                <div
                                    wire:key="{{ $cardKey }}"
                                    data-id="{{ $lead->id }}"
                                    wire:click="openChat({{ $lead->id }})"
                                    @click="$dispatch('open-chat-side-panel')"
                                    wire:loading.class="opacity-50"
                                    wire:target="openChat({{ $lead->id }})"
                                    class="kanban-card {{ $lead->has_new ? 'kanban-card-unread' : '' }} {{ $lead->has_new && $key !== 'new' ? 'kanban-card-pulse' : '' }} {{ $lead->needs_reminder ? 'kanban-card-reminder' : '' }} bg-white px-3 py-2.5 rounded-lg border border-slate-200 cursor-grab active:cursor-grabbing hover:bg-slate-50 hover:border-blue-300 transition-all"
                                >
                                @if($key === 'new')
                                    @php
                                        $avatarBg = match($lead->source) {
                                            '2gis' => 'bg-emerald-100 text-emerald-500',
                                            'site' => 'bg-sky-100 text-sky-500',
                                            default => 'bg-slate-200 text-slate-400',
                                        };
                                    @endphp
                                    <div class="flex items-start gap-2.5">
                                        <div class="relative flex-shrink-0">
                                            <div class="w-11 h-11 rounded-full flex items-center justify-center {{ $avatarBg }}">
                                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.3 0-9.8 1.6-9.8 4.9v2.5h19.6v-2.5c0-3.3-6.5-4.9-9.8-4.9z"/></svg>
                                            </div>
                                            @if($lead->needs_reminder)
                                                <span class="absolute -top-0.5 -left-0.5 w-4 h-4 rounded-full bg-amber-400 border-2 border-white flex items-center justify-center" title="Пора напомнить">
                                                    <svg class="w-2 h-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-[13px] {{ $lead->has_new ? 'font-black text-slate-900' : 'font-semibold text-slate-700' }} truncate">+{{ $lead->phone }}</span>
                                                <span class="flex-shrink-0 text-[10px] {{ $lead->has_new ? 'text-green-600 font-bold' : 'text-slate-400 font-medium' }}">{{ $lead->updated_at->diffForHumans() }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 mt-0.5">
                                                <div class="flex items-center gap-1 min-w-0">
                                                    @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                                    @if($lastMsg)
                                                        <p class="text-[12px] {{ $lead->has_new ? 'text-slate-700 font-medium' : 'text-slate-500' }} truncate">{{ \Illuminate\Support\Str::limit($lastMsg->message_text, 40) }}</p>
                                                    @endif
                                                </div>
                                                @if($lead->has_new)
                                                    <span class="flex-shrink-0 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-green-500 text-white text-[10px] font-black">{{ $lead->unread_count }}</span>
                                                @endif
                                            </div>
                                            @include('livewire.admin.partials.status-select', ['lead' => $lead, 'statuses' => $statuses, 'disabled' => $lead->has_new])
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between gap-2 {{ $lastMsg ? 'mb-1' : '' }}">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            @if($lead->has_new)
                                                <span class="inline-flex rounded-full h-3 w-3 bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.6)] flex-shrink-0"></span>
                                            @endif
                                            @if($lead->needs_reminder)
                                                <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="Пора напомнить"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            @endif
                                            <span class="text-[13px] font-bold text-slate-900 truncate">+{{ $lead->phone }}</span>
                                            @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                        </div>
                                        <span class="flex-shrink-0 text-[10px] text-slate-400 font-medium">{{ $lead->updated_at->diffForHumans() }}</span>
                                    </div>

                                    @if($lastMsg)
                                        <p class="text-[12px] text-slate-600 truncate">{{ \Illuminate\Support\Str::limit($lastMsg->message_text, 50) }}</p>
                                    @endif

                                    @include('livewire.admin.partials.status-select', ['lead' => $lead, 'statuses' => $statuses, 'disabled' => $lead->has_new])
                                @endif
                                </div>
                            @endforeach
                        @endif
                        @if(($statusCounts[$key] ?? 0) > (isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->count() : 0))
                            <div class="kanban-loadmore-sentinel py-2 text-center text-[9px] text-slate-400 font-bold uppercase tracking-wide" data-loadmore-status="{{ $key }}">Загрузка ещё…</div>
                        @endif
                    </div>
                </div>
            @endif

            @if($key === 'new')
                {{-- "Пора напомнить" (просьба Романа 2026-09-21) — ВИРТУАЛЬНАЯ
                     колонка, сразу после "Новые". НЕ реальный статус в БД —
                     просто карточки из $remindersDue (уже отобранные и
                     сгруппированные в KanbanBoard::render() по
                     needs_reminder=true поперёк "КП отправлено" + всех
                     подпричин "Работы с возражениями"). Та же самая модель
                     лида, что и в его родной колонке — просто отрисована
                     ещё раз здесь. Перетащить МОЖНО отсюда в реальный
                     статус (data-no-drop НЕ блокирует "pull", только
                     "put" — см. initKanban() выше) — тащить СЮДА руками
                     нельзя, карточка появляется тут только автоматически. --}}
                <div class="flex-shrink-0 w-[320px] flex flex-col h-full min-h-0" wire:key="status-col-reminders">
                    <div class="flex items-center justify-between mb-3 px-3 py-2.5 rounded-xl bg-amber-500 border border-black/5 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <h3 class="font-black uppercase text-[10px] tracking-widest text-white">Пора напомнить</h3>
                        </div>
                        <div class="bg-white/40 px-2 py-0.5 rounded text-[10px] font-bold text-white">
                            {{ $remindersDueTotal }}
                        </div>
                    </div>

                    <div
                        id="status-reminders"
                        data-status="__reminders_readonly__"
                        data-no-drop="true"
                        class="kanban-column flex-grow min-h-0 overflow-y-auto space-y-3 p-2 bg-amber-50/50 rounded-2xl border border-dashed border-amber-300/50 transition-all custom-scrollbar"
                        style="min-height: 200px;"
                    >
                        @forelse($remindersDue as $lead)
                            @php
                                $lastMsg = $lead->lastMessage;
                            @endphp
                            <div
                                wire:key="reminder-card-{{ $lead->id }}-{{ $lastMsg->id ?? 'none' }}"
                                data-id="{{ $lead->id }}"
                                wire:click="openChat({{ $lead->id }})"
                                @click="$dispatch('open-chat-side-panel')"
                                wire:loading.class="opacity-50"
                                wire:target="openChat({{ $lead->id }})"
                                class="kanban-card kanban-card-reminder bg-white px-3 py-2.5 rounded-lg border border-slate-200 cursor-grab active:cursor-grabbing hover:bg-slate-50 hover:border-amber-300 transition-all"
                            >
                                <div class="flex items-center justify-between gap-2 {{ $lastMsg ? 'mb-1' : '' }}">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span class="text-[13px] font-bold text-slate-900 truncate">+{{ $lead->phone }}</span>
                                        @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                    </div>
                                    <span class="flex-shrink-0 text-[10px] text-slate-400 font-medium">{{ $lead->updated_at->diffForHumans() }}</span>
                                </div>

                                @if($lastMsg)
                                    <p class="text-[12px] text-slate-600 truncate">{{ \Illuminate\Support\Str::limit($lastMsg->message_text, 50) }}</p>
                                @endif

                                @include('livewire.admin.partials.status-select', ['lead' => $lead, 'statuses' => $statuses])
                            </div>
                        @empty
                            <div class="text-[10px] text-amber-600/70 text-center py-6 italic">Пока некому напоминать</div>
                        @endforelse
                        @if($remindersDueTotal > $remindersDue->count())
                            <div class="kanban-loadmore-sentinel py-2 text-center text-[9px] text-amber-500/70 font-bold uppercase tracking-wide" data-loadmore-status="__reminders__">Загрузка ещё…</div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Скрипты и Шторка --}}
    <style>
        .kanban-card-chosen { box-shadow: 0 10px 25px -5px rgba(0,0,0,.15), 0 8px 10px -6px rgba(0,0,0,.1); }
        .kanban-card-dragging { opacity: .92; transform: rotate(1.5deg); }
        /* Вспышка на бейдже непрочитанных при появлении/изменении — сам узел
           каждый раз пересоздаётся (wire:key завязан на число), а свежий DOM-узел
           всегда переигрывает CSS-анимацию заново, без отдельного JS. */
        @keyframes badge-pop {
            0%   { transform: scale(0.4); opacity: 0; }
            60%  { transform: scale(1.25); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .kanban-unread-badge { animation: badge-pop .45s cubic-bezier(.34,1.56,.64,1); }

        /* Пульсирующая рамка на непрочитанной карточке (просьба Романа
           2026-09-18) — НАМЕРЕННО только за пределами колонки "Новые"
           (условие $key !== 'new' в блейде, не здесь): в "Новые" и так всё
           непрочитанное по умолчанию, пульс там был бы шумом на весь экран;
           а вот клиент, который уже уехал в другую колонку и написал
           снова — сигнал, что его реально пропустишь, если не выделить. */
        /* box-shadow ЗДЕСЬ конфликтовал со статичным Tailwind-классом shadow-sm
           у карточек в широкой секции "Работа с возражениями" (жалоба Романа
           2026-09-18 "рамка там не моргает") — у обычных колонок shadow-sm нет,
           поэтому там пульс работал, а в широкой секции его перебивало. По
           спеку анимация должна выигрывать box-shadow независимо от источника,
           но на практике — нет, так что вместо борьбы с cascade просто увели
           пульс на СОВСЕМ ДРУГОЕ свойство (outline), которое shadow-sm не
           трогает вообще, — конфликтовать физически не с чем.

           Статичная рамка вместо бесконечной анимации (просьба Романа
           2026-09-23) — "мигающая рамка" была `animation: ... infinite`,
           то есть на КАЖДОЙ непрочитанной карточке одновременно и БЕЗ
           остановки крутился бесконечный цикл перерисовки outline —
           реальный источник жалобы "весь комп виснет" на слабом/встроенном
           видео (на мощной машине незаметно, ровно то, что Роман и
           обнаружил на компе друга). Оставлена только сама рамка, без
           анимации — сигнал "непрочитано" виден, но не грузит рендер. */
        .kanban-card-pulse { outline: 2px solid rgba(244,63,94,.6); }

        /* Подсветка "пора напомнить" (просьба Романа 2026-09-21, "типа варнинг
           у бутстрапа с часиками") — отдельный CSS-класс со своими цветами, а
           НЕ conditional Tailwind border-*/bg-* классы поверх уже стоящих на
           карточке border-slate-200/bg-white — тот же класс проблемы, что уже
           ловили с .kanban-card-pulse выше (Tailwind через CDN не гарантирует
           порядок правил, конфликт statically-указанных утилит с
           динамическими непредсказуем). !important снимает вопрос полностью. */
        .kanban-card-reminder {
            background-color: #fffbeb !important;
            border-color: #f59e0b !important;
        }

        /* "Залежался в Не отвечает больше суток" (просьба Романа
           2026-09-22) — нейтрально-серая, не тревожная (в отличие от
           жёлтого "Пора напомнить" выше) — это не активная сделка,
           просто подсказка "стоит глянуть", тот же !important-приём по
           той же причине (Tailwind CDN). */
        .kanban-card-stale {
            background-color: #f8fafc !important;
            border-color: #94a3b8 !important;
            border-style: dashed !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Раньше initKanban() ДЕСТРОИЛ и пересоздавал Sortable на КАЖДОЙ колонке
        // при каждом morph.updated — а morph.updated стреляет на КАЖДЫЙ тик
        // wire:poll.8s (шапка всего компонента), то есть каждые 8 секунд,
        // независимо от того, изменилось ли что-то реально. Если в этот
        // момент пользователь как раз тащил карточку — Sortable-инстанс
        // колонки уничтожался прямо под пальцем, drop терялся молча (карточка
        // визуально возвращалась на место или просто не долетала до новой
        // колонки) — похоже, это и есть жалоба "не во все колонки садится".
        // Плюс это просто лишняя работа каждые 8 секунд на ровном месте.
        //
        // Фикс: создаём Sortable на колонке ТОЛЬКО если её там ещё нет
        // (Sortable.get(el) вернёт null для новых узлов после morph — сам
        // элемент колонки стабилен между poll-тиками благодаря постоянному
        // id="status-<ключ>" на div'е колонки, Livewire его не пересоздаёт,
        // только переставляет карточки внутри). Ничего не destroy'им на
        // живых колонках вообще — значит и во время драга их никто не тронет.
        let kanbanDragActive = false;

        function initKanban() {
            document.querySelectorAll('.kanban-column').forEach(el => {
                if (Sortable.get(el)) {
                    return;
                }

                new Sortable(el, {
                    // "Пора напомнить" (data-no-drop, просьба Романа 2026-09-21) —
                    // карточку можно ВЫТАЩИТЬ отсюда (это реально меняет статус
                    // лида как обычно), но НЕЛЬЗЯ затащить руками — она появляется
                    // здесь только автоматически по правилу needs_reminder.
                    group: el.dataset.noDrop === 'true'
                        ? { name: 'leads_pipeline', pull: true, put: false }
                        : 'leads_pipeline',
                    animation: 200,
                    ghostClass: 'bg-blue-50',
                    chosenClass: 'kanban-card-chosen',
                    dragClass: 'kanban-card-dragging',
                    // Автоскролл при перетаскивании к краю экрана (просьба Романа
                    // 2026-09-18) — доска шире экрана, без этого приходилось
                    // сбрасывать карточку на промежуточные колонки, чтобы дотащить
                    // до дальней. bubbleScroll (дефолт true) сам находит нужный
                    // скроллируемый контейнер — у каждой колонки свой overflow-y
                    // (список карточек), но у курсора на правом/левом краю экрана
                    // сработает горизонтальный скролл внешнего ряда колонок, не
                    // вертикальный скролл внутри одной колонки.
                    //
                    // forceFallback ПРОБОВАЛИ здесь (2026-09-18) как фикс нестабильного
                    // автоскролла — откатили в тот же день: живая жалоба Романа "карточки
                    // перестали тащиться вообще". Гипотеза — в этом режиме Sortable сам
                    // отслеживает mousedown/mousemove вместо нативного HTML5 drag&drop, и
                    // это разошлось с wire:poll.8s, который продолжает морфить карточки
                    // каждые 8 секунд даже во время попытки перетаскивания (kanbanDragActive
                    // защищает только от пересоздания САМИХ Sortable-инстансов колонок, не от
                    // морфа содержимого карточек). Рабочий драг важнее автоскролла — оставляем
                    // нативный DnD (scroll:true даёт автоскролл, просто менее надёжно при
                    // повторных перетаскиваниях подряд, это лучше, чем сломанный драг).
                    scroll: true,
                    scrollSensitivity: 80,
                    scrollSpeed: 15,
                    draggable: '.kanban-card',
                    onStart: function () {
                        kanbanDragActive = true;
                    },
                    onEnd: function (evt) {
                        kanbanDragActive = false;
                        const leadId = evt.item.getAttribute('data-id');
                        const newStatus = evt.to.getAttribute('data-status');

                        if (evt.from !== evt.to) {
                            // Непрочитанные карточки (просьба Романа 2026-09-17) — нельзя
                            // перетаскивать, пока не открыли и не прочитали хотя бы раз.
                            // Раньше это блокировалось через Sortable filter — но filter
                            // срабатывает на КАЖДОЕ mousedown, включая обычный клик по
                            // карточке (не только попытку драга), из-за чего сломался
                            // клик "открыть чат" на непрочитанных (жалоба Романа). Теперь
                            // драг стартует как обычно (визуально), а откатывается назад
                            // только по факту РЕАЛЬНОГО дропа в другую колонку — клик
                            // никак не задействует этот код вообще.
                            if (evt.item.classList.contains('kanban-card-unread')) {
                                evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                                window.dispatchEvent(new CustomEvent('unread-drag-blocked'));
                                return;
                            }
                            @this.call('updateLeadStatus', leadId, newStatus);
                        }
                    }
                });
            });
        }

        // Звук уведомления (просьба Романа 2026-09-21, "как в WhatsApp, когда
        // приходят любые сообщения с любой карточки") — сам факт "пришло
        // новое" определяется на сервере (KanbanBoard::render(), сравнение
        // суммы непрочитанных с предыдущим тиком wire:poll.8s), сюда
        // прилетает только команда "сыграй звук". Синтезируем короткий
        // двухтональный "дзинь" через Web Audio API — без отдельного
        // аудиофайла: не нужно ничего хостить/грузить, и звучит достаточно
        // похоже на нотификацию мессенджера.
        let notificationAudioCtx = null;

        function playNotificationSound() {
            try {
                if (!notificationAudioCtx) {
                    notificationAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                }
                // Автоплей-политика браузера иногда держит контекст
                // "suspended" до первого пользовательского жеста — Роман
                // уже взаимодействует со страницей к моменту первого
                // уведомления, но на всякий случай пробуем разбудить.
                if (notificationAudioCtx.state === 'suspended') {
                    notificationAudioCtx.resume();
                }

                const playTone = (freq, startAt, duration) => {
                    const osc = notificationAudioCtx.createOscillator();
                    const gain = notificationAudioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    gain.gain.setValueAtTime(0, startAt);
                    gain.gain.linearRampToValueAtTime(0.2, startAt + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, startAt + duration);
                    osc.connect(gain);
                    gain.connect(notificationAudioCtx.destination);
                    osc.start(startAt);
                    osc.stop(startAt + duration);
                };

                const now = notificationAudioCtx.currentTime;
                playTone(880, now, 0.12);
                playTone(1174.66, now + 0.1, 0.18);
            } catch (e) {
                // Тихо игнорируем — звук не критичен для работы доски.
            }
        }

        // Лэйзилоадинг по колонкам (просьба Романа 2026-09-23) — на экране
        // одновременно видно порядка 4-5 карточек в высоту × ~17 колонок,
        // остальное грузить по факту прокрутки конкретной колонки, а не
        // одним общим лимитом на всю доску (см. INITIAL_COLUMN_LIMIT в
        // KanbanBoard::render()). Каждая колонка, где ещё есть что
        // подгрузить, несёт в конце sentinel-узел с data-loadmore-status —
        // IntersectionObserver наблюдает за всеми сразу; когда прокрутка
        // ДОВОДИТ sentinel до видимой области (это и есть "низ колонки"),
        // дёргаем loadMoreForStatus(status), сервер отдаёт +LOAD_MORE_INCREMENT
        // карточек этой колонки, DOM морфится, initLazyLoad() перевешивает
        // наблюдение на новый (или уже исчезнувший, если догрузили всё) sentinel.
        //
        // root не указан (= viewport) — колонка сама по себе не выше
        // viewport (h-[calc(100vh-180px)] на общем ряду), так что элемент,
        // прокрученный за пределы видимой части колонки, всегда и вне
        // viewport тоже — не нужен отдельный root на каждую колонку.
        let lazyLoadObserver = null;
        const lazyLoadPending = new Set();

        function initLazyLoad() {
            if (lazyLoadObserver) {
                lazyLoadObserver.disconnect();
            }

            lazyLoadObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    const status = entry.target.dataset.loadmoreStatus;
                    if (!status || lazyLoadPending.has(status)) {
                        return;
                    }
                    lazyLoadPending.add(status);
                    @this.call('loadMoreForStatus', status).finally(() => {
                        lazyLoadPending.delete(status);
                    });
                });
            }, { rootMargin: '150px' });

            document.querySelectorAll('[data-loadmore-status]').forEach(el => {
                lazyLoadObserver.observe(el);
            });
        }

        document.addEventListener('livewire:initialized', () => {
            initKanban();
            initLazyLoad();

            Livewire.on('play-notification-sound', () => {
                playNotificationSound();
            });

            // Тот же guard на случай, если Livewire всё-таки подменит саму
            // колонку целиком (не только карточки внутри) — во время
            // активного драга пропускаем переинициализацию, догоняем сразу
            // после отпускания карточки (onEnd уже снял kanbanDragActive к
            // этому моменту, следующий morph.updated её подхватит штатно).
            Livewire.hook('morph.updated', () => {
                if (!kanbanDragActive) {
                    initKanban();
                    initLazyLoad();
                }
            });
        });

        document.addEventListener('livewire:navigated', () => {
            initKanban();
            initLazyLoad();
        });
    </script>

    {{-- Модалка-предупреждение при попытке перетащить непрочитанную заявку
         (просьба Романа 2026-09-17) — сама блокировка драга в initKanban()
         (Sortable filter: '.kanban-card-unread'), это только уведомление.
         Закрывается сама через 2.5с или по клику/Esc. --}}
    <div
        x-data="{ open: false }"
        @unread-drag-blocked.window="open = true; clearTimeout(window.__unreadWarnTimeout); window.__unreadWarnTimeout = setTimeout(() => open = false, 2500)"
        @keydown.escape.window="open = false"
        x-show="open"
        x-transition
        class="fixed inset-0 z-[200] flex items-center justify-center pointer-events-none"
        style="display: none;"
    >
        <div @click="open = false" class="absolute inset-0 bg-slate-900/40 pointer-events-auto"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200 px-6 py-5 max-w-sm mx-4 pointer-events-auto flex items-start gap-3">
            <span class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            </span>
            <div>
                <p class="text-sm font-bold text-slate-800">Заявка не прочитана</p>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">Сначала откройте чат и прочитайте сообщение — потом можно будет переместить карточку.</p>
            </div>
        </div>
    </div>

    {{-- Шторка --}}
    <div x-data="{ open: false }" @open-chat-side-panel.window="open = true" @keydown.escape.window="open = false" class="relative z-[100]">
        <div x-show="open" x-transition:opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
        {{-- pointer-events-none на всех обёртках до самой панели — иначе они,
             будучи прозрачными, но fixed/absolute поверх фона, перехватывают клик
             раньше, чем он доходит до @click="open=false" на фоне выше. --}}
        <div x-show="open" class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="fixed inset-y-0 right-0 flex max-w-full pointer-events-none">
                    <div x-show="open" x-transition:enter="transform transition ease-in-out duration-500" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in-out duration-500" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="w-screen max-w-[50vw] pointer-events-auto">
                        <div class="flex h-full flex-col bg-white shadow-2xl rounded-l-3xl overflow-hidden border-l">
                            <div class="px-6 py-4 bg-slate-50 border-b flex items-center justify-between">
                                <div><h2 class="text-lg font-bold text-slate-800">Быстрый ответ</h2></div>
                                <button @click="open = false" class="p-2 rounded-full hover:bg-slate-200 text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="flex-grow overflow-hidden relative">
                                @if($activeLeadIdForChat)
                                    @livewire('admin.whatsapp-messenger', ['activeLeadId' => $activeLeadIdForChat, 'compactMode' => true], key('side-chat-'.$activeLeadIdForChat))
                                @else
                                    {{-- Шторка уже открылась (чисто клиентский Alpine-клик, см. @click рядом с
                                         wire:click на кнопках выше) — это самый первый клик за сессию, сервер
                                         ещё не успел прислать activeLeadIdForChat. Скелетон вместо пустоты. --}}
                                    <div class="h-full flex items-center justify-center">
                                        <svg class="animate-spin h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </div>
                                @endif

                                {{-- Затемняющая накладка поверх СТАРОГО чата, пока грузится НОВЫЙ (просьба
                                     Романа 2026-09-14 — при переключении чатов было видно, как старые
                                     сообщения "прыгают"/сменяются новыми). wire:target="openChat" без
                                     аргументов матчит вызов с ЛЮБЫМ id лида — то есть срабатывает на
                                     переключение на любой другой чат, не только на первый клик. --}}
                                <div
                                    wire:loading.flex
                                    wire:target="openChat"
                                    class="hidden absolute inset-0 bg-white/70 backdrop-blur-[1px] items-center justify-center z-10 transition-opacity"
                                >
                                    <svg class="animate-spin h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

