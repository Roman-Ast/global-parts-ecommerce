<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * Заказы Kaspi из Merchant API (kaspi:sync-orders), ещё не оформленные в
 * нашей ERP — для подстановки в форму «Ручное создание заказа» (admin.js).
 * Поставщика и С/С Роман выбирает сам: автоматически создавать заказ в ERP
 * не стали — от этих полей считается долг поставщику.
 */
class KaspiOrdersController extends Controller
{
    /** Отменённые/возвращённые оформлять нечего. */
    const SKIP_STATUSES = ['CANCELLED', 'CANCELLING', 'RETURNED', 'KASPI_DELIVERY_RETURN_REQUESTED'];

    public function pending()
    {
        $orders = DB::table('kaspi_orders')
            ->whereNull('order_id')
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', self::SKIP_STATUSES);
            })
            ->where('kaspi_created_at', '>=', now()->subDays(14))
            ->orderByDesc('kaspi_created_at')
            ->get();

        $entries = DB::table('kaspi_order_entries as e')
            ->leftJoin('kaspi_feed_items as f', 'f.kaspi_sku', '=', 'e.kaspi_sku')
            ->whereIn('e.kaspi_order_id', $orders->pluck('id'))
            ->orderBy('e.entry_number')
            ->get(['e.kaspi_order_id', 'e.kaspi_sku', 'e.offer_name', 'e.quantity', 'e.base_price',
                   'f.our_article', 'f.brand', 'f.purchase_price', 'f.supplier_name'])
            ->groupBy('kaspi_order_id');

        return response()->json($orders->map(fn($o) => [
            'code'          => $o->code,
            'created_at'    => $o->kaspi_created_at,
            'date'          => substr((string) $o->kaspi_created_at, 0, 10),
            'status'        => $o->status,
            'state'         => $o->state,
            'customer_name' => $o->customer_name,
            'town'          => $o->town,
            'total_price'   => (float) $o->total_price,
            'entries'       => ($entries[$o->id] ?? collect())->map(fn($e) => [
                'kaspi_sku'      => $e->kaspi_sku,
                'name'           => $e->offer_name,
                'qty'            => (int) $e->quantity,
                'price'          => (float) $e->base_price,
                'article'        => $e->our_article,
                'brand'          => $e->brand ? mb_strtoupper($e->brand) : null,
                'purchase_price' => $e->purchase_price !== null ? (float) $e->purchase_price : null,
                'supplier_hint'  => $e->supplier_name,
            ])->values(),
        ])->values());
    }
}
