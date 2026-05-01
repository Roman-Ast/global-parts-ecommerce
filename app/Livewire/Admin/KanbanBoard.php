<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;

class KanbanBoard extends Component
{
    public $activeLeadIdForChat = null;

    // Твоя основная воронка (оставляем её здесь)
    public $statuses = [
        'new'       => ['title' => 'Новые', 'color' => 'bg-blue-500'],
        'selection' => ['title' => 'Подбор', 'color' => 'bg-yellow-500'],
        'offer'     => ['title' => 'КП Отправлено', 'color' => 'bg-indigo-500'],
        
        // Групповой статус
        'thinking'  => [
            'title' => 'Работа с возражениями', 
            'color' => 'bg-slate-700',
            'sub' => [
                'silent'    => 'Молчит',
                'expensive' => 'Дорого',
                'wait'      => 'Сроки',
                'pending'   => 'Думает'
            ]
        ],

        'payment'   => ['title' => 'Оплата', 'color' => 'bg-green-500'],
        'deal_closed'   => ['title' => 'Сделка закрыта', 'color' => 'bg-sky-500']
    ];

    protected $listeners = ['refreshKanban' => '$refresh'];

    public function updateLeadStatus($leadId, $newStatus)
    {
        $lead = WhatsappLead::find($leadId);
        
        // Проверяем статус в основном списке ИЛИ во вложенном списке "thinking"
        $isSubStatus = isset($this->statuses['thinking']['sub']) && array_key_exists($newStatus, $this->statuses['thinking']['sub']);
        $isMainStatus = array_key_exists($newStatus, $this->statuses);

        if ($lead && ($isMainStatus || $isSubStatus)) {
            $lead->update(['status' => $newStatus]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Статус обновлен на: " . ($isSubStatus ? $this->statuses['thinking']['sub'][$newStatus] : $this->statuses[$newStatus]['title'])
            ]);
        }
    }

    public function openChat($id)
    {
        $this->activeLeadIdForChat = $id;
        
        // Помечаем прочитанным
        WhatsappMessage::where('whatsapp_lead_id', $id)
            ->where('is_incoming', true)
            ->update(['is_read' => true]);

        $this->dispatch('open-chat-side-panel');
    }

    // app/Livewire/Admin/KanbanBoard.php

    public function render()
    {
        $leads = \App\Models\WhatsappLead::with(['messages' => function($q) {
                $q->latest()->limit(1);
            }])
            // СОРТИРОВКА ПО ОБНОВЛЕНИЮ: кто последний написал, тот и сверху
            ->orderByDesc('updated_at') 
            ->get()
            ->map(function($lead) {
                $lead->load(['messages' => fn($q) => $q->latest()->limit(1)]);
                $lead->has_new = $lead->messages
                    ->where('is_incoming', true)
                    ->where('is_read', false)
                    ->isNotEmpty();
                return $lead;
            })
            ->groupBy('status');

        return view('livewire.admin.kanban-board', [
            'leadsByStatus' => $leads,
            'statuses' => $this->statuses,
            'totalCount' => \App\Models\WhatsappLead::count()
        ]);
    }
}