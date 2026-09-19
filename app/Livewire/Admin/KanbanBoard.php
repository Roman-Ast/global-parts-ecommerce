<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use App\Models\CrmActivityLog;
use App\Support\LeadStatuses;

class KanbanBoard extends Component
{
    public $activeLeadIdForChat = null;

    /** Вкладка внутри колонки "Новые" — 'all' | 'unread'. См. render()/блейд. */
    public $newLeadsTab = 'all';

    /**
     * Лог активности (просьба Романа 2026-09-18, см. докблок CrmActivityLog) —
     * mount() выполняется ОДИН раз за загрузку страницы (в отличие от render(),
     * который дёргается каждые 3с через wire:poll), поэтому именно здесь, а не
     * в render(), фиксируем "открыл доску" — иначе лог был бы забит записями
     * каждые 3 секунды впустую.
     */
    public function mount(): void
    {
        CrmActivityLog::log('view_board');
        $this->statuses = LeadStatuses::all();
    }

    public function setNewLeadsTab(string $tab): void
    {
        $this->newLeadsTab = in_array($tab, ['all', 'unread'], true) ? $tab : 'all';
    }

    // Сама воронка теперь единым источником в LeadStatuses (просьба
    // Романа 2026-09-19 — смена статуса появилась ещё и в окне чата
    // (WhatsappMessenger), дублировать список статусов в двух местах
    // означало бы держать их синхронными вручную). Заполняется в mount().
    public $statuses = [];

    /**
     * "Спам" (просьба Романа 2026-09-17) — НЕ стадия воронки продаж,
     * поэтому сознательно не в $statuses: там нет своей колонки/карточек,
     * только счётчик-"корзина" сверху доски и Sortable drop-зона на него.
     * Просматривать содержимое будем изредка напрямую SQL-запросом, без
     * отдельной вьюхи — если понадобится UI, добавлять отдельно.
     */
    const SPAM_STATUS = LeadStatuses::SPAM_STATUS;

    protected $listeners = ['refreshKanban' => '$refresh'];

    public function updateLeadStatus($leadId, $newStatus)
    {
        $label = LeadStatuses::update((int) $leadId, (string) $newStatus);

        if ($label !== null) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Статус обновлен на: {$label}"
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

        CrmActivityLog::log('open_chat', $id);

        $this->dispatch('open-chat-side-panel');
    }

    // app/Livewire/Admin/KanbanBoard.php

    /**
     * Верхняя граница рабочей выборки — не по дате (Роман 2026-09-17: лид,
     * молчавший 2 месяца, может написать сегодня и должен появиться), а по
     * количеству самых свежих по `updated_at`. Написавший лид сразу
     * поднимается наверх и попадает в лимит независимо от даты последнего
     * сообщения — старые невостребованные лиды просто вытесняются из
     * рабочей выборки, но остаются в БД (доска — не единственный доступ к
     * данным). Без лимита `render()` тянул ВСЕХ лидов на каждый
     * wire:poll.3s — с ростом базы это и есть тот рост нагрузки, о котором
     * предупреждал Роман.
     */
    const LEADS_LIMIT = 200;

    public function render()
    {
        // lastMessage (latestOfMany) + withCount вместо with(['messages' =>
        // limit(1)]) + ->load() на каждом лиде внутри map() — старая версия
        // была одновременно и некорректной (limit() в eager-load closure
        // ограничивает общий запрос, не "по одному на лида" — реально
        // возвращалось одно сообщение на всю пачку), и N+1 (тот ->load()
        // был обходным путём под это, отдельный запрос на КАЖДОГО лида).
        // При wire:poll.3s это был лишний удар по БД каждые 3 секунды.
        $leads = \App\Models\WhatsappLead::with('lastMessage')
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_incoming', true)->where('is_read', false);
            }])
            // Спам не часть воронки — не тратим LEADS_LIMIT-окно на них здесь,
            // они и так никогда не рендерятся (нет колонки в $statuses).
            ->where('status', '!=', self::SPAM_STATUS)
            // СОРТИРОВКА ПО ОБНОВЛЕНИЮ: кто последний написал, тот и сверху
            ->orderByDesc('updated_at')
            ->limit(self::LEADS_LIMIT)
            ->get()
            ->map(function($lead) {
                $lead->has_new = $lead->unread_count > 0;
                return $lead;
            })
            ->groupBy('status');

        return view('livewire.admin.kanban-board', [
            'leadsByStatus' => $leads,
            'statuses' => $this->statuses,
            'totalCount' => \App\Models\WhatsappLead::count(),
            'spamCount' => \App\Models\WhatsappLead::where('status', self::SPAM_STATUS)->count(),
        ]);
    }
}