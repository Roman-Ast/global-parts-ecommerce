<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WhatsappLead;

class WhatsappMessenger extends Component
{
    public $activeLeadId;
    public $replyText = '';
    public $compactMode = false;

    /** Тот же лимит и тот же принцип, что и в KanbanBoard::LEADS_LIMIT. */
    const LEADS_LIMIT = 200;

    /**
     * Сколько последних сообщений подгружать для ОТКРЫТОГО чата. render()
     * раньше грузил with(['messages' => latest()]) — ВСЮ историю ВСЕХ
     * лидов на каждый рендер (список чатов при этом использовал только
     * messages->first() ради превью — остальное впустую). Теперь список
     * берёт lastMessage (latestOfMany, тот же приём, что уже есть в
     * KanbanBoard), а полная история грузится только для activeLeadId и
     * только последние N сообщений — открытому чату этого достаточно,
     * прокрутка вверх за более старыми пока не реализована (не просили).
     */
    const ACTIVE_CHAT_MESSAGES_LIMIT = 60;

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

        // Синк с Green API — отдельным запросом ПОСЛЕ того, как этот ответ уже
        // ушёл и чат отрисовался, не блокируя переключение чата. См.
        // syncReadOnWhatsApp()/mount() за тем же приёмом через wire:init.
        $this->js('$wire.call("syncReadOnWhatsApp")');
    }

    /**
     * Помечает чат прочитанным на стороне самого WhatsApp (Green API readChat) —
     * без этого вызова галочка "прочитано" у КЛИЕНТА никогда не посинеет: то, что
     * мы отметили is_read=true у себя в БД, видно только в нашем интерфейсе, а не
     * в WhatsApp отправителя. Не критично, если сбой — просто следующий раз, когда
     * чат откроют, синие галочки появятся у клиента с задержкой.
     */
    private function markReadOnWhatsApp(int $leadId): void
    {
        $lead = \App\Models\WhatsappLead::find($leadId);
        if (!$lead) {
            return;
        }

        [$instanceId, $token] = $this->resolveInstanceCreds($lead);

        try {
            // Без явного таймаута брался дефолт Laravel (30 сек) — а Green API
            // временами отвечает по 10-20 сек (видели весь день сегодня). Так
            // как это только пометка "прочитано" у клиента, не критично, если
            // не успеет — короткий таймаут вместо блокировки открытия шторки
            // на полминуты (жалоба Романа 2026-09-14: "шторка очень долго
            // открывается").
            \Illuminate\Support\Facades\Http::timeout(3)->post(
                "https://api.green-api.com/waInstance{$instanceId}/readChat/{$token}",
                ['chatId' => $lead->phone . '@c.us']
            );
        } catch (\Exception $e) {
            \Log::error("Ошибка readChat WhatsApp: " . $e->getMessage());
        }
    }

    /**
     * У лида два возможных источника — 'site' (основной номер) и '2gis'
     * (второй номер, подключён 2026-09-14) — у каждого свой инстанс/токен
     * Green API (config('services.green_api.instances')). Без этого ответ
     * 2ГИС-клиенту ушёл бы с номера сайта либо вообще не тем инстансом.
     * Источник не распознан/не задан — считаем 'site' (старое поведение
     * для лидов, заведённых до второго номера).
     */
    private function resolveInstanceCreds(\App\Models\WhatsappLead $lead): array
    {
        $source = $lead->source ?? 'site';
        $creds = config("services.green_api.instances.{$source}") ?? config('services.green_api.instances.site');

        return [$creds['instance_id'], $creds['token']];
    }

    public function render()
    {
        // 1. Список чатов слева — только превью (lastMessage), не вся история.
        $leads = \App\Models\WhatsappLead::with('lastMessage')
            ->orderBy('last_seen_at', 'desc')
            ->limit(self::LEADS_LIMIT)
            ->get();

        // 2. Полную историю сообщений грузим только для реально открытого
        // чата, последние ACTIVE_CHAT_MESSAGES_LIMIT штук, в хронологическом
        // порядке (блейд рисует сверху вниз и скроллит к последнему).
        $activeLead = null;
        if ($this->activeLeadId) {
            $activeLead = \App\Models\WhatsappLead::find($this->activeLeadId);
            if ($activeLead) {
                $recentMessages = \App\Models\WhatsappMessage::where('whatsapp_lead_id', $activeLead->id)
                    ->latest()
                    ->limit(self::ACTIVE_CHAT_MESSAGES_LIMIT)
                    ->get()
                    ->reverse()
                    ->values();

                $activeLead->setRelation('messages', $recentMessages);
            }
        }

        // 3. ВОЗВРАЩАЕМ ВЬЮХУ МЕССЕНДЖЕРА, а не канбана!
        return view('livewire.admin.whatsapp-messenger', [
            'leads' => $leads,
            'activeLead' => $activeLead,
        ]);
    }

    public function sendMessage()
    {
        // Если чат не выбран или текст пустой — ничего не делаем
        if (!$this->activeLeadId || empty(trim($this->replyText))) {
            return;
        }

        $lead = \App\Models\WhatsappLead::find($this->activeLeadId);

        [$instanceId, $token] = $this->resolveInstanceCreds($lead);

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
                    // Статус дальше обновляет вебхук outgoingMessageStatus
                    // (sent -> delivered -> read), см. WhatsAppWebhookController.
                    'status' => 'sent',
                ]);

                // 2. Обновляем время (update обновит и last_seen_at, и updated_at)
                $lead->update(['last_seen_at' => now()]);
                
                // Если хочешь быть уверен на 100%, можно добавить touch(),
                // но технически update выше это уже сделал.
                $lead->touch();

                \App\Models\CrmActivityLog::log('send_message', $lead->id);

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
            // Сразу помечаем прочитанным у СЕБЯ (быстро, локальная БД). Сам
            // вызов Green API (markReadOnWhatsApp) — НЕ здесь, см.
            // syncReadOnWhatsApp() + wire:init в блейде: mount() не должен
            // ждать внешний HTTP-запрос, иначе именно ИЗ-ЗА него открытие
            // чата ощутимо тормозит (особенно при плохом интернете — жалоба
            // Романа 2026-09-14: "второй чат долго грузится"). Чат должен
            // отрисоваться сразу, синк с WhatsApp — довеском после, незаметно.
            \App\Models\WhatsappMessage::where('whatsapp_lead_id', $activeLeadId)
                ->where('is_incoming', true)
                ->update(['is_read' => true]);
        }
    }

    /** Вызывается через wire:init ПОСЛЕ того, как чат уже отрисован — см. mount(). */
    public function syncReadOnWhatsApp(): void
    {
        if ($this->activeLeadId) {
            $this->markReadOnWhatsApp($this->activeLeadId);
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

            $this->markReadOnWhatsApp($this->activeLeadId);

            // Обновляем сам канбан, чтобы там точка тоже погасла/не загоралась
            $this->dispatch('refreshKanban')->to('admin.kanban-board');
        }
    }
}