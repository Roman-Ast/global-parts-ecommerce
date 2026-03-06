<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;
use Auth;

class HomeController extends Controller
{
    public function index()
    {  
        $reviews = Review::query()
            ->inRandomOrder()
            ->limit(4)
            ->get();

        Route::get('/', [HomeController::class, 'index']);
    }
}
