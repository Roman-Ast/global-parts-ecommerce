<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartsCatalog extends Model
{
    protected $table = 'parts_catalog';

    protected $casts = [
        'characteristics' => 'array',
        'images' => 'array',
        'scraped_at' => 'datetime',
    ];

    protected $guarded = [];
}
