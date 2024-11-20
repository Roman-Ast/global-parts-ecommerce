<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GaraGE extends Model
{
    use HasFactory;
    protected $table = 'garage';

    protected $fillable = [
        'user_id', 'model', 'year', 'vincode', 'licence',
        'owner_name', 'owner_phone', 'note'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
