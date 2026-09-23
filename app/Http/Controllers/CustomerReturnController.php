<?php

namespace App\Http\Controllers;

use App\Models\CustomerReturn;
use Illuminate\Http\Request;
use App\Http\Controllers\CustomerReturnController;
use App\Models\Accounts;
use App\Models\CashflowTransactions;
use App\Models\SupplierCredit;
use Illuminate\Support\Facades\DB;

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
        // защита от повторного зачёта/оплаты, если уже обработано
        if (in_array($customerReturn->supplier_refund_status, ['received', 'credited'])) {
            return redirect()->route('admin_panel')->with('error', 'Возврат от поставщика уже зафиксирован ранее!');
        }

        // Живой баг 2026-09-23 (Роман): "Общий статус возврата" и "Статус
        // компенсации" — два независимых select'а, и ничего не мешало
        // сохранить "Завершён" при компенсации всё ещё "в ожидании" —
        // ровно так и получилось: деньги от Тисс реально пришли на баланс,
        // но т.к. "получена" не был отмечен, ни CashflowTransactions, ни
        // SupplierCredit не создались, а возврат при этом ушёл в
        // completed — после чего update() выше по коду уже НЕ даёт второй
        // раз провести эту же запись (защита от повторного зачёта не
        // делает разницы между "уже зачли" и "просто закрыли без зачёта"),
        // то есть исправить дозаполнением формы стало физически
        // невозможно. Блокируем сам переход в completed, пока статус
        // компенсации ещё "в ожидании" — pending имеет смысл только для
        // возврата, который ещё в работе.
        if ($request->status === 'completed' && $request->supplier_refund_status === 'pending') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Нельзя завершить возврат, пока статус компенсации от поставщика — "в ожидании". Сначала отметь "получена" (если деньги пришли) или "не ожидается" (если компенсации не будет).');
        }

        $supplierRefundStatus = $request->supplier_refund_status;
        $cashflowTransactionIn = null;

        // Транзакция 2026-08-31: тот же класс риска, что чинили в
        // makeCustomerReturn() — если запись CustomerReturn::update() ниже
        // упадёт, CashflowTransactions/SupplierCredit не должны оставаться
        // висеть в базе осиротевшими.
        DB::transaction(function () use ($request, $customerReturn, &$supplierRefundStatus, &$cashflowTransactionIn) {
        if ($request->supplier_refund_status === 'received' && (float) $request->supplier_refund_received > 0) {
            if ($request->supplier_refund_mode === 'credit') {
                SupplierCredit::create([
                    'supplier_id' => $customerReturn->supplier_id,
                    'amount' => (float) $request->supplier_refund_received,
                    'source_table' => 'customer_returns',
                    'source_id' => $customerReturn->id,
                    'comment' => 'Зачёт по возврату №' . $customerReturn->id,
                    'date' => $request->supplier_refund_date ?: now(),
                ]);
                $supplierRefundStatus = 'credited';
            } else {
                $cashflowTransactionIn = CashflowTransactions::create([
                    'txn_at' => $request->supplier_refund_date ?: now(),
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
                    'comment' => $request->comment ?: 'Возврат от поставщика по заказу №' . $request->order_id,
                ]);
            }
        }

        $customerReturn->update([
            'supplier_refund_amount' => $request->supplier_refund_amount,
            'supplier_refund_received' => $request->supplier_refund_received,
            'supplier_refund_date' => $request->supplier_refund_date,
            'supplier_refund_status' => $supplierRefundStatus,
            'comment' => $request->comment,
            'closed_at' => $request->closed_at ?: now(),
            'status' => $request->status,
            'supplier_cashflow_transaction_id' => $cashflowTransactionIn?->id,
        ]);
        });

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
