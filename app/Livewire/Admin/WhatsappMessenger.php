<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WhatsappLead;

class WhatsappMessenger extends Component
{
    public $activeLeadId;
    public $replyText = '';
    public $compactMode = false;

    protected $listeners = [
        'echo:messages,MessageReceived' => 'handleIncomingMessage', // Если используешь Laravel Echo
        'refreshChat' => '$refresh' // Обычный рефреш
    ];

    public function selectLead($id)
    {
        $this->activeLeadId = $id;
        

        // Как только выбрали лида — помечаем все сообщения от него как прочитанные
        \App\Models\WhatsappMessage::where('whatsapp_lead_id', $id)
            ->where('is_incoming', true) // помечаем только ВХОДЯЩИЕ
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function render()
    {
        // 1. Берем лидов именно для списка чатов (сортируем по дате сообщения)
        $leads = \App\Models\WhatsappLead::with(['messages' => function($q) {
                $q->latest();
            }])
            ->orderBy('last_seen_at', 'desc')
            ->get();

        // 2. ВОЗВРАЩАЕМ ВЬЮХУ МЕССЕНДЖЕРА, а не канбана!
        return view('livewire.admin.whatsapp-messenger', [
            'leads' => $leads,
            'activeLead' => $this->activeLeadId ? \App\Models\WhatsappLead::find($this->activeLeadId) : null,
        ]);
    }

    public function sendMessage()
    {
        // Если чат не выбран или текст пустой — ничего не делаем
        if (!$this->activeLeadId || empty(trim($this->replyText))) {
            return;
        }

        $lead = \App\Models\WhatsappLead::find($this->activeLeadId);
        
        $instanceId = config('services.green_api.instance_id');
        $token = config('services.green_api.token');
        
        $url = "https://api.green-api.com/waInstance{$instanceId}/sendMessage/{$token}";

        try {
            $response = \Illuminate\Support\Facades\Http::post($url, [
                'chatId' => $lead->phone . '@c.us',
                'message' => $this->replyText,
            ]);

            if ($response->successful()) {
                // 1. Сохраняем сообщение
                $lead->messages()->create([
                    'instance_id' => $instanceId,
                    'message_text' => $this->replyText,
                    'is_incoming' => false,
                    'is_read' => true,
                    'message_id' => $response->json()['idMessage'] ?? uniqid(),
                    'type' => 'chat',
                ]);

                // 2. Обновляем время (update обновит и last_seen_at, и updated_at)
                $lead->update(['last_seen_at' => now()]);
                
                // Если хочешь быть уверен на 100%, можно добавить touch(), 
                // но технически update выше это уже сделал.
                $lead->touch(); 

                $this->dispatch('scroll-chat-to-bottom');
                $this->replyText = '';
                
                $this->dispatch('refreshKanban')->to('admin.kanban-board');
            }
        } catch (\Exception $e) {
            \Log::error("Ошибка отправки WhatsApp: " . $e->getMessage());
        }
    }

    // Добавь этот метод mount
    public function mount($activeLeadId = null, $compactMode = false)
    {
        $this->compactMode = $compactMode;
        
        if ($activeLeadId) {
            $this->dispatch('scroll-chat-to-bottom');
            $this->activeLeadId = $activeLeadId;
            // Сразу помечаем прочитанным, раз мы открыли этот чат
            \App\Models\WhatsappMessage::where('whatsapp_lead_id', $activeLeadId)
                ->where('is_incoming', true)
                ->update(['is_read' => true]);
        }
    }

    public function handleIncomingMessage()
    {
        // Если чат открыт — помечаем всё прочитанным сразу
        if ($this->activeLeadId) {
            \App\Models\WhatsappMessage::where('whatsapp_lead_id', $this->activeLeadId)
                ->where('is_incoming', true)
                ->where('is_read', false)
                ->update(['is_read' => true]);
                
            // Обновляем сам канбан, чтобы там точка тоже погасла/не загоралась
            $this->dispatch('refreshKanban')->to('admin.kanban-board');
        }
    }
}