<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'date',
        'sum',
        'payment_method',
        'comments'
    ];

    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
