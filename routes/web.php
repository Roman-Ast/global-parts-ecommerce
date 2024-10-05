<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;


Route::get('/', function () {
    return view('index');
});
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');

Route::get('register', [UserController::class, 'create'])->name('register');
Route::post('register', [UserController::class, 'store'])->name('user.store');
Route::get('login', [UserController::class, 'login'])->name('login');
Route::get('logout', [UserController::class, 'logout'])->name('logout');

Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');

Route::get('verify-email', function(){
    return view('user.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function(EmailVerificationRequest $request){
    $request->fulfill();

    return redirect()->route('dashboard');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function(Request $request){
    $request->user()->sendEmailVerificationnotification();

    return back()->with('message', 'Ссылка верификации отправлена!');
})->middleware(['auth', 'throttle:2,1'])->name('verification.send');




