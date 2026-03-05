<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $table = 'order_payments';

    protected $fillable = [
        'order_id',
        'account_id',
        'paid_at',
        'amount',
        'comment',
        'type',
    ];
}
