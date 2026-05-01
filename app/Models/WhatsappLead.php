<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappLead extends Model
{
    protected $fillable = ['phone', 'client_name', 'last_vin', 'status', 'source', 'last_seen_at'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    // Связь: у одного лида много сообщений
    public function messages()
    {
        // Laravel сам найдет колонку whatsapp_lead_id
        return $this->hasMany(WhatsappMessage::class);
    }
}
