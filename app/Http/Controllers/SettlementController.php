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
        $sumReleased = $settlements->where('released', 1)->sum('sum');
        $sumPaid = $settlements->where('paid', 1)->sum('sum');


        return view('settlements', [
            'settlements' => $settlements,
            'sumReleased' => $sumReleased,
            'sumPaid' => $sumPaid,
            'balance' => $sumPaid - $sumReleased
        ]);
    }
}
