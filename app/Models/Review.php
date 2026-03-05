<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'src',
        'author',
        'date',
        'avatar',
        'text',
        'rate'
    ];
}
