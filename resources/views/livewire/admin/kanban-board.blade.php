

<div class="p-6 bg-slate-100 min-h-screen" wire:poll.3s>
    {{-- Заголовок и счетчик --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Канбан CRM (Global Parts)</h1>
            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Управление воронкой продаж</p>
        </div>
        <div class="bg-white px-5 py-2 rounded-2xl shadow-sm border border-slate-200">
            <span class="text-[10px] text-slate-400 font-black uppercase block">Всего лидов</span>
            <span class="text-2xl font-black text-slate-900 leading-none">{{ $totalCount }}</span>
        </div>
    </div>

    {{-- Воронка --}}
    <div class="flex overflow-x-auto pb-8 gap-4 items-start h-[calc(100vh-180px)] custom-scrollbar px-2">
        @foreach($statuses as $key => $info)
            @if($key === 'thinking')
                {{-- ШИРОКАЯ КОЛОНКА ДЛЯ "ДУМАЕТ" --}}
                <div class="flex-shrink-0 w-[900px] flex flex-col h-full bg-slate-200/30 rounded-[2.5rem] p-4 border border-slate-300/50" wire:key="group-{{ $key }}">
                    <div class="flex items-center justify-center mb-4 py-2 bg-slate-800 text-white rounded-2xl shadow-md">
                        <span class="text-[11px] font-black uppercase tracking-[0.3em] italic">{{ $info['title'] }}</span>
                    </div>

                    <div class="grid grid-cols-4 gap-3 h-full">
                        @foreach($info['sub'] as $subKey => $subTitle)
                            <div class="flex flex-col h-full">
                                <div class="text-[9px] font-black text-slate-500 uppercase text-center mb-2 tracking-tighter">
                                    {{ $subTitle }} ({{ isset($leadsByStatus[$subKey]) ? $leadsByStatus[$subKey]->count() : 0 }})
                                </div>
                                
                                <div 
                                    id="status-{{ $subKey }}" 
                                    data-status="{{ $subKey }}"
                                    class="kanban-column flex-grow overflow-y-auto space-y-3 p-2 bg-white/40 rounded-2xl border border-dashed border-slate-300/50"
                                    style="min-height: 150px;"
                                >
                                    @if(isset($leadsByStatus[$subKey]))
                                        @foreach($leadsByStatus[$subKey] as $lead)
                                            <div wire:key="card-{{ $lead->id }}-{{ $lead->lastMessage->id ?? 'none' }}" data-id="{{ $lead->id }}" class="kanban-card bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
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
                <div class="flex-shrink-0 w-[320px] flex flex-col h-full {{ $loop->first ? 'sticky left-0 z-10 bg-slate-100 shadow-[8px_0_12px_-8px_rgba(0,0,0,0.15)]' : '' }}" wire:key="status-col-{{ $key }}">
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

                    <div id="status-{{ $key }}" data-status="{{ $key }}" class="kanban-column flex-grow overflow-y-auto space-y-3 p-2 bg-slate-100/50 rounded-2xl border border-slate-200/50 transition-all custom-scrollbar" style="min-height: 200px;">
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
                                <div wire:key="{{ $cardKey }}" data-id="{{ $lead->id }}" class="kanban-card bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center space-x-2">
                                            @if($lead->has_new)
                                                <span class="flex h-2 w-2 rounded-full bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.6)]"></span>
                                            @endif
                                            <span class="text-xs font-bold text-slate-800 tracking-tighter">+{{ $lead->phone }}</span>
                                            @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                                        </div>
                                        <span class="text-[8px] text-slate-400 font-bold uppercase">{{ $lead->updated_at->diffForHumans() }}</span>
                                    </div>

                                    @if($lastMsg)
                                        <div class="text-[10px] text-slate-500 bg-slate-50 p-2 rounded-lg border border-slate-100 mb-3 line-clamp-2 italic leading-relaxed">
                                            {{ $lastMsg->message_text }}
                                        </div>
                                    @endif

                                    <button @click="$dispatch('open-chat-side-panel')" wire:click="openChat({{ $lead->id }})" wire:loading.attr="disabled" wire:target="openChat({{ $lead->id }})" class="w-full py-2 flex items-center justify-center space-x-1 border border-slate-200 text-slate-400 hover:bg-slate-900 hover:text-white hover:border-slate-900 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all shadow-sm disabled:opacity-60">
                                        <svg wire:loading.remove wire:target="openChat({{ $lead->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        <svg wire:loading wire:target="openChat({{ $lead->id }})" class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span wire:loading.remove wire:target="openChat({{ $lead->id }})">Открыть чат</span>
                                        <span wire:loading wire:target="openChat({{ $lead->id }})">Открываю...</span>
                                    </button>
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

