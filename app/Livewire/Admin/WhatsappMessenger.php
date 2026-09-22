<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\WhatsappLead;
use App\Support\LeadStatuses;

class WhatsappMessenger extends Component
{
    use WithFileUploads;

    public $activeLeadId;
    public $replyText = '';
    public $compactMode = false;

    /** Отслеживание для автоскролла — см. render(). */
    public $lastRenderedMessageId = null;

    /**
     * Вставка картинки из буфера обмена прямо в чат (Ctrl+V, просьба Романа
     * 2026-09-18) — временный Livewire-аплоад, не постоянное свойство формы.
     * JS-обработчик paste на textarea (см. блейд) вызывает $wire.upload(...)
     * на это имя, затем sendPastedImage() ниже.
     */
    public $pastedImage = null;

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

    /**
     * Смена статуса лида прямо из открытого чата (просьба Романа
     * 2026-09-19) — тот же ленивый выпадающий список, что и на карточках
     * канбана (partials/status-select.blade.php), и тот же общий
     * LeadStatuses::update(), что и в KanbanBoard::updateLeadStatus() —
     * не дублируем ни список статусов, ни логику применения. refreshKanban
     * дальше — чтобы карточка сразу уехала в новую колонку на доске за
     * шторкой, не дожидаясь её собственного wire:poll.3s.
     */
    public function updateLeadStatus($leadId, $newStatus)
    {
        LeadStatuses::update((int) $leadId, (string) $newStatus);
        $this->dispatch('refreshKanban')->to('admin.kanban-board');
    }

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

    /** См. computeFingerprint()/pollTick() — тот же приём, что и в KanbanBoard
     *  (просьба Романа 2026-09-22, "дешёвые варианты без сокета"). */
    public $lastFingerprint = '';

    /**
     * Дешёвый слепок состояния мессенджера — та же идея, что и в
     * KanbanBoard::computeFingerprint(): 2 лёгких запроса вместо полного
     * render() (список из 200 лидов + до 60 сообщений открытого чата +
     * Blade-рендер) на каждый тик, когда за 5 секунд ничего не изменилось.
     *
     * `MAX(id)` по whatsapp_messages (не COUNT/MAX(updated_at) — у таблицы
     * нет собственного индекса на updated_at, только составные с
     * whatsapp_lead_id первым столбцом, см. индексы) — дёшево независимо
     * от размера таблицы (первичный ключ). Ловит НОВЫЕ сообщения (основной
     * случай); НЕ ловит чисто статусные обновления существующей записи
     * (галочки sent→delivered→read у уже отрисованного сообщения без
     * нового сообщения рядом) — приемлемый компромисс, статус галочки
     * подтянется при следующем реальном изменении.
     */
    private function computeFingerprint(): string
    {
        $leadsAgg = WhatsappLead::selectRaw('COUNT(*) as cnt, MAX(last_seen_at) as max_seen')->first();
        $maxMessageId = (int) \App\Models\WhatsappMessage::max('id');

        return $leadsAgg->cnt . '|' . $leadsAgg->max_seen . '|' . $maxMessageId;
    }

    /**
     * Цель wire:poll.5s.visible (не голый $refresh) — см. computeFingerprint().
     * Прямые действия (selectLead/sendMessage/updateLeadStatus/...) сюда НЕ
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

                // Автоскролл к низу чата (просьба Романа 2026-09-21) — раньше
                // scroll-chat-to-bottom дёргался ТОЛЬКО когда Роман сам
                // отправлял сообщение (sendTextMessage). Новые ВХОДЯЩИЕ от
                // клиента прилетают через webhook в БД напрямую, эта
                // Livewire-компонента узнаёт о них только на следующем тике
                // wire:poll.5s — список сообщений дорисовывался внизу, но без
                // скролла, поэтому если Роман не был прокручен ровно в
                // конец (а после того как написал ответ и остался читать —
                // обычно не был), новое сообщение клиента оставалось за
                // пределами видимой области. Сравниваем id самого свежего
                // сообщения с тем, что видели на прошлом тике — при любом
                // изменении (новое сообщение, смена активного чата) шлём
                // скролл; повторный скролл на уже отправленное самим Романом
                // сообщение (там scroll-chat-to-bottom и так уже дёрнут явно)
                // просто безвредно сработает ещё раз.
                $newestMessageId = $recentMessages->max('id');
                if ($newestMessageId !== null && $newestMessageId !== $this->lastRenderedMessageId) {
                    $this->dispatch('scroll-chat-to-bottom');
                }
                $this->lastRenderedMessageId = $newestMessageId;
            }
        } else {
            $this->lastRenderedMessageId = null;
        }

        // См. computeFingerprint() — держим слепок свежим и после прямых
        // действий (не только после pollTick), чтобы следующий тик опроса
        // сравнивал с актуальным состоянием, а не устаревшим.
        $this->lastFingerprint = $this->computeFingerprint();

        // 3. ВОЗВРАЩАЕМ ВЬЮХУ МЕССЕНДЖЕРА, а не канбана!
        return view('livewire.admin.whatsapp-messenger', [
            'leads' => $leads,
            'activeLead' => $activeLead,
            'statuses' => LeadStatuses::all(),
            'quickReplies' => self::QUICK_REPLIES,
        ]);
    }

    public function sendMessage()
    {
        // Если чат не выбран или текст пустой — ничего не делаем
        if (!$this->activeLeadId || empty(trim($this->replyText))) {
            return;
        }

        if ($this->sendTextMessage($this->replyText)) {
            $this->replyText = '';
        }
    }

    /**
     * Быстрые ответы (просьба Романа 2026-09-21) — фиксированный набор
     * шаблонных сообщений (запрос техпаспорта/VIN, срок самовывоза, адрес
     * с 2GIS-ссылкой), которые он печатает вручную по многу раз на дню.
     * Ключ — просто индекс в QUICK_REPLIES, значение — короткая подпись
     * для чекбокса в блейде.
     */
    const QUICK_REPLIES = [
        [
            'label' => 'Тех. паспорт / VIN',
            'text'  => 'Здравствуйте, фото техпаспорта или винкод авто отправьте',
        ],
        [
            'label' => 'Забрать через 2 часа',
            'text'  => 'Если сейчас заказ оформим, забрать можно будет через 2 часа Целинный 5/1, ТД "Акку"',
        ],
        [
            'label' => 'Адрес',
            'text'  => "Наш адрес 🏘️\nг. Астана, мкрн Целинный 5/1, ТД Акку, главный вход, 2 этаж.\nhttps://go.2gis.com/N23Xb",
        ],
        [
            'label' => 'Заказ оформлен',
            // *текст* — базовая разметка WhatsApp (жирный), поддерживается
            // клиентом получателя нативно, без каких-либо доп. настроек с
            // нашей стороны.
            'text'  => "✅ *Ваш заказ оформлен!*\nКак только он поступит — сразу сообщим вам.\n\nСпасибо, что выбрали Global Parts! 🙏",
        ],
    ];

    /** Отмеченные чекбоксы в блоке быстрых ответов — индексы QUICK_REPLIES. */
    public $selectedQuickReplies = [];

    /**
     * Отправляет каждое отмеченное быстрое сообщение ОТДЕЛЬНОЙ строкой в
     * чат (не склеивая в одно) — так оно и в WhatsApp у получателя выглядит
     * как обычная последовательность реплик, а не один длинный блок текста.
     */
    public function sendQuickReplies()
    {
        if (!$this->activeLeadId || empty($this->selectedQuickReplies)) {
            return;
        }

        foreach ($this->selectedQuickReplies as $index) {
            if (!isset(self::QUICK_REPLIES[$index])) {
                continue;
            }
            $this->sendTextMessage(self::QUICK_REPLIES[$index]['text']);
        }

        $this->selectedQuickReplies = [];
    }

    /**
     * Общая часть sendMessage()/sendQuickReplies() — раньше вся эта логика
     * жила только внутри sendMessage() и читала $this->replyText напрямую,
     * пришлось вынести в отдельный метод с параметром, чтобы не дублировать
     * Green API вызов + сохранение + активность/канбан-рефреш для быстрых
     * ответов. Возвращает true, если сообщение реально ушло.
     */
    private function sendTextMessage(string $text): bool
    {
        $lead = \App\Models\WhatsappLead::find($this->activeLeadId);
        if (!$lead) {
            return false;
        }

        [$instanceId, $token] = $this->resolveInstanceCreds($lead);

        $url = "https://api.green-api.com/waInstance{$instanceId}/sendMessage/{$token}";

        try {
            $response = \Illuminate\Support\Facades\Http::post($url, [
                'chatId' => $lead->phone . '@c.us',
                'message' => $text,
            ]);

            if ($response->successful()) {
                // 1. Сохраняем сообщение
                $lead->messages()->create([
                    'instance_id' => $instanceId,
                    'message_text' => $text,
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

                $this->dispatch('refreshKanban')->to('admin.kanban-board');

                return true;
            }
        } catch (\Exception $e) {
            \Log::error("Ошибка отправки WhatsApp: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Превращает голые URL в тексте сообщения в кликабельные ссылки
     * (просьба Романа 2026-09-21, для быстрого ответа "Адрес" с 2GIS-
     * ссылкой) — экранируем ВЕСЬ текст сначала (защита от XSS из
     * содержимого сообщения), потом заменяем уже экранированные URL на
     * <a>. WhatsApp у получателя и так сам линкует URL в сырых
     * сообщениях — это только для отображения в НАШЕЙ панели, там текст
     * рендерился как чистый escaped-текст без единой ссылки.
     */
    public function linkify(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $escaped = e($text);

        return preg_replace(
            '/(https?:\/\/[^\s<]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="underline text-blue-600 hover:text-blue-800 break-all">$1</a>',
            $escaped
        );
    }

    /**
     * Отправка картинки, вставленной из буфера обмена (Ctrl+V) — см. paste-
     * обработчик на textarea в блейде, который зовёт $wire.upload('pastedImage', ...)
     * и по завершении вызывает этот метод. Файл сохраняется на public-диск
     * (постоянно, не temp) ради стабильного URL: и для показа в НАШЕМ чате
     * (file_url у обычных imageMessage и так уже ссылка), и потому что Green
     * API `sendFileByUrl` сам СКАЧИВАЕТ файл по этому URL — значит адрес
     * должен быть реально доступен из интернета, а не только локально
     * (поэтому у себя на локалке отправка реально в WhatsApp не дойдёт —
     * протестировать можно только на проде, см. CLAUDE.md).
     *
     * ВАЖНО (найдено 2026-09-21, живая жалоба "фото битые отправляются") —
     * рабочего APP_URL и правильного пути тут недостаточно: если на сервере
     * не выполнен `php artisan storage:link`, `public/storage` не существует
     * физически, и построенная ссылка (синтаксически верная, APP_URL+путь)
     * 404-ит. Green API получает не картинку, а страницу ошибки — на
     * стороне клиента это выглядит как битое фото. На проде этот симлинк
     * не был создан вообще ни разу с момента деплоя CRM — `storage:link`
     * прогнан вручную через Plesk, фикс подтверждён живьём.
     */
    public function sendPastedImage()
    {
        if (!$this->activeLeadId || !$this->pastedImage) {
            return;
        }

        $lead = WhatsappLead::find($this->activeLeadId);
        [$instanceId, $token] = $this->resolveInstanceCreds($lead);

        try {
            $path = $this->pastedImage->store('whatsapp-outgoing', 'public');
            $publicUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

            $response = \Illuminate\Support\Facades\Http::post(
                "https://api.green-api.com/waInstance{$instanceId}/sendFileByUrl/{$token}",
                [
                    'chatId' => $lead->phone . '@c.us',
                    'urlFile' => $publicUrl,
                    'fileName' => basename($path),
                ]
            );

            if ($response->successful()) {
                $lead->messages()->create([
                    'instance_id' => $instanceId,
                    'message_text' => 'Изображение',
                    'file_url' => $publicUrl,
                    'is_incoming' => false,
                    'is_read' => true,
                    'message_id' => $response->json()['idMessage'] ?? uniqid(),
                    'type' => 'imageMessage',
                    'status' => 'sent',
                ]);

                $lead->update(['last_seen_at' => now()]);
                $lead->touch();

                \App\Models\CrmActivityLog::log('send_message', $lead->id);

                $this->dispatch('scroll-chat-to-bottom');
                $this->dispatch('refreshKanban')->to('admin.kanban-board');
            } else {
                \Log::error('Ошибка отправки картинки WhatsApp: ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Ошибка отправки картинки WhatsApp: ' . $e->getMessage());
        }

        $this->pastedImage = null;
    }

    // Добавь этот метод mount
    public function mount($activeLeadId = null, $compactMode = false)
    {
        $this->compactMode = $compactMode;
        $this->lastFingerprint = $this->computeFingerprint();

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