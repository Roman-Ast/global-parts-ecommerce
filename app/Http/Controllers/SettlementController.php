<?php

namespace App\Http\Controllers;

use App\Models\Setlement;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        $settlements = Setlement::where('user_id', $user->id)->orderBy('date', 'desc')->get();
        $orders = $user->orders;
        $sumReleased = $settlements->where('released', 1)->sum('sumWithMargine');
        $sumPaid = $settlements->where('paid', 1)->sum('sumWithMargine');
        
        return view('settlements', [
            'settlements' => $settlements,
            'sumReleased' => $sumReleased,
            'sumPaid' => $sumPaid,
            'balance' => $sumPaid + $sumReleased
        ]);
    }
}
