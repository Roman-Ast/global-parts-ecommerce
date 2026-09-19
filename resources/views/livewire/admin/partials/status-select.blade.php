{{--
    Быстрая смена статуса выпадающим списком (просьба Романа 2026-09-19) —
    запасной путь на случай, когда drag-n-drop "тупит": выбор в селекте
    зовёт тот же KanbanBoard::updateLeadStatus(), что и drop карточки,
    так что уведомление/лог активности/группировка по под-статусам работают
    одинаково для обоих способов.

    @click.stop на обёртке — карточка-родитель имеет свой wire:click
    (open-chat-панель); без остановки всплытия клик по селекту открывал бы
    чат вместо/вместе с выбором пункта.

    Непрочитанные карточки блокированы тем же правилом, что и drag (нельзя
    переместить, пока не открыли и не прочитали хотя бы раз) — иначе
    селект стал бы лазейкой в обход этой защиты.
--}}
<div @click.stop class="mt-1.5">
    <select
        wire:change="updateLeadStatus({{ $lead->id }}, $event.target.value)"
        @if($lead->has_new) disabled title="Сначала откройте и прочитайте сообщение" @endif
        class="w-full text-[9px] font-bold uppercase tracking-wide text-slate-500 bg-white border border-slate-200 rounded-md px-1.5 py-1 cursor-pointer hover:border-blue-300 focus:outline-none focus:ring-1 focus:ring-blue-400 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
    >
        @foreach($statuses as $optKey => $optInfo)
            @if(isset($optInfo['sub']))
                <optgroup label="{{ $optInfo['title'] }}">
                    @foreach($optInfo['sub'] as $subKey => $subLabel)
                        <option value="{{ $subKey }}" @selected($lead->status === $subKey)>{{ $subLabel }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optKey }}" @selected($lead->status === $optKey)>{{ $optInfo['title'] }}</option>
            @endif
        @endforeach
        <optgroup label="Другое">
            <option value="spam" @selected($lead->status === 'spam')>Спам</option>
        </optgroup>
    </select>
</div>
