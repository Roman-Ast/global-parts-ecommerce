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

        // Стартовое значение для звука уведомлений (см. render()) — если
        // на момент открытия доски уже были непрочитанные, звук на НИХ
        // играть не нужно (это не "только что пришло"), только на то, что
        // придёт ПОСЛЕ открытия. Тот же фильтр по статусу, что и в
        // render(), чтобы база и последующие сравнения считались одинаково.
        $this->lastTotalUnread = $this->currentTotalUnread();

        $this->lastFingerprint = $this->computeFingerprint();
    }

    /**
     * Дешёвый "слепок" состояния доски (просьба Романа 2026-09-22 —
     * "дешёвые варианты без сокета") — 2 лёгких запроса (COUNT+MAX и
     * EXISTS) вместо полного render() (до 200 лидов + lastMessage +
     * withCount + Blade-рендер ~200 карточек). pollTick() сравнивает его
     * с предыдущим значением и, если ничего не изменилось (обычный
     * случай — новые сообщения приходят не каждые 5 секунд), вызывает
     * skipRender() ДО того, как Livewire вообще позовёт render() —
     * экономится не только HTML-ответ, но и сам тяжёлый запрос.
     *
     * "Пора напомнить" — отдельная забота: карточка может стать
     * подходящей под напоминание чисто от течения времени, без единого
     * изменения в БД (см. LeadStatuses::needsReminder() — порог в часах
     * от последнего НАШЕГО сообщения). COUNT/MAX по лидам такое не
     * поймает. Грубое решение — 5-минутные окна времени подмешиваются в
     * слепок, но ТОЛЬКО если вообще есть лиды в подходящих статусах
     * (иначе слепок держится по времени вхолостую, когда нечему
     * подсвечиваться). Худший случай — подсветка "Пора напомнить"
     * опаздывает до 5 минут вместо 5 секунд, не бесконечно.
     */
    private function computeFingerprint(): string
    {
        $agg = WhatsappLead::where('status', '!=', self::SPAM_STATUS)
            ->selectRaw('COUNT(*) as cnt, MAX(updated_at) as max_updated')
            ->first();

        $hasReminderEligible = WhatsappLead::whereIn('status', LeadStatuses::REMINDER_ELIGIBLE_STATUSES)->exists();
        $timeBucket = $hasReminderEligible ? intdiv(now()->timestamp, 300) : 0;

        return $agg->cnt . '|' . $agg->max_updated . '|' . $timeBucket;
    }

    /** См. computeFingerprint(). Обновляется и здесь, и в конце render() —
     *  на случай, если полный рендер случился не через pollTick (прямое
     *  действие вроде updateLeadStatus/openChat), слепок всё равно должен
     *  остаться свежим к следующему тику опроса. */
    public $lastFingerprint = '';

    /**
     * Цель wire:poll.5s.visible (не голый $refresh) — см. computeFingerprint().
     * Прямые действия (updateLeadStatus/openChat/setNewLeadsTab) сюда НЕ
     * заходят — у них всегда полный рендер сразу, без этой проверки.
     */
    public function pollTick(): void
    {
        $fingerprint = $this->computeFingerprint();

        if ($fingerprint === $this->lastFingerprint) {
            $this->skipRender();
            return;
        }

        $this->lastFingerprint = $fingerprint;
    }

    private function currentTotalUnread(): int
    {
        return (int) WhatsappLead::where('status', '!=', self::SPAM_STATUS)
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_incoming', true)->where('is_read', false);
            }])
            ->get()
            ->sum('unread_count');
    }

    public function setNewLeadsTab(string $tab): void
    {
        $this->newLeadsTab = in_array($tab, ['all', 'unread'], true) ? $tab : 'all';
    }

    /**
     * Звук уведомления (просьба Романа 2026-09-21, "как в WhatsApp, когда
     * приходят любые сообщения с любой карточки") — сравниваем суммарное
     * число непрочитанных на каждом render() (он же и есть wire:poll.3s)
     * с предыдущим значением; если выросло — играем звук. Растёт именно
     * СУММА, не привязка к конкретному лиду, поэтому не важно, с какой
     * карточки пришло. Намеренно НЕ пересчитываем звук на уменьшение
     * (прочитали чат) — там playSound не дёргаем вообще.
     */
    public $lastTotalUnread = 0;

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
                // "Пора напомнить" (просьба Романа 2026-09-21) — считаем
                // здесь же, а не отдельным запросом: lastMessage уже
                // подгружена выше ради превью, needsReminder() её просто
                // переиспользует.
                $lead->needs_reminder = LeadStatuses::needsReminder($lead);
                return $lead;
            });

        $currentTotalUnread = $leads->sum('unread_count');
        if ($currentTotalUnread > $this->lastTotalUnread) {
            $this->dispatch('play-notification-sound');
        }
        $this->lastTotalUnread = $currentTotalUnread;

        // "Пора напомнить" — виртуальная колонка (просьба Романа
        // 2026-09-21): НЕ отдельный статус в БД, просто карточки из
        // $leads (уже отфильтрованных/загруженных выше), у которых
        // needs_reminder=true, собранные в один список поперёк реальных
        // статусов (КП отправлено + все подпричины "Работы с
        // возражениями"). Перетащить карточку МОЖНО отсюда (это меняет
        // настоящий status лида как обычно через updateLeadStatus) — но
        // НЕЛЬЗЯ затащить сюда руками, см. data-no-drop в блейде/JS.
        // Те же объекты моделей, что и в $leadsByStatus — не дублируем
        // данные, только ссылки на них в другом списке.
        $remindersDue = $leads->filter(fn ($lead) => $lead->needs_reminder)->values();

        $leads = $leads->groupBy('status');

        // См. computeFingerprint() — держим слепок свежим и после прямых
        // действий (не только после pollTick), чтобы следующий тик опроса
        // сравнивал с актуальным состоянием, а не устаревшим.
        $this->lastFingerprint = $this->computeFingerprint();

        return view('livewire.admin.kanban-board', [
            'leadsByStatus' => $leads,
            'remindersDue' => $remindersDue,
            'statuses' => $this->statuses,
            'totalCount' => \App\Models\WhatsappLead::count(),
            'spamCount' => \App\Models\WhatsappLead::where('status', self::SPAM_STATUS)->count(),
        ]);
    }
}