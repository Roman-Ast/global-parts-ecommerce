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

    public static function log(string $action, ?int $leadId = null, ?array $meta = null): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        static::create([
            'user_id' => $userId,
            'action' => $action,
            'whatsapp_lead_id' => $leadId,
            'meta' => $meta,
        ]);
    }
}
