<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettlementController;

Route::get('/', function () {
    return view('index');
});
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');
Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/add', [CartController::class, 'store']);
Route::post('/cart/delete', [CartController::class, 'deleteItem']);
Route::post('/cart/update', [CartController::class, 'update']);
Route::get('cart/clear', [CartController::class, 'clear']);
Route::post('/makeorder', [OrderController::class, 'store']);
Route::get('orders', [OrderController::class, 'index']);
Route::get('settlements', [SettlementController::class, 'index']);
Route::post('order/products', [OrderController::class, 'products']);

Route::middleware(['auth', 'verified'])->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
});
Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');



Route::middleware('guest')->group(function() {
    Route::get('register', [UserController::class, 'create'])->name('register');
    Route::post('register', [UserController::class, 'store'])->name('user.store');

    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::post('login', [UserController::class, 'authLogin'])->name('login.auth');

    Route::get('forgot-password', function() {
        return view('user.forgot-password');
    })->name('password.request');

    Route::post('forgot-password', [USerController::class, 'forgotPasswordStore'])->name('password.email')->middleware('throttle:3,1');

    Route::get('reset-password/{token}', function(string $token) {
        return view('user.reset-password', ['token' => $token]);
    })->name('password.reset');

    Route::post('reset-password', [UserController::class, 'resetPasswordUpdate'])->name('password.update');
});


Route::middleware('auth')->group(function() {
    Route::get('verify-email', function(){
        return view('user.verify-email');
    })->name('verification.notice');
    
    Route::get('/email/verify/{id}/{hash}', function(EmailVerificationRequest $request){
        $request->fulfill();
    
        return redirect()->route('dashboard');
    })->middleware('signed')->name('verification.verify');
    
    Route::post('/email/verification-notification', function(Request $request){
        $request->user()->sendEmailVerificationnotification();
    
        return back()->with('message', 'Ссылка верификации отправлена!');
    })->middleware('throttle:2,1')->name('verification.send');

    Route::get('logout', [UserController::class, 'logout'])->name('logout');
});






