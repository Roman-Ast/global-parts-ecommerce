<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierSettlement extends Model
{
    use HasFactory;

    protected $table = 'supplier_settlement';

    protected $fillable = [
        'order_id',
        'supplier',
        'sum',
        'date',
        'operation',
        'product_id'
    ];
}
