@extends('layouts.app')

@section('title', 'СРМ')

{{--
    Канбан и мессенджер (livewire/admin/kanban-board, livewire/admin/whatsapp-messenger)
    свёрстаны на Tailwind + Alpine.js — ни того, ни другого в общем layouts/app.blade.php
    нет (сайт на Bootstrap). Подключаем оба только на этой странице через @push, чтобы
    не тащить Tailwind (с его сбросом базовых стилей) на витрину и остальную админку.
    preflight выключен намеренно — иначе Tailwind сбрасывает базовые стили Bootstrap
    (заголовки/кнопки/формы) в шапке/меню, которые рендерит общий layout.
--}}
@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { corePlugins: { preflight: false } };
    </script>
    {{-- Alpine.js отдельно НЕ подключаем — Livewire v3 уже несёт его внутри себя
         (через @livewireScripts в layouts/app.blade.php); второй экземпляр Alpine
         ломает Livewire (`Alpine.transaction is not a function`) и саму шторку. --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .custom-scrollbar { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }

        /* Плавающая кнопка "Написать в WhatsApp" (просьба Романа 2026-09-21) —
           это виджет для витрины (layouts/app.blade.php, #social-media-container),
           общий для всего сайта. На странице самой WhatsApp-CRM она бессмысленна
           (и перекрывает интерфейс справа снизу) — прячем только здесь, не трогая
           сам layout, чтобы на остальном сайте кнопка осталась как была. */
        .whatsapp-fixed-btn,
        .whatsapp-fixed-btn-only-to-open-block {
            display: none !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        @livewire('admin.kanban-board')
    </div>
@endsection