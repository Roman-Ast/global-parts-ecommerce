<?php

namespace App\Support;

use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use Illuminate\Support\Collection;

/**
 * Поиск "как в WhatsApp" по тексту переписки + номеру телефона (просьба
 * Романа 2026-09-22) — сначала реализован только в WhatsappMessenger
 * (полноэкранная страница /admin/whatsapp), но Роман работает СТРОГО с
 * канбан-доски и той страницей не пользуется вообще. Вынесено сюда общим
 * классом, чтобы KanbanBoard мог показать тот же поиск прямо у себя
 * (открывает найденный чат в той же шторке, что и обычный клик по
 * карточке — не отдельный рабочий процесс), не дублируя запрос и логику
 * подсветки в двух Livewire-компонентах.
 */
class WhatsappMessageSearch
{
    /** WhatsApp тоже не показывает сотни совпадений на генерик-запрос
     *  ("да"/"ок") — выдача всегда ограничена разумным числом. */
    const RESULTS_LIMIT = 50;

    /**
     * Ищет лидов по совпадению в тексте сообщений ИЛИ в номере телефона.
     * У каждого найденного лида проставлен search_snippet — КОНКРЕТНОЕ
     * совпавшее сообщение (не последнее), чтобы в выдаче было видно, ГДЕ
     * нашлось совпадение, а не просто что лид попал в список.
     *
     * LIKE '%...%' без полнотекстового индекса — на текущих объёмах
     * (несколько тысяч сообщений) достаточно быстро; если
     * whatsapp_messages вырастет на порядки, стоит вернуться к FULLTEXT,
     * не раньше.
     */
    public static function search(string $query, int $limit = self::RESULTS_LIMIT): Collection
    {
        $like = '%' . self::escapeLike($query) . '%';

        $matchingLeadIds = WhatsappMessage::where('message_text', 'like', $like)
            ->distinct()
            ->pluck('whatsapp_lead_id');

        $leads = WhatsappLead::query()
            ->where(function ($q) use ($like, $matchingLeadIds) {
                $q->whereIn('id', $matchingLeadIds)
                  ->orWhere('phone', 'like', $like);
            })
            ->orderBy('last_seen_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($leads as $lead) {
            $matched = WhatsappMessage::where('whatsapp_lead_id', $lead->id)
                ->where('message_text', 'like', $like)
                ->latest()
                ->first();
            $lead->setAttribute('search_snippet', $matched?->message_text);
        }

        return $leads;
    }

    /** Экранирует спецсимволы LIKE (%, _, \), чтобы поиск не путался,
     *  если пользователь вобьёт их буквально (напр. "скидка 10%"). */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Подсветка совпадения в превью, как в самом WhatsApp — экранируем
     * ВЕСЬ текст сначала (защита от XSS из содержимого сообщения), потом
     * оборачиваем совпавший фрагмент в <mark>.
     */
    public static function highlight(?string $text, ?string $query): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $escaped = e($text);
        $query = trim((string) $query);

        if ($query === '') {
            return $escaped;
        }

        return preg_replace(
            '/(' . preg_quote(e($query), '/') . ')/iu',
            '<mark class="bg-yellow-200 rounded px-0.5">$1</mark>',
            $escaped
        );
    }
}
