<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCredit extends Model
{
    protected $table = 'supplier_credits';

    protected $fillable = [
        'supplier_id', 'amount', 'source_table', 'source_id', 'comment', 'date'
    ];

    public function supplier()
    {
        return $this->belongsTo(Suppliers::class, 'supplier_id');
    }
}