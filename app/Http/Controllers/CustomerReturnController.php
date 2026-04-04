<?php

namespace App\Http\Controllers;

use App\Models\CustomerReturn;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerReturnController;
use App\Models\Accounts;
use App\Models\CashflowTransactions;

class CustomerReturnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerReturn $customerReturn)
    {
        //dd($customerReturn);
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomerReturn $customerReturn)
    {
        $accounts = Accounts::all();

        return view('completeCustomerReturn', [
            'customerReturn' => $customerReturn,
            'accounts' => $accounts
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerReturn $customerReturn)
    {
        //dd($request);
        $customerReturn->update([
            'supplier_refund_amount' => $request->supplier_refund_amount,
            'supplier_refund_received' => $request->supplier_refund_received,
            'supplier_refund_date' => $request->supplier_refund_date,
            'supplier_refund_status' => $request->supplier_refund_status,
            'comment' => $request->comment,
            'closed_at' => $request->closed_at,
            'status' => $request->status,
        ]);

        //делаем запись в cashflow_transactions (ДДС)
        $cashflowTransactionIn = CashflowTransactions::create([
            'txn_at' => now(), // дата фактической оплаты
            'direction' => 'in',
            'cashflow_category_id' => 4,
            'expense_category_id' => null,
            'supplier_id' => $request->supplier_id,
            'user_id' => auth()->id(),
            'account_id' => $request->account_id_in,
            'amount' => $request->supplier_refund_received,
            'subcategory' => 'возврат от поставщика по заказу №' . $request->order_id,
            'counterparty' => $request->supplier_name,
            'related_table' => 'customer_returns',
            'related_id' => $customerReturn->id,
            'comment' => $request->comment == '' ? 'Возврат от поставщика по заказу №' . $request->order_id : $request->comment,
        ]);

        $customerReturn->update(['supplier_cashflow_transaction_id' => $cashflowTransactionIn->id]);
        
        return redirect()
            ->route('admin_panel')
            ->with('success', 'Возврат обновлён');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerReturn $customerReturn)
    {
        //
    }
}
