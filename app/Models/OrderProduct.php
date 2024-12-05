<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    protected $table = 'order_product';

    protected $fillable = [
        'order_id',
        'article',
        'brand',
        'name',
        'price',
        'priceWithMargine',
        'item_sum',
        'itemSumWithMargine',
        'searched_number',
        'fromStock',
        'deliveryTime',
        'qty',
        'status'
    ];

    
}
