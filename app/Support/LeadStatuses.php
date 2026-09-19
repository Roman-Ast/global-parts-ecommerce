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

    public static function all(): array
    {
        return [
            'new'       => ['title' => 'Новые', 'color' => 'bg-blue-500'],
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

        $statuses = self::all();
        $isMainStatus = array_key_exists($newStatus, $statuses) || $newStatus === self::SPAM_STATUS;

        $subLabel = null;
        foreach ($statuses as $group) {
            if (isset($group['sub']) && array_key_exists($newStatus, $group['sub'])) {
                $subLabel = $group['sub'][$newStatus];
                break;
            }
        }

        if (!$isMainStatus && $subLabel === null) {
            return null;
        }

        $oldStatus = $lead->status;
        $lead->update(['status' => $newStatus]);
        CrmActivityLog::log('update_status', $lead->id, ['from' => $oldStatus, 'to' => $newStatus]);

        return $subLabel ?? ($newStatus === self::SPAM_STATUS ? 'Спам' : $statuses[$newStatus]['title']);
    }
}
