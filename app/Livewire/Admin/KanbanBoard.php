<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;

class KanbanBoard extends Component
{
    public $activeLeadIdForChat = null;

    /** Вкладка внутри колонки "Новые" — 'all' | 'unread'. См. render()/блейд. */
    public $newLeadsTab = 'all';

    public function setNewLeadsTab(string $tab): void
    {
        $this->newLeadsTab = in_array($tab, ['all', 'unread'], true) ? $tab : 'all';
    }

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

        // Между "Оплата" и "Продано" — просьба Романа 2026-09-17: раньше
        // клиент, уже заплативший и ждущий доставку ("где мой заказ?"),
        // либо путался с новыми лидами, либо приходилось раньше времени
        // считать сделку закрытой. Отдельная стадия — сюда попадают именно
        // такие сообщения, "Продано" остаётся только для реально полученных
        // заказов.
        'bought_waiting' => ['title' => 'Купил и ждёт', 'color' => 'bg-teal-500'],

        'deal_closed'   => ['title' => 'Продано (выдано)', 'color' => 'bg-sky-500'],

        // "Сделка закрыта" раньше смешивала "продали" и "не купили" в одном
        // статусе — по просьбе Романа 2026-09-17 разделено на два разных
        // терминальных исхода: 'deal_closed' теперь строго "продали", а это
        // группа — "не купили", финал без дальнейшей работы (в отличие от
        // 'thinking', где ещё можно дожимать). Подпричины намеренно взяты
        // теми же ключами/формулировками, что и закрытая таксономия
        // `demand_signals.decline_reason` (миграция
        // 2026_09_14_000004_add_decline_reason_to_demand_signals_table) —
        // Роман сам вывел её из реальных чатов, здесь просто тот же словарь,
        // без параллельной таксономии. 'no_stock_wont_wait' — ключевой сигнал
        // по складу ("купил бы прямо сейчас, была бы деталь в наличии").
        'lost'      => [
            'title' => 'Не купили',
            'color' => 'bg-rose-600',
            'sub' => [
                'no_stock_wont_wait'      => 'Нет в наличии',
                'in_stock_too_expensive'  => 'Дорого',
                'part_not_found'          => 'Не нашли деталь',
                'changed_mind'            => 'Передумал',
            ],
        ],
    ];

    protected $listeners = ['refreshKanban' => '$refresh'];

    public function updateLeadStatus($leadId, $newStatus)
    {
        $lead = WhatsappLead::find($leadId);

        $isMainStatus = array_key_exists($newStatus, $this->statuses);

        // Sub-статус может принадлежать ЛЮБОЙ группе с 'sub' (сейчас —
        // 'thinking' и 'lost'), не только 'thinking' — раньше было жёстко
        // зашито под одну группу.
        $subLabel = null;
        foreach ($this->statuses as $group) {
            if (isset($group['sub']) && array_key_exists($newStatus, $group['sub'])) {
                $subLabel = $group['sub'][$newStatus];
                break;
            }
        }

        if ($lead && ($isMainStatus || $subLabel !== null)) {
            $lead->update(['status' => $newStatus]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Статус обновлен на: " . ($subLabel ?? $this->statuses[$newStatus]['title'])
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
            'totalCount' => \App\Models\WhatsappLead::count()
        ]);
    }
}