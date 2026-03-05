<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashflowTransactions extends Model
{
    protected $table = 'cashflow_transactions';

    protected $fillable = [
        'txn_at',
        'direction',
        'account_id',
        'amount',
        'category',
        'subcategory',
        'counterparty',
        'related_table',
        'related_id',
        'comment',
        'cashflow_category_id',
        'expense_category_id',
        'supplier_id',
        'user_id',

    ];
}
