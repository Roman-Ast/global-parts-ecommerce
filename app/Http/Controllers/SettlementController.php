<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        $settlements = $user->settlements;
        $order = $user->orders;
        $sumReleased = $order->sum('sum');
        $sumPaid = 0;

        return view('settlements', [
            'settlements' => $settlements,
            'sumReleased' => $sumReleased,
            'sumPaid' => $sumPaid,
            'balance' => $sumPaid - $sumReleased
        ]);
    }
}
