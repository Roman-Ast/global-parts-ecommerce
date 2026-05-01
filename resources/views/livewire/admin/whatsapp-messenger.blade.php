<div wire:poll.10s class="{{ $compactMode ? 'flex w-full h-full' : 'fixed inset-0 top-[64px] flex bg-gray-100 z-10' }}">
    
    {{-- ЛЕВАЯ КОЛОНКА: Отображается только в обычном режиме --}}
    @if(!$compactMode)
        <div class="w-1/3 min-w-[320px] max-w-[450px] bg-white border-r flex flex-col shadow-sm h-full">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <h1 class="text-xl font-bold text-slate-800">WhatsApp Лиды</h1>
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span class="text-xs font-medium text-gray-500">Live</span>
                </div>
            </div>
            
            <div class="overflow-y-auto flex-1 custom-scrollbar">
                @forelse($leads as $lead)
                    <div wire:click="selectLead({{ $lead->id }})" 
                       class="cursor-pointer block p-4 border-b hover:bg-slate-50 transition-colors {{ $activeLeadId == $lead->id ? 'bg-blue-50 border-r-4 border-blue-500' : '' }}">
                        
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-bold text-slate-700">+{{ $lead->phone }}</span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">
                                {{ $lead->last_seen_at ? $lead->last_seen_at->diffForHumans() : '' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-500 truncate pr-2">
                                {{ $lead->messages->first()->message_text ?? 'Нет сообщений' }}
                            </p>
                            @if($lead->last_vin)
                                <span class="flex-shrink-0 bg-orange-100 text-orange-700 text-[10px] px-1.5 py-0.5 rounded font-mono font-bold">
                                    VIN
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400">Пока чатов нет</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ПРАВАЯ КОЛОНКА: Окно переписки (в шторке занимает 100%) --}}
    <div class="flex-1 flex flex-col bg-white overflow-hidden relative h-full">
        @if($activeLead)
            <div class="flex flex-col h-full">
                <div class="p-4 border-b flex justify-between items-center bg-white shadow-sm z-20">
                    <div>
                        <h2 class="font-bold text-lg text-gray-800">+{{ $activeLead->phone }}</h2>
                        <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Источник: {{ $activeLead->source }}</p>
                    </div>
                    @if($activeLead->last_vin)
                        <div class="bg-orange-50 border border-orange-200 rounded-lg px-3 py-1 text-right">
                            <span class="text-[9px] text-orange-400 block uppercase font-bold tracking-tighter">VIN-код</span>
                            <span class="font-mono text-orange-700 font-bold">{{ $activeLead->last_vin }}</span>
                        </div>
                    @endif
                </div>

                <div 
                    id="chat-window" 
                    {{-- Добавляем Alpine.js логику --}}
                    x-data="{ 
                        scrollToBottom() { 
                            $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' }); 
                        } 
                    }"
                    x-init="scrollToBottom()" {{-- Скролл при загрузке (открытии чата) --}}
                    @scroll-chat-to-bottom.window="scrollToBottom()" {{-- Скролл по сигналу из PHP --}}
                    class="flex-1 overflow-y-auto p-6 bg-[#f0f2f5] space-y-4 custom-scrollbar"
                >
                    @foreach($activeLead->messages as $msg)
                        <div class="flex {{ $msg->is_incoming ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[85%] rounded-lg p-3 shadow-sm relative {{ $msg->is_incoming ? 'bg-white text-gray-800 rounded-tl-none' : 'bg-[#dcf8c6] text-gray-800 rounded-tr-none' }}">
                                
                                @if($msg->file_url && (str_contains($msg->type, 'image') || str_contains($msg->message_text, '.jpg') || str_contains($msg->message_text, '.png')))
                                    <div class="mb-2">
                                        <a href="{{ $msg->file_url }}" target="_blank">
                                            <img src="{{ $msg->file_url }}" class="rounded-lg max-h-80 w-full object-contain bg-gray-50 shadow-inner" alt="Фото">
                                        </a>
                                    </div>
                                @endif

                                @if($msg->file_url && str_contains($msg->type, 'audio'))
                                    <div class="mb-2 min-w-[200px]">
                                        <audio controls class="w-full h-8">
                                            <source src="{{ $msg->file_url }}" type="audio/mpeg">
                                        </audio>
                                    </div>
                                @endif

                                <p class="text-[15px] leading-relaxed whitespace-pre-wrap">{{ $msg->message_text }}</p>
                                
                                <div class="flex items-center justify-end gap-1 mt-1 opacity-60">
                                    <span class="text-[9px] uppercase tracking-tighter">
                                        {{ $msg->created_at->format('H:i') }}
                                    </span>
                                    @if(!$msg->is_incoming)
                                        <div class="flex items-center">
                                            @if($msg->status === 'read')
                                                <div class="flex -space-x-1.5">
                                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                </div>
                                            @else
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                    
                <div class="p-4 bg-gray-50 border-t">
                    <div class="flex gap-2">
                        <textarea 
                            wire:model.defer="replyText" 
                            wire:keydown.enter.shift="sendMessage"
                            placeholder="Введите ответ... (Shift+Enter для отправки)" 
                            rows="1"
                            oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'
                            class="flex-1 border border-slate-300 rounded-2xl px-5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all resize-none overflow-hidden"
                        ></textarea>
                        
                        <button wire:click="sendMessage" 
                                class="bg-blue-600 text-white px-8 py-2.5 rounded-full hover:bg-blue-700 transition shadow-md active:scale-95 font-bold uppercase text-xs tracking-widest">
                            ОТПРАВИТЬ
                        </button>
                    </div>
                </div>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mb-4 opacity-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <h2 class="text-lg font-bold text-slate-400 uppercase tracking-widest">Выберите диалог</h2>
                <p class="text-xs opacity-60">Переписка появится здесь автоматически</p>
            </div>
        @endif
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>

    <script>
        // Прокрутка вниз при загрузке и обновлении сообщений
        function scrollToBottom() {
            const container = document.getElementById('chat-window');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        document.addEventListener('livewire:load', function () {
            scrollToBottom();
            Livewire.on('chatSelected', () => {
                setTimeout(scrollToBottom, 100);
            });
        });

        // Следим за обновлениями по poll
        document.addEventListener('livewire:initialized', () => {
            @this.on('messagesUpdated', () => {
                setTimeout(scrollToBottom, 50);
            });
        });
    </script>
</div>