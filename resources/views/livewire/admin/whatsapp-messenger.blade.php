<div wire:poll.5s.visible="pollTick" wire:init="syncReadOnWhatsApp" class="{{ $compactMode ? 'flex w-full h-full' : 'fixed inset-0 top-[64px] flex bg-gray-100 z-10' }}">
    
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

            {{-- Поиск по переписке "как в WhatsApp" (просьба Романа
                 2026-09-22) — пока поле не пустое, список ниже заменяется
                 результатами поиска (см. WhatsappMessenger::searchLeads()),
                 ищет и по тексту сообщений, и по номеру телефона. Крестик
                 очистки показывается только когда есть что чистить.
                 debounce.400ms — не долбим БД на каждое нажатие клавиши. --}}
            <div class="p-3 border-b bg-white">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="searchQuery"
                        placeholder="Поиск по переписке или номеру"
                        class="w-full pl-9 pr-8 py-2 text-sm rounded-full bg-slate-100 border-0 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all"
                    >
                    @if($searchQuery)
                        <button type="button" wire:click="$set('searchQuery', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    @endif
                </div>
            </div>

            <div class="overflow-y-auto flex-1 custom-scrollbar">
                @forelse($leads as $lead)
                    <div wire:click="selectLead({{ $lead->id }})"
                       class="cursor-pointer block p-4 border-b hover:bg-slate-50 transition-colors {{ $activeLeadId == $lead->id ? 'bg-blue-50 border-r-4 border-blue-500' : '' }}">

                        <div class="flex justify-between items-start mb-1">
                            <span class="flex items-center gap-1.5">
                                <span class="font-bold text-slate-700">+{{ $lead->phone }}</span>
                                @include('livewire.admin.partials.source-badge', ['source' => $lead->source])
                            </span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">
                                {{ $lead->last_seen_at ? $lead->last_seen_at->diffForHumans() : '' }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-500 truncate pr-2">
                                {!! $this->highlightMatch($lead->search_snippet ?? ($lead->lastMessage->message_text ?? null), $searchQuery) ?: 'Нет сообщений' !!}
                            </p>
                            @if($lead->last_vin)
                                <span class="flex-shrink-0 bg-orange-100 text-orange-700 text-[10px] px-1.5 py-0.5 rounded font-mono font-bold">
                                    VIN
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-400 text-sm">
                        @if($searchQuery)
                            Ничего не найдено по «{{ $searchQuery }}»
                        @else
                            Пока чатов нет
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ПРАВАЯ КОЛОНКА: Окно переписки (в шторке занимает 100%) --}}
    <div class="flex-1 flex flex-col bg-white overflow-hidden relative h-full">
        @if($activeLead)
            <div class="flex flex-col h-full">
                <div class="p-4 border-b flex justify-between items-center bg-white shadow-sm z-20">
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-lg text-gray-800">+{{ $activeLead->phone }}</h2>
                        @include('livewire.admin.partials.source-badge', ['source' => $activeLead->source])
                    </div>
                    <div class="flex items-center gap-3">
                        @if($activeLead->last_vin)
                            <div class="bg-orange-50 border border-orange-200 rounded-lg px-3 py-1 text-right">
                                <span class="text-[9px] text-orange-400 block uppercase font-bold tracking-tighter">VIN-код</span>
                                <span class="font-mono text-orange-700 font-bold">{{ $activeLead->last_vin }}</span>
                            </div>
                        @endif
                        {{-- Смена статуса прямо из чата, не закрывая переписку (просьба
                             Романа 2026-09-19) — тот же партиал, что и на карточках
                             канбана, см. его докблок. wrapperClass переопределяет
                             дефолтный mt-1.5 (там он нужен под карточкой, здесь —
                             самостоятельный элемент в шапке чата, без верхнего отступа
                             и с фиксированной шириной). --}}
                        @include('livewire.admin.partials.status-select', ['lead' => $activeLead, 'statuses' => $statuses, 'wrapperClass' => 'w-40'])
                        @include('livewire.admin.partials.reminder-interval-select', ['lead' => $activeLead, 'wrapperClass' => 'w-40 mt-1'])
                    </div>
                </div>

                <div
                    id="chat-window"
                    {{-- Добавляем Alpine.js логику --}}
                    x-data="{
                        scrollToBottom(smooth = true) {
                            $el.scrollTo({ top: $el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
                        }
                    }"
                    x-init="
                        {{-- При ОТКРЫТИИ чата — мгновенно, без анимации (как в реальном WhatsApp,
                             чат сразу открывается внизу, не едет туда на глазах). $nextTick — ждём,
                             пока Alpine домонтирует все сообщения в DOM, иначе scrollHeight ещё не
                             финальный. Плюс короткий таймаут — картинки/файлы в сообщениях меняют
                             высоту уже ПОСЛЕ загрузки, из-за этого чат утром 2026-09-14 открывался
                             не докрученным до конца. --}}
                        $nextTick(() => scrollToBottom(false));
                        setTimeout(() => scrollToBottom(false), 300);

                        {{-- Фокус на поле ввода при открытии чата (просьба Романа
                             2026-09-18) — тот же $nextTick, что и у скролла: этот x-init
                             перевыполняется заново при каждой смене активного лида
                             (весь #chat-window пересоздаётся, т.к. компонент
                             WhatsappMessenger висит на wire:key='side-chat-{id}'), так
                             что фокус переставляется на новое поле при переключении
                             между чатами, не только при первом открытии шторки. --}}
                        $nextTick(() => document.getElementById('whatsapp-reply-input')?.focus());
                    "
                    @scroll-chat-to-bottom.window="scrollToBottom(true)" {{-- Новое сообщение при уже открытом чате — плавно --}}
                    class="flex-1 overflow-y-auto p-6 bg-[#f0f2f5] space-y-4 custom-scrollbar"
                >
                    {{-- Разделитель дат по центру, как в самом WhatsApp (просьба
                         Романа 2026-09-19) — ненавязчивая маленькая "таблетка",
                         показывается только когда дата СМЕНИЛАСЬ относительно
                         предыдущего сообщения в этой же прокрутке (не на каждое
                         сообщение). Сообщения уже идут в хронологическом порядке
                         (от старых к новым, см. render() в WhatsappMessenger). --}}
                    @php($lastMsgDate = null)
                    @foreach($activeLead->messages as $msg)
                        @php($msgDate = $msg->created_at->toDateString())
                        @if($msgDate !== $lastMsgDate)
                            <div class="flex justify-center my-3">
                                <span class="text-[10px] font-medium text-slate-400 bg-white/70 px-3 py-1 rounded-full">
                                    @if($msg->created_at->isToday())
                                        Сегодня
                                    @elseif($msg->created_at->isYesterday())
                                        Вчера
                                    @else
                                        {{ $msg->created_at->translatedFormat($msg->created_at->isCurrentYear() ? 'd MMMM' : 'd MMMM Y') }}
                                    @endif
                                </span>
                            </div>
                        @endif
                        @php($lastMsgDate = $msgDate)
                        <div class="flex {{ $msg->is_incoming ? 'justify-start' : 'justify-end' }} mb-3">
                            <div class="max-w-[85%] rounded-lg p-3 shadow-sm relative {{ $msg->is_incoming ? 'bg-white text-gray-800 rounded-tl-none' : 'bg-[#dcf8c6] text-gray-800 rounded-tr-none' }}">
                                
                                {{-- 1. ЛОГИКА ДЛЯ ИЗОБРАЖЕНИЙ --}}
                                @if($msg->file_url && (str_contains($msg->type, 'image') || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $msg->file_url)))
                                    <div class="mb-2">
                                        <a href="{{ $msg->file_url }}" target="_blank">
                                            <img src="{{ $msg->file_url }}" class="rounded-lg max-h-80 w-full object-contain bg-gray-50 shadow-inner" alt="Фото">
                                        </a>
                                    </div>

                                {{-- 2. ЛОГИКА ДЛЯ PDF И ДОКУМЕНТОВ --}}
                                @elseif($msg->file_url && (str_contains($msg->type, 'document') || str_ends_with(strtolower($msg->file_url), '.pdf')))
                                    <div class="mb-2 p-3 bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-3">
                                        <div class="w-10 h-10 bg-red-100 flex items-center justify-center rounded text-red-600">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg>
                                        </div>
                                        <div class="flex-1 overflow-hidden">
                                            <p class="text-xs font-bold truncate text-gray-700">Техпаспорт / PDF</p>
                                            <a href="{{ $msg->file_url }}" target="_blank" class="text-[11px] text-blue-600 hover:underline font-semibold">
                                                ОТКРЫТЬ ФАЙЛ
                                            </a>
                                        </div>
                                    </div>

                                {{-- 3. ЛОГИКА ДЛЯ АУДИО --}}
                                @elseif($msg->file_url && str_contains($msg->type, 'audio'))
                                    <div class="mb-2 min-w-[200px]">
                                        <audio controls class="w-full h-8">
                                            <source src="{{ $msg->file_url }}" type="audio/mpeg">
                                        </audio>
                                    </div>
                                @endif

                                <p class="text-[15px] leading-relaxed whitespace-pre-wrap">{!! $this->linkify($msg->message_text) !!}</p>
                                
                                <div class="flex items-center justify-end gap-1 mt-1 opacity-60">
                                    <span class="text-[9px] uppercase tracking-tighter">
                                        {{ $msg->created_at->format('H:i') }}
                                    </span>
                                    @if(!$msg->is_incoming)
                                        <div class="flex items-center">
                                            @if($msg->status === 'read')
                                                {{-- прочитано — двойная синяя --}}
                                                <div class="flex -space-x-1.5">
                                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                </div>
                                            @elseif($msg->status === 'delivered')
                                                {{-- доставлено — двойная серая --}}
                                                <div class="flex -space-x-1.5">
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                                </div>
                                            @elseif($msg->status === 'failed')
                                                {{-- ошибка отправки --}}
                                                <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M12 8v4m0 4h.01M12 3l9 16H3L12 3z" /></svg>
                                            @else
                                                {{-- отправлено (sent), ещё не доставлено — одна серая --}}
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                    
                {{-- Быстрые ответы (просьба Романа 2026-09-21) — фиксированный
                     набор шаблонных сообщений, которые иначе печатались бы
                     вручную по многу раз на дню. Чекбоксы + ОТДЕЛЬНАЯ кнопка
                     "Отправить выбранные" (не смешана с обычной отправкой из
                     поля ввода ниже) — каждое отмеченное сообщение уходит
                     отдельной репликой, см. WhatsappMessenger::sendQuickReplies(). --}}
                <div class="px-4 pt-3 pb-1 bg-gray-50 border-t">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                        @foreach($quickReplies as $i => $reply)
                            <label class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    wire:model="selectedQuickReplies"
                                    value="{{ $i }}"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                >
                                {{ $reply['label'] }}
                            </label>
                        @endforeach
                        <button
                            wire:click="sendQuickReplies"
                            wire:loading.attr="disabled"
                            wire:target="sendQuickReplies"
                            class="ml-auto bg-slate-500 text-white px-4 py-1.5 rounded-full hover:bg-slate-600 transition shadow-sm active:scale-95 font-bold uppercase text-[10px] tracking-widest disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Отправить выбранные
                        </button>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 border-t" x-data="{ uploadingImage: false }">
                    <div class="flex gap-2">
                        {{-- wire:ignore — без него wire:poll.5s на корневом div (строка 1)
                             каждые 5 секунд перерисовывал этот textarea и стирал
                             инлайновую высоту, которую ставит oninput ниже: визуально
                             это выглядело как "расширяется при вставке, потом сжимается
                             обратно" (жалоба Романа 2026-09-17) — рост был настоящим,
                             просто следующий poll-тик его тут же откатывал. wire:model.defer
                             продолжает работать как обычно (слушатели вешаются один раз
                             при монтировании, wire:ignore лишь отключает повторный морф
                             этого узла) — очистка поля после отправки теперь не через
                             морф (он больше не трогает этот элемент), а явно ниже по
                             событию scroll-chat-to-bottom, которое sendMessage() и так
                             диспатчит сразу после успешной отправки. Синтетический
                             'input' после очистки — чтобы wire:model.defer тоже увидел
                             пустое значение, а не только визуально пустое поле. --}}
                        <textarea
                            id="whatsapp-reply-input"
                            wire:ignore
                            wire:model.defer="replyText"
                            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                            x-on:scroll-chat-to-bottom.window="$el.value = ''; $el.style.height = ''; $el.dispatchEvent(new Event('input'))"
                            {{-- Вставка картинки из буфера обмена (Ctrl+V, просьба Романа
                                 2026-09-18) — clipboardData.items доступен только в самом
                                 событии paste, поэтому логика целиком инлайновая, не через
                                 отдельный метод. $wire.upload — штатный Livewire JS API для
                                 File/Blob (WithFileUploads на бэкенде), не нужно вручную
                                 base64-кодировать. Текст вставляется как обычно (условие
                                 срабатывает только когда в буфере реально картинка). --}}
                            x-on:paste="
                                const items = $event.clipboardData?.items || [];
                                for (const item of items) {
                                    if (item.type && item.type.startsWith('image/')) {
                                        $event.preventDefault();
                                        const file = item.getAsFile();
                                        if (!file) continue;
                                        uploadingImage = true;
                                        $wire.upload('pastedImage', file,
                                            () => { $wire.call('sendPastedImage').then(() => { uploadingImage = false; }); },
                                            () => { uploadingImage = false; }
                                        );
                                        break;
                                    }
                                }
                            "
                            placeholder="Введите ответ... (Enter — отправить, Shift+Enter — новая строка, Ctrl+V — вставить картинку)"
                            rows="1"
                            oninput='this.style.height = "";this.style.height = this.scrollHeight + "px"'
                            class="flex-1 border border-slate-300 rounded-2xl px-5 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all resize-none overflow-y-auto max-h-[240px] custom-scrollbar"
                        ></textarea>

                        <button wire:click="sendMessage"
                                class="bg-blue-600 text-white px-8 py-2.5 rounded-full hover:bg-blue-700 transition shadow-md active:scale-95 font-bold uppercase text-xs tracking-widest">
                            ОТПРАВИТЬ
                        </button>
                    </div>
                    <div x-show="uploadingImage" class="text-xs text-slate-400 px-1 mt-1.5 flex items-center gap-1.5">
                        <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Отправляю изображение...
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