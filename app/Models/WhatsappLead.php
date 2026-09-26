<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappLead extends Model
{
    protected $fillable = ['phone', 'client_name', 'last_vin', 'status', 'source', 'last_seen_at', 'custom_reminder_hours'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    // Связь: у одного лида много сообщений
    public function messages()
    {
        // Laravel сам найдет колонку whatsapp_lead_id
        return $this->hasMany(WhatsappMessage::class);
    }

    /**
     * Последнее сообщение — для канбана (KanbanBoard::render()). Раньше там
     * грузили messages через with(['messages' => fn($q) => $q->latest()->limit(1)]),
     * но limit() внутри eager-load closure ограничивает ОБЩИЙ запрос, а не
     * даёт "по одному на лида" — реально возвращалось одно сообщение на всю
     * пачку лидов разом. Это маскировалось точечным ->load() на каждом лиде
     * внутри ->map() (N+1: отдельный запрос на каждого лида). latestOfMany()
     * решает и то, и другое — один корректный запрос, без N+1.
     */
    public function lastMessage()
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany();
    }
}
