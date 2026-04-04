<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReturn extends Model
{
    protected $table = 'customer_returns';

    protected $fillable = [
        'customer_id', 'order_id', 'order_product_id', 'supplier_id', 'user_id', 'customer_phone',
        'return_date', 'qty', 'sale_price', 'customer_refund_amount', 'customer_refund_paid', 'customer_refund_date',
        'supplier_purchase_price', 'supplier_refund_amount', 'supplier_refund_received', 'supplier_refund_date',
        'supplier_refund_status', 'status', 'closed_at', 'reason', 'comment', 'supplier_name', 'customer_cashflow_transaction_id',
        'supplier_cashflow_transaction_id'
    ];

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class, 'order_product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customerCashflowTransaction()
    {
        return $this->belongsTo(CashflowTransactions::class, 'customer_cashflow_transaction_id');
    }

    public function supplierCashflowTransaction()
    {
        return $this->belongsTo(CashflowTransactions::class, 'supplier_cashflow_transaction_id');
    }
}
