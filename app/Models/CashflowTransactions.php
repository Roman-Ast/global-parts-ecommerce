<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounts;
use App\Models\Suppliers;
use App\Models\CashflowCategories;

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

    public function account()
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Suppliers::class);
    }

    public function cashflowCategory()
    {
        return $this->belongsTo(CashflowCategories::class);
    }
}
