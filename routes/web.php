<?php

use App\Http\Controllers\AdminPanelController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\CartController;
use App\Http\Controllers\GarageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SettlementController;


Route::get('/', function () {
    return view('index');
});
Route::get('/', [UserController::class, 'index']);
Route::get('/home', [HomeController::class, 'index'])->name('home');






Route::post('/product/change_status', [AdminPanelController::class, 'changeStatus']);

Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/add', [CartController::class, 'store']);
Route::post('/cart/delete', [CartController::class, 'deleteItem']);
Route::post('/cart/update', [CartController::class, 'update']);
Route::get('cart/clear', [CartController::class, 'clear']);
Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');


Route::middleware(['auth', 'verified'])->group(function() {
    Route::get('/', function () {
        return view('index');
    });
    
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/makeorder', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('settlements', [SettlementController::class, 'index']);
    Route::post('order/products', [OrderController::class, 'products']);
    Route::get('admin_panel', [AdminPanelController::class, 'index'])->name('admin_panel');
    Route::post('/payment', [AdminPanelController::class, 'pay']);
    Route::post('/product/change_status', [AdminPanelController::class, 'changeStatus']);
    Route::get('/garage', [GarageController::class, 'index']);
    Route::get('/garage/create', [GarageController::class, 'create']);
    Route::post('/garage/store', [GarageController::class, 'store'])->name('garage.store');
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'store']);
    Route::post('/cart/delete', [CartController::class, 'deleteItem']);
    Route::post('/cart/update', [CartController::class, 'update']);
    Route::get('cart/clear', [CartController::class, 'clear']);
    Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
    Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');
});

Route::middleware('guest')->group(function() {
    Route::get('/', function () {
        return view('index');
    });
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

    Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
    Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'store']);
    Route::post('/cart/delete', [CartController::class, 'deleteItem']);
    Route::post('/cart/update', [CartController::class, 'update']);
    Route::get('cart/clear', [CartController::class, 'clear']);
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

    Route::post('/makeorder', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('settlements', [SettlementController::class, 'index']);
    Route::post('order/products', [OrderController::class, 'products']);
    Route::get('admin_panel', [AdminPanelController::class, 'index'])->name('admin_panel');
    Route::post('/payment', [AdminPanelController::class, 'pay']);
    Route::post('/product/change_status', [AdminPanelController::class, 'changeStatus']);
    Route::get('/garage', [GarageController::class, 'index']);
    Route::get('/garage/create', [GarageController::class, 'create']);
    Route::post('/garage/store', [GarageController::class, 'store'])->name('garage.store');
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'store']);
    Route::post('/cart/delete', [CartController::class, 'deleteItem']);
    Route::post('/cart/update', [CartController::class, 'update']);
    Route::get('cart/clear', [CartController::class, 'clear']);
    Route::get('logout', [UserController::class, 'logout'])->name('logout');
    Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
    Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');
});






