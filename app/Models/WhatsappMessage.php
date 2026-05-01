<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'chat_id', 'is_incoming', 'instance_id', 
        'message_text', 'type', 'file_url', 
        'message_id', 'raw_body'
    ];

    protected $casts = [
        'raw_body' => 'array', // Чтобы Laravel сам превращал JSON в массив
        'is_incoming' => 'boolean'
    ];

    // Обратная связь
    public function lead()
    {
        return $this->belongsTo(WhatsappLead::class, 'chat_id', 'phone');
    }
}
