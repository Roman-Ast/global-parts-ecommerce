<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemandSignal extends Model
{
    protected $fillable = [
        'whatsapp_lead_id', 'lead_request_id', 'source', 'phone',
        'vin', 'brand', 'car_model', 'car_year',
        'part_name', 'part_side', 'part_position',
        'availability_answer', 'lead_time_days', 'quoted_price',
        'outcome', 'decline_reason', 'outcome_amount', 'notes', 'raw_llm_response', 'analyzed_at',
    ];

    protected $casts = [
        'raw_llm_response' => 'array',
        'analyzed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(WhatsappLead::class, 'whatsapp_lead_id');
    }

    public function leadRequest()
    {
        return $this->belongsTo(LeadRequest::class, 'lead_request_id');
    }
}
