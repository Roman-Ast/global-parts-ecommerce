<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Материализованный снимок GROUP BY по parts_catalog — см. докблок
 * миграции create_popular_categories_table. Наполняется ТОЛЬКО командой
 * catalog:refresh-popular-categories, обычный код в неё не пишет.
 */
class PopularCategory extends Model
{
    protected $fillable = [
        'category_slug',
        'category_top_title',
        'category_top_code',
        'cnt',
    ];
}
