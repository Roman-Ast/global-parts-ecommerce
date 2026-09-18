<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only лог активности в СРМ — см. докблок миграции
 * 2026_09_18_000001_create_crm_activity_log_table за примером агрегации
 * по дням. Никакой отдельной вьюхи под просмотр пока нет (Роман смотрит
 * напрямую через SQL) — если понадобится, строить поверх этой же таблицы.
 */
class CrmActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'crm_activity_log';

    protected $fillable = ['user_id', 'action', 'whatsapp_lead_id', 'meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Вспомогательный лог — сбой здесь (напр. миграция ещё не докатилась на
     * прод, как случилось 2026-09-18: таблицы не было, и это валило
     * ЦЕЛИКОМ открытие доски/чата/перемещение карточек) не должен ронять
     * основной функционал СРМ. Ловим и молча пишем в обычный Laravel-лог —
     * потеря одной записи активности несравнимо дешевле недоступной доски.
     */
    public static function log(string $action, ?int $leadId = null, ?array $meta = null): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        try {
            static::create([
                'user_id' => $userId,
                'action' => $action,
                'whatsapp_lead_id' => $leadId,
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CrmActivityLog::log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
