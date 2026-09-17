<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'chat_id', 'is_incoming', 'instance_id',
        'message_text', 'type', 'file_url',
        'message_id', 'raw_body', 'status', 'is_read',
    ];

    protected $casts = [
        'raw_body' => 'array', // Чтобы Laravel сам превращал JSON в массив
        'extracted_parts_json' => 'array',
        'llm_raw_response' => 'array',
        'is_incoming' => 'boolean',
        'llm_processed_at' => 'datetime',
    ];

    // Обратная связь. Было `belongsTo(..., 'chat_id', 'phone')` — колонки chat_id
    // в таблице физически нет (см. разбор WhatsApp-пайплайна с Романом
    // 2026-09-13), связь никогда не работала. Реальный FK — whatsapp_lead_id.
    public function lead()
    {
        return $this->belongsTo(WhatsappLead::class, 'whatsapp_lead_id');
    }
}
