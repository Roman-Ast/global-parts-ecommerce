<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setlement extends Model
{
    use HasFactory;

    protected $table = 'settlements';

    protected $fillable = [
        'order_id',
        'user_id',
        'operation',
        'date',
        'sum',
        'released',
        'paid',
        'sumWithMargine'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
