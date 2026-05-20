<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KaspiInitialProduct extends Model
{
    // Указываем имя таблицы вручную, так как оно кастомное
    protected $table = 'kaspi_initial_products';

    protected $fillable = [
        'sku',
        'title',
        'brand',
        'category_code',
        'description',
        'price',
        'stock',
        'images',
        'attributes',
        'raw_cross_numbers'
    ];

    // Автоматически преобразуем JSON из базы в массивы PHP
    protected $casts = [
        'images' => 'array',
        'attributes' => 'array',
        'price' => 'float',
        'stock' => 'int',
    ];
}
