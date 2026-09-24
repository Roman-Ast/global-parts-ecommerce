{{--
    Бейдж источника лида (site/2gis) — пастельный, не кричащий, по просьбе
    Романа 2026-09-14 (визуально отличать номер сайта от номера 2ГИС в
    канбане и в шапке чата). Один файл — переиспользуется в нескольких
    местах (карточки канбана, шапка открытого чата, список чатов слева).
--}}
@if($source === '2gis')
    <span class="text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-500 border border-emerald-100 whitespace-nowrap">2ГИС</span>
@elseif($source === 'site')
    <span class="text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-sky-50 text-sky-500 border border-sky-100 whitespace-nowrap">Сайт</span>
@elseif($source === 'site_form')
    {{-- Заявка с формы сайта (просьба Романа 2026-09-24) — лид заведён ДО
         первого реального сообщения в WhatsApp, отличаем визуально от
         обычных "Сайт"-лидов, у которых первым делом всегда живое
         сообщение клиента. --}}
    <span class="text-[8px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded bg-violet-50 text-violet-500 border border-violet-100 whitespace-nowrap">Форма</span>
@endif
