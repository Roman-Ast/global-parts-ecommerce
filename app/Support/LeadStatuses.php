<?php

namespace App\Support;

use App\Models\CrmActivityLog;
use App\Models\WhatsappLead;

/**
 * Единый источник правды для воронки статусов CRM. Раньше весь список
 * статусов и логика их применения жили только в KanbanBoard — при
 * добавлении смены статуса прямо из окна чата (WhatsappMessenger, просьба
 * Романа 2026-09-19) пришлось бы либо дублировать этот список в двух
 * местах (риск разъехаться при следующем добавлении статуса/подпричины),
 * либо вынести сюда один раз. KanbanBoard и WhatsappMessenger теперь оба
 * дёргают update() отсюда.
 */
class LeadStatuses
{
    const SPAM_STATUS = 'spam';

    /**
     * "Рабочие" (просьба Романа 2026-09-19) — не стадия продаж, а бак для
     * своих/коллег/поставщиков, попавших в лиды по ошибке (напр. личные
     * номера Романа/коллег, группа "Темщики" — раньше эти контакты жёстко
     * исключались ДО появления лида константой EXCLUDED_PHONES/
     * EXCLUDED_GROUP_NAMES в WhatsAppWebhookController, теперь заводятся
     * лидами как обычно, а сюда Роман переносит их вручную сам, когда
     * видит, что это не клиент). В отличие от SPAM_STATUS — это НАСТОЯЩАЯ
     * колонка на доске (see all(), сразу после 'new'), не просто
     * счётчик-корзина: Роман хочет видеть, кто туда попал. Специальное
     * поведение — WhatsAppWebhookController перестаёт сохранять НОВЫЕ
     * сообщения по номеру лида в этом статусе (см. докблок там же); уже
     * сохранённая история не трогается.
     */
    const STAFF_STATUS = 'staff';

    /**
     * Напоминание менеджеру "пора написать клиенту" (просьба Романа
     * 2026-09-21) — статусы, где лид реально "ждёт" ответа клиента после
     * нашего последнего сообщения: КП отправлено и все четыре подпричины
     * "Работы с возражениями" (молчит/дорого/сроки/думает — все по сути
     * тот же смысл "ждём решения клиента"). Намеренно НЕ включает 'payment'
     * ("Оплата") — это отдельный вопрос, пока не подтверждено Романом.
     */
    const REMINDER_ELIGIBLE_STATUSES = ['offer', 'silent', 'expensive', 'wait', 'pending'];

    /**
     * Порог тишины (часов с НАШЕГО последнего сообщения, если клиент с тех
     * пор не ответил), после которого карточка подсвечивается жёлтым —
     * по прямому подтверждению Романа 2026-09-21 это НЕ лесенка с разными
     * интервалами между повторными напоминаниями (обсуждали, отклонено как
     * усложнение без реальной практической разницы): подсветка загорается
     * один раз и горит непрерывно, пока менеджер не напишет снова (это
     * само обнулит таймер — последнее сообщение снова станет "нашим, но
     * свежим") или не переведёт карточку в другой статус.
     */
    const REMINDER_THRESHOLD_HOURS = 3;

    /**
     * Нужно ли напомнить менеджеру про этого лида — требует, чтобы
     * `lastMessage` (latestOfMany) уже была подгружена заранее (не делает
     * отдельный запрос сама, вызывается на пачке лидов в KanbanBoard::render(),
     * где эта связь и так уже нужна для превью последнего сообщения).
     */
    public static function needsReminder(WhatsappLead $lead): bool
    {
        if (!in_array($lead->status, self::REMINDER_ELIGIBLE_STATUSES, true)) {
            return false;
        }

        $lastMessage = $lead->lastMessage;
        if (!$lastMessage || $lastMessage->is_incoming) {
            // Нет сообщений вообще, либо последнее — от клиента (тогда это
            // не "ждём клиента", а "клиент ждёт нас", другая история —
            // уже покрыта has_new/звуком уведомлений).
            return false;
        }

        return $lastMessage->created_at->diffInHours(now()) >= self::REMINDER_THRESHOLD_HOURS;
    }

    public static function all(): array
    {
        return [
            'new'       => ['title' => 'Новые', 'color' => 'bg-blue-500'],
            self::STAFF_STATUS => ['title' => 'Рабочие', 'color' => 'bg-gray-500'],
            'selection' => ['title' => 'Подбор', 'color' => 'bg-yellow-500'],
            'offer'     => ['title' => 'КП Отправлено', 'color' => 'bg-indigo-500'],

            'thinking'  => [
                'title' => 'Работа с возражениями',
                'color' => 'bg-slate-700',
                'sub' => [
                    'silent'    => 'Молчит',
                    'expensive' => 'Дорого',
                    'wait'      => 'Сроки',
                    'pending'   => 'Думает',
                ],
            ],

            'payment'   => ['title' => 'Оплата', 'color' => 'bg-green-500'],

            'bought_waiting' => ['title' => 'Купил и ждёт', 'color' => 'bg-teal-500'],

            'deal_closed'   => ['title' => 'Продано (выдано)', 'color' => 'bg-sky-500'],

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
    }

    /**
     * Обновляет статус лида и пишет в лог активности. Возвращает
     * человекочитаемый ярлык нового статуса (для тоста у вызывающей
     * стороны) или null, если лид не найден либо $newStatus невалиден.
     */
    public static function update(int $leadId, string $newStatus): ?string
    {
        $lead = WhatsappLead::find($leadId);
        if (!$lead) {
            return null;
        }

        $label = self::labelFor($newStatus);
        if ($label === null) {
            return null;
        }

        $oldStatus = $lead->status;
        $lead->update(['status' => $newStatus]);
        CrmActivityLog::log('update_status', $lead->id, ['from' => $oldStatus, 'to' => $newStatus]);

        return $label;
    }

    /**
     * Человекочитаемый ярлык статуса — и для тоста после update(), и для
     * подписи на самой кнопке дропдауна (просьба Романа 2026-09-19: кнопка
     * должна показывать ТЕКУЩИЙ статус лида, не статичную надпись "Сменить
     * статус"). Возвращает null для незнакомого/пустого ключа.
     */
    public static function labelFor(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        if ($status === self::SPAM_STATUS) {
            return 'Спам';
        }

        $statuses = self::all();

        if (array_key_exists($status, $statuses)) {
            return $statuses[$status]['title'];
        }

        foreach ($statuses as $group) {
            if (isset($group['sub']) && array_key_exists($status, $group['sub'])) {
                return $group['sub'][$status];
            }
        }

        return null;
    }
}
