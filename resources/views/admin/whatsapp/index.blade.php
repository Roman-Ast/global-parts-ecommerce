@extends('layouts.app')

{{-- См. admin/kanban.blade.php — тот же Tailwind/Alpine, тот же повод (не тащить их
     на витрину/остальную админку), preflight выключен по той же причине. --}}
@push('styles')
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { corePlugins: { preflight: false } };
    </script>
    {{-- Alpine.js отдельно НЕ подключаем — см. admin/kanban.blade.php --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .custom-scrollbar { scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    </style>
@endpush

@section('content')
    <livewire:admin.whatsapp-messenger />
@endsection