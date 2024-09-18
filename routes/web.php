<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SparePartController;

Route::get('/', function () {
    return view('index');
});

Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');
Route::get('/searchTreid', [SparePartController::class, 'searchTreid']);
