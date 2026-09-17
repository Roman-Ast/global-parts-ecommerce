

<div class="p-6 bg-slate-100 min-h-screen" wire:poll.3s>
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
                    <div class="flex items-center justify-center mb-4 py-2 {{ $info['color'] }} text-white rounded-2xl shadow-md">
                        <span class="text-[11px] font-black uppercase tracking-[0.3em] italic">{{ $info['title'] }}</span>
                    </div>

                    <div class="grid grid-cols-4 gap-3 h-full min-h-0">
                        @foreach($info['sub'] as $subKey => $subTitle)
                            <div class="flex flex-col h-full min-h-0">
                                <div class="text-[9px] font-black text-slate-500 uppercase text-center mb-2 tracking-tighter">
                                    {{ $subTitle }} ({{ isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->count() : 0 }})
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
                                            <div wire:key="card-{{ $lead->id }}-{{ $lead->lastMessage->id ?? 'none' }}" data-id="{{ $lead->id }}" class="kanban-card {{ $lead->has_new ? 'kanban-card-unread' : '' }} bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div class="flex items-center space-x-2">
                                                        @if($lead->has_new)
                                                            <span class="flex h-2 w-2 rounded-full bg-green-500"></span>
                                                        @endif
                                                        <span class="text-xs font-bold text-slate-800 tracking-tighter">+{{ $lead->phone }}</span>
                                                    </div>
                                                    @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
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
                                            </div>
                                        @endforeach
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
                    <div class="flex items-center justify-between mb-3 px-3 py-2.5 rounded-xl {{ $info['color'] }} border border-black/5 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-black uppercase text-[10px] tracking-widest">{{ $info['title'] }}</h3>
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
                                     (просьба Романа 2026-09-17): без аватара, номер+источник
                                     в одну строку, превью сообщения обрезано до 50 символов,
                                     клик по всей строке открывает шторку (раньше была
                                     отдельная кнопка "Открыть чат" на всю ширину — занимала
                                     место и делала карточки "бледными"/разреженными).
                                     wire:click на самом ряду не мешает Sortable — драг
                                     отличается от клика по порогу смещения мыши, тот же
                                     приём уже используется в других местах на сайте. --}}
                                <div
                                    wire:key="{{ $cardKey }}"
                                    data-id="{{ $lead->id }}"
                                    wire:click="openChat({{ $lead->id }})"
                                    @click="$dispatch('open-chat-side-panel')"
                                    wire:loading.class="opacity-50"
                                    wire:target="openChat({{ $lead->id }})"
                                    class="kanban-card {{ $lead->has_new ? 'kanban-card-unread' : '' }} bg-white px-3 py-2.5 rounded-lg border border-slate-200 cursor-grab active:cursor-grabbing hover:bg-slate-50 hover:border-blue-300 transition-all"
                                >
                                    <div class="flex items-center justify-between gap-2 {{ $lastMsg ? 'mb-1' : '' }}">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            @if($lead->has_new)
                                                <span class="flex-shrink-0 h-2 w-2 rounded-full bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.6)]"></span>
                                            @endif
                                            <span class="text-[13px] font-bold text-slate-900 truncate">+{{ $lead->phone }}</span>
                                            @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                        </div>
                                        <span class="flex-shrink-0 text-[10px] text-slate-400 font-medium">{{ $lead->updated_at->diffForHumans() }}</span>
                                    </div>

                                    @if($lastMsg)
                                        <p class="text-[12px] text-slate-600 truncate">{{ \Illuminate\Support\Str::limit($lastMsg->message_text, 50) }}</p>
                                    @endif
                                </div>
                            @endforeach
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
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Раньше initKanban() ДЕСТРОИЛ и пересоздавал Sortable на КАЖДОЙ колонке
        // при каждом morph.updated — а morph.updated стреляет на КАЖДЫЙ тик
        // wire:poll.3s (шапка всего компонента), то есть каждые 3 секунды,
        // независимо от того, изменилось ли что-то реально. Если в этот
        // момент пользователь как раз тащил карточку — Sortable-инстанс
        // колонки уничтожался прямо под пальцем, drop терялся молча (карточка
        // визуально возвращалась на место или просто не долетала до новой
        // колонки) — похоже, это и есть жалоба "не во все колонки садится".
        // Плюс это просто лишняя работа каждые 3 секунды на ровном месте.
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
                    group: 'leads_pipeline',
                    animation: 200,
                    ghostClass: 'bg-blue-50',
                    chosenClass: 'kanban-card-chosen',
                    dragClass: 'kanban-card-dragging',
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

        document.addEventListener('livewire:initialized', () => {
            initKanban();

            // Тот же guard на случай, если Livewire всё-таки подменит саму
            // колонку целиком (не только карточки внутри) — во время
            // активного драга пропускаем переинициализацию, догоняем сразу
            // после отпускания карточки (onEnd уже снял kanbanDragActive к
            // этому моменту, следующий morph.updated её подхватит штатно).
            Livewire.hook('morph.updated', () => {
                if (!kanbanDragActive) {
                    initKanban();
                }
            });
        });

        document.addEventListener('livewire:navigated', initKanban);
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

