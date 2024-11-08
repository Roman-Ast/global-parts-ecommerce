<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'paid'
    ];
}
