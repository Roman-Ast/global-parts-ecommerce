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
     * "Долгоиграющие" (просьба Романа 2026-09-26) — клиент реально ждёт
     * чего-то СВОЕГО (например "как выплата по страховой поступит,
     * напишу вам"), не возражение и не отказ. Долбить каждые 3 часа как
     * обычных "в работе" — бессмысленно и раздражает, но и забывать
     * совсем нельзя — через неделю-две стоит написать самому. Отдельный
     * статус/колонка + свой, гораздо более длинный порог в
     * REMINDER_THRESHOLDS_HOURS ниже — та же "Пора напомнить"
     * (needsReminder()), просто с другим графиком, не отдельный
     * механизм.
     */
    const LONG_TERM_STATUS = 'long_term';

    /**
     * Напоминание менеджеру "пора написать клиенту" (просьба Романа
     * 2026-09-21, расширено 2026-09-26 под LONG_TERM_STATUS) — статус
     * лида => через сколько часов тишины после НАШЕГО последнего
     * сообщения загорается "Пора напомнить". КП отправлено + все
     * подпричины "Работы с возражениями" + "Оплата" — 3 часа (лид
     * реально в работе, ждём решения прямо сейчас). "Долгоиграющие" —
     * 240 часов (10 дней, середина озвученных Романом "неделя-две") —
     * тут спешить некуда, а частые напоминания только раздражают клиента,
     * который и так сказал, когда сам объявится.
     */
    const REMINDER_THRESHOLDS_HOURS = [
        'offer'     => 3,
        'silent'    => 3,
        'expensive' => 3,
        'wait'      => 3,
        'pending'   => 3,
        'payment'   => 3,
        self::LONG_TERM_STATUS => 240,
    ];

    /**
     * Плоский список статусов, участвующих в "Пора напомнить" — раньше
     * был отдельной константой (REMINDER_ELIGIBLE_STATUSES), но тогда
     * пришлось бы держать ДВА списка синхронными (этот + пороги) при
     * каждом добавлении нового долгоиграющего статуса. Один источник
     * правды — ключи REMINDER_THRESHOLDS_HOURS.
     */
    public static function reminderEligibleStatuses(): array
    {
        return array_keys(self::REMINDER_THRESHOLDS_HOURS);
    }

    /**
     * Подсветка "залежался" в стиле "Не отвечает" ("Долгоиграющие" — та
     * же жёлтая подсветка "Пора напомнить", как только придёт время, но
     * порог свой, см. REMINDER_THRESHOLDS_HOURS).
     *
     * Нужно ли напомнить менеджеру про этого лида — требует, чтобы
     * `lastMessage` (latestOfMany) уже была подгружена заранее (не делает
     * отдельный запрос сама, вызывается на пачке лидов в KanbanBoard::render(),
     * где эта связь и так уже нужна для превью последнего сообщения).
     */
    public static function needsReminder(WhatsappLead $lead): bool
    {
        if (!isset(self::REMINDER_THRESHOLDS_HOURS[$lead->status])) {
            return false;
        }

        $lastMessage = $lead->lastMessage;
        if (!$lastMessage || $lastMessage->is_incoming) {
            // Нет сообщений вообще, либо последнее — от клиента (тогда это
            // не "ждём клиента", а "клиент ждёт нас", другая история —
            // уже покрыта has_new/звуком уведомлений).
            return false;
        }

        return $lastMessage->created_at->diffInHours(now()) >= self::REMINDER_THRESHOLDS_HOURS[$lead->status];
    }

    /**
     * Плоский список реальных статусов, по которым в БД можно фильтровать
     * WHERE status = ... — то есть все ключи all() КРОМЕ групп-контейнеров
     * с 'sub' (у 'thinking'/'lost' самого по себе такого статуса в БД
     * никогда не бывает, только у их подпричин). Нужен KanbanBoard::render()
     * для per-column лимитов (просьба Романа 2026-09-23, "5*17 с запасом,
     * остальное лэйзилоадинг") — раньше был один общий запрос на всю
     * доску, теперь по одному узкому запросу на каждый реальный статус.
     */
    public static function leafStatuses(): array
    {
        $leaf = [];
        foreach (self::all() as $key => $info) {
            if (isset($info['sub'])) {
                $leaf = array_merge($leaf, array_keys($info['sub']));
            } else {
                $leaf[] = $key;
            }
        }
        return $leaf;
    }

    public static function all(): array
    {
        return [
            'new'       => ['title' => 'Новые', 'color' => 'bg-blue-500'],
            self::STAFF_STATUS => ['title' => 'Рабочие', 'color' => 'bg-gray-500'],
            'selection' => ['title' => 'Подбор', 'color' => 'bg-yellow-500'],
            'offer'     => ['title' => 'КП Отправлено', 'color' => 'bg-indigo-500'],

            self::LONG_TERM_STATUS => ['title' => 'Долгоиграющие', 'color' => 'bg-cyan-600'],

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
                    // Просьба Романа 2026-09-22 — отдельно от "Молчит" у
                    // "Работа с возражениями" (там лид ещё в работе, ждём
                    // ответа), сюда — те, кого он считает потерянными
                    // именно из-за игнора сообщений, не по другой причине.
                    'no_response'             => 'Не отвечает',
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
