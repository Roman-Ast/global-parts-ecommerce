

<div class="p-6 bg-slate-100 min-h-screen" wire:poll.10s>
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
                                            <div wire:key="card-{{ $lead->id }}-{{ $lead->messages->first()->id ?? '0' }}" data-id="{{ $lead->id }}" class="kanban-card bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
                                                <div class="flex justify-between items-start mb-2">
                                                    <div class="flex items-center space-x-2">
                                                        @if($lead->has_new)
                                                            <span class="flex h-2 w-2 rounded-full bg-green-500"></span>
                                                        @endif
                                                        <span class="text-xs font-bold text-slate-800 tracking-tighter">+{{ $lead->phone }}</span>
                                                    </div>
                                                </div>
                                                @if($lead->messages->first())
                                                    <div class="text-[10px] text-slate-500 bg-slate-50 p-2 rounded-lg mb-3 line-clamp-2 italic">
                                                        {{ $lead->messages->first()->message_text }}
                                                    </div>
                                                @endif
                                                <button wire:click="openChat({{ $lead->id }})" class="w-full py-2 flex items-center justify-center space-x-1 border border-slate-200 text-slate-400 hover:bg-slate-900 hover:text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">
                                                    <span>Открыть чат</span>
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
                {{-- ОБЫЧНАЯ КОЛОНКА --}}
                <div class="flex-shrink-0 w-[320px] flex flex-col h-full" wire:key="status-col-{{ $key }}">
                    <div class="flex items-center justify-between mb-3 px-3 py-2.5 rounded-xl {{ $info['color'] }} border border-black/5 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <h3 class="font-black uppercase text-[10px] tracking-widest">{{ $info['title'] }}</h3>
                        </div>
                        <div class="bg-white/40 px-2 py-0.5 rounded text-[10px] font-bold">
                            {{ isset($leadsByStatus[$key]) ? $leadsByStatus[$key]->count() : 0 }}
                        </div>
                    </div>

                    <div id="status-{{ $key }}" data-status="{{ $key }}" class="kanban-column flex-grow overflow-y-auto space-y-3 p-2 bg-slate-100/50 rounded-2xl border border-slate-200/50 transition-all custom-scrollbar" style="min-height: 200px;">
                        @if(isset($leadsByStatus[$key]))
                            @foreach($leadsByStatus[$key] as $lead)
                                @php 
                                    $lastMsg = $lead->messages->first(); 
                                    $cardKey = "card-v5-{$lead->id}-" . ($lastMsg ? $lastMsg->id : 'none');
                                @endphp
                                <div wire:key="{{ $cardKey }}" data-id="{{ $lead->id }}" class="kanban-card bg-white p-3.5 rounded-xl shadow-sm border border-slate-200 cursor-grab active:cursor-grabbing hover:border-blue-400 transition-all group">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center space-x-2">
                                            @if($lead->has_new)
                                                <span class="flex h-2 w-2 rounded-full bg-green-500 shadow-[0_0_5px_rgba(34,197,94,0.6)]"></span>
                                            @endif
                                            <span class="text-xs font-bold text-slate-800 tracking-tighter">+{{ $lead->phone }}</span>
                                        </div>
                                        <span class="text-[8px] text-slate-400 font-bold uppercase">{{ $lead->updated_at->diffForHumans() }}</span>
                                    </div>

                                    @if($lastMsg)
                                        <div class="text-[10px] text-slate-500 bg-slate-50 p-2 rounded-lg border border-slate-100 mb-3 line-clamp-2 italic leading-relaxed">
                                            {{ $lastMsg->message_text }}
                                        </div>
                                    @endif

                                    <button wire:click="openChat({{ $lead->id }})" class="w-full py-2 flex items-center justify-center space-x-1 border border-slate-200 text-slate-400 hover:bg-slate-900 hover:text-white hover:border-slate-900 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                        <span>Открыть чат</span>
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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function initKanban() {
        // Ищем все элементы с классом .kanban-column, даже если они внутри грида
        const columns = document.querySelectorAll('.kanban-column');
        
        columns.forEach(el => {
            // Удаляем старый экземпляр, если он был
            if (Sortable.get(el)) {
                Sortable.get(el).destroy();
            }

            new Sortable(el, {
                group: 'leads_pipeline', // Общая группа для всех-всех колонок
                animation: 250,
                ghostClass: 'bg-blue-50',
                draggable: ".kanban-card", // Четко указываем, что таскаем
                onEnd: function (evt) {
                    const leadId = evt.item.getAttribute('data-id');
                    const newStatus = evt.to.getAttribute('data-status');
                    
                    if (evt.from !== evt.to) {
                        // Магия Livewire: вызываем метод обновления
                        @this.call('updateLeadStatus', leadId, newStatus);
                    }
                }
            });
        });
    }

    // Инициализация при всех возможных событиях Livewire
    document.addEventListener('livewire:initialized', initKanban);
    document.addEventListener('livewire:navigated', initKanban);
    
    // Самое важное: перепривязка после poll (каждые 10 сек)
    document.addEventListener('livewire:load', () => {
        Livewire.hook('morph.updated', (el, component) => {
            initKanban();
        });
    });

    // Резервный запуск
    initKanban();
        function initSortable() {
            const columns = document.querySelectorAll('.kanban-column');
            columns.forEach(el => {
                if (Sortable.get(el)) Sortable.get(el).destroy();
                new Sortable(el, {
                    group: 'leads_pipeline',
                    animation: 250,
                    ghostClass: 'bg-blue-50',
                    onEnd: (evt) => {
                        const leadId = evt.item.getAttribute('data-id');
                        const newStatus = evt.to.getAttribute('data-status');
                        if (evt.from !== evt.to) {
                            @this.updateLeadStatus(leadId, newStatus);
                        }
                    }
                });
            });
        }
        document.addEventListener('livewire:initialized', initSortable);
        document.addEventListener('livewire:navigated', initSortable);
        document.addEventListener('DOMContentLoaded', initSortable);
        document.addEventListener('livewire:load', () => {
            Livewire.hook('morph.updated', () => initSortable());
        });
    </script>

    {{-- Шторка --}}
    <div x-data="{ open: false }" @open-chat-side-panel.window="open = true" @keydown.escape.window="open = false" class="relative z-[100]">
        <div x-show="open" x-transition:opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
        <div x-show="open" class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="fixed inset-y-0 right-0 flex max-w-full">
                    <div x-show="open" x-transition:enter="transform transition ease-in-out duration-500" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transform transition ease-in-out duration-500" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="w-screen max-w-[50vw]">
                        <div class="flex h-full flex-col bg-white shadow-2xl rounded-l-3xl overflow-hidden border-l">
                            <div class="px-6 py-4 bg-slate-50 border-b flex items-center justify-between">
                                <div><h2 class="text-lg font-bold text-slate-800">Быстрый ответ</h2></div>
                                <button @click="open = false" class="p-2 rounded-full hover:bg-slate-200 text-slate-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="flex-grow overflow-hidden">
                                @if($activeLeadIdForChat)
                                    @livewire('admin.whatsapp-messenger', ['activeLeadId' => $activeLeadIdForChat, 'compactMode' => true], key('side-chat-'.$activeLeadIdForChat))
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

