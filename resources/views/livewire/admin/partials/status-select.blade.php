{{--
    Быстрая смена статуса (просьба Романа 2026-09-19) — запасной путь на
    случай, когда drag-n-drop "тупит": выбор пункта зовёт тот же
    KanbanBoard::updateLeadStatus(), что и drop карточки, так что
    уведомление/лог активности/группировка по под-статусам работают
    одинаково для обоих способов.

    ВАЖНО про производительность — первая версия была обычным <select> с
    ~17 <option>/<optgroup> внутри, отрендеренным сразу на всех до 200
    карточек доски (LEADS_LIMIT). При wire:poll.3s Livewire на каждый тик
    перепроверяет/морфит эту разметку у ВСЕХ карточек разом (не только у
    тех, что реально изменились) — ~3000+ лишних узлов на каждый опрос
    заметно затормозили всю доску (жалоба Романа "все лагать жутко").
    Сейчас список опций рендерится ЛЕНИВО через Alpine `<template x-if>` —
    физически существует в DOM только пока конкретное меню открыто, в
    состоянии покоя на карточке всего одна маленькая кнопка.

    @click.stop на обёртке — карточка-родитель имеет свой wire:click
    (open-chat-панель); без остановки всплытия клик по меню открывал бы
    чат вместо/вместе с выбором пункта.

    Непрочитанные карточки блокированы тем же правилом, что и drag (нельзя
    переместить, пока не открыли и не прочитали хотя бы раз) — иначе меню
    стало бы лазейкой в обход этой защиты.
--}}
<div x-data="{ open: false }" @click.stop @click.away="open = false" class="relative mt-1.5">
    <button
        type="button"
        @click="open = !open"
        @if($lead->has_new) disabled title="Сначала откройте и прочитайте сообщение" @endif
        class="w-full flex items-center justify-between gap-1 text-[9px] font-bold uppercase tracking-wide text-slate-500 bg-white border border-slate-200 rounded-md px-1.5 py-1 hover:border-blue-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
    >
        <span>Сменить статус</span>
        <svg class="w-2.5 h-2.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
    </button>

    <template x-if="open">
        <div class="absolute z-20 mt-1 left-0 w-52 max-h-64 overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg py-1">
            @foreach($statuses as $optKey => $optInfo)
                @if(isset($optInfo['sub']))
                    <div class="px-3 pt-1.5 pb-0.5 text-[8px] font-black uppercase tracking-wider text-slate-400">{{ $optInfo['title'] }}</div>
                    @foreach($optInfo['sub'] as $subKey => $subLabel)
                        <button
                            type="button"
                            wire:click="updateLeadStatus({{ $lead->id }}, '{{ $subKey }}')"
                            @click="open = false"
                            class="w-full text-left px-3 py-1.5 text-[10px] hover:bg-slate-50 {{ $lead->status === $subKey ? 'font-bold text-blue-600' : 'text-slate-600' }}"
                        >{{ $subLabel }}</button>
                    @endforeach
                @else
                    <button
                        type="button"
                        wire:click="updateLeadStatus({{ $lead->id }}, '{{ $optKey }}')"
                        @click="open = false"
                        class="w-full text-left px-3 py-1.5 text-[10px] hover:bg-slate-50 {{ $lead->status === $optKey ? 'font-bold text-blue-600' : 'text-slate-600' }}"
                    >{{ $optInfo['title'] }}</button>
                @endif
            @endforeach
            <div class="px-3 pt-1.5 pb-0.5 text-[8px] font-black uppercase tracking-wider text-slate-400">Другое</div>
            <button
                type="button"
                wire:click="updateLeadStatus({{ $lead->id }}, 'spam')"
                @click="open = false"
                class="w-full text-left px-3 py-1.5 text-[10px] hover:bg-slate-50 {{ $lead->status === 'spam' ? 'font-bold text-rose-600' : 'text-rose-500' }}"
            >Спам</button>
        </div>
    </template>
</div>
