<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadRequest extends Model
{
    protected $fillable = [
        'whatsapp_lead_id', 'vin', 'car_model', 'raw_request', 'parts_json', 'status', 'deal_sum'
    ];

    protected $casts = [
        'parts_json' => 'array', // Чтобы Laravel сам превращал JSON из базы в массив
    ];

    public function lead()
    {
        return $this->belongsTo(WhatsappLead::class, 'whatsapp_lead_id');
    }
}
