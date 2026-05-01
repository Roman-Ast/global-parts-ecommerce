@extends('admin.whatsapp.index')

@section('chat_content')
<div class="flex flex-col h-full">
    <div class="p-4 border-b flex justify-between items-center bg-white shadow-sm">
        <div>
            <h2 class="font-bold text-lg text-gray-800">+{{ $lead->phone }}</h2>
            <p class="text-xs text-gray-500">Источник: {{ strtoupper($lead->source) }}</p>
        </div>
        @if($lead->last_vin)
        <div class="bg-orange-50 border border-orange-200 rounded-lg px-3 py-1">
            <span class="text-[10px] text-orange-400 block uppercase font-bold">Обнаружен VIN</span>
            <span class="font-mono text-orange-700 tracking-wider">{{ $lead->last_vin }}</span>
        </div>
        @endif
    </div>

    <div class="flex-1 overflow-y-auto p-6 bg-[#f0f2f5] space-y-4">
        @foreach($messages as $msg)
            <div class="flex {{ $msg->is_incoming ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[70%] rounded-lg p-3 shadow-sm {{ $msg->is_incoming ? 'bg-white text-gray-800' : 'bg-green-500 text-white' }}">
                    <p class="text-sm">{{ $msg->message_text }}</p>
                    <span class="text-[10px] block mt-1 opacity-60 text-right">
                        {{ $msg->created_at->format('H:i') }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="p-4 bg-gray-50 border-t">
        <div class="flex gap-2">
            <input type="text" placeholder="Введите ответ..." class="flex-1 border rounded-full px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button class="bg-blue-600 text-white px-6 py-2 rounded-full hover:bg-blue-700 transition">
                Отправить
            </button>
        </div>
    </div>
</div>
@endsection