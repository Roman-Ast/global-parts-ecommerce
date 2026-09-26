{{--
    Индивидуальный интервал "Пора напомнить" для "Долгоиграющие" (просьба
    Романа 2026-09-26) — показывается ТОЛЬКО когда $lead->status ===
    'long_term'. Живой пример из просьбы: "на больничном, на след.
    неделе напишем" — 11 дней многовато, "ждём страховую" (у них 2
    недели-месяц) — маловато, единый дефолт не подходит. Пресеты и сама
    логика — App\Support\LeadStatuses::REMINDER_INTERVAL_PRESETS/
    setCustomReminderHours(). Без выбора действует дефолт по статусу
    (10 дней, см. REMINDER_THRESHOLDS_HOURS).

    Тот же ленивый паттерн (Alpine x-if, рендерится только пока открыто),
    что и в status-select.blade.php — по той же причине (карточек на
    доске может быть много, лишняя разметка в состоянии покоя тормозит
    wire:poll). @click.stop — та же защита от всплытия на родительский
    wire:click карточки.
--}}
@if($lead->status === \App\Support\LeadStatuses::LONG_TERM_STATUS)
    <div x-data="{ open: false }" @click.stop @click.away="open = false" class="relative {{ $wrapperClass ?? 'mt-1' }}">
        <button
            type="button"
            @click="open = !open"
            class="w-full flex items-center justify-between gap-1 text-[9px] font-bold uppercase tracking-wide text-cyan-600 bg-cyan-50 border-0 outline-none ring-1 ring-cyan-200 rounded-md px-1.5 py-1 hover:ring-cyan-400 transition-colors"
        >
            <span class="truncate">
                @if($lead->custom_reminder_hours && isset(\App\Support\LeadStatuses::REMINDER_INTERVAL_PRESETS[$lead->custom_reminder_hours]))
                    Напомнить через: {{ \App\Support\LeadStatuses::REMINDER_INTERVAL_PRESETS[$lead->custom_reminder_hours] }}
                @else
                    Напомнить: по умолчанию (10 дн.)
                @endif
            </span>
            <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
        </button>

        <template x-if="open">
            <div class="absolute z-20 mt-1 left-0 w-40 max-h-64 overflow-y-auto bg-white rounded-lg shadow-lg ring-1 ring-slate-200 py-1">
                @foreach(\App\Support\LeadStatuses::REMINDER_INTERVAL_PRESETS as $hours => $presetLabel)
                    <button
                        type="button"
                        wire:click="setLeadReminderInterval({{ $lead->id }}, {{ $hours }})"
                        @click="open = false"
                        class="w-full text-left px-3 py-1.5 text-[10px] border-0 bg-transparent outline-none hover:bg-slate-50 {{ $lead->custom_reminder_hours === $hours ? 'font-bold text-cyan-600' : 'text-slate-600' }}"
                    >{{ $presetLabel }}</button>
                @endforeach
            </div>
        </template>
    </div>
@endif
