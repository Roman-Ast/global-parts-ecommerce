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
use App\Http\Controllers\GmPricelistFromAdilController;
use App\Http\Controllers\OfficePriceController;
use App\Http\Controllers\XuiPoimiPriceController;
use App\Http\Controllers\IngvarPriceController;
use App\Models\gm_pricelist_from_adil;
use App\Models\OfficePrice;
use App\Models\XuiPoimiPrice;
use App\Models\IngvarPrice;
use App\Models\InterkomPrice;
use App\Models\AdilPhaetonPrice;
use App\Http\Controllers\GlobalProductController;
use App\Http\Controllers\VoltagePriceController;
use App\Http\Controllers\BlueStarPriceController;
use App\Http\Controllers\InterkomPriceController;
use App\Http\Controllers\AdilPhaetonPriceController;
use App\Http\Controllers\SparePartRequestController;
use App\Http\Controllers\AISimpleSearchController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\FinanceDashboardController;


/*Route::get('/home', function() {
    (new AdilPhaetonPrice())->importToDb();
    dd('done');
});*/

Route::get('/test-host-error', function () {
    return view('components.hostError');
});

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/home', '/', 301);
Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);
Route::post('/getPart/', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'store'])->name('cart.add');
Route::post('/cart/delete', [CartController::class, 'deleteItem'])->name('cart.delete');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::get('cart/clear', [CartController::class, 'clear'])->name('cart.clear');

Route::post('/sparepart-request', [SparePartRequestController::class, 'store']);
Route::post('/simpleAISearchWithoutVin', [AISimpleSearchController::class, 'searchArticlesByGPT']);
Route::post('/simpleAIVinSearch', [AISimpleSearchController::class, 'searchArticlesByGPTWithVin']);

//каталог корейских авто
Route::get('/hyundai', function() {
    return view('korean-cars.index');
});
Route::get('/hyundai/santafe20-24', function() {
    return view('korean-cars.santafe18-21');
});
Route::get('hyundai/sonata19-23', function() {
    return view('korean-cars.sonata19-23');
});
Route::get('hyundai/k520-23', function() {
    return view('korean-cars.k520-23');
});
Route::get('hyundai/sportage21-25', function() {
    return view('korean-cars.sportage21-25');
});

//каталог корейскикитайских авто
Route::get('/chinacars', function() {
    return view('china-cars.index');
});
Route::get('/china/chery-tigo-7-pro', function() {
    return view('china-cars.chery-tiggo-7-pro');
});
Route::get('china/sonata19-23', function() {
    return view('korean-cars.sonata19-23');
});
Route::get('china/k520-23', function() {
    return view('korean-cars.k520-23');
});
Route::get('china/sportage21-25', function() {
    return view('korean-cars.sportage21-25');
});

Route::get('/product/{brand}/{article}', [GlobalProductController::class, 'show'])->name('product.show');
// Добавляем /api/ в начало пути прямо здесь
Route::get('/api/search-prices', [App\Http\Controllers\GlobalProductController::class, 'getApiPrices']);

Route::middleware('guest')->group(function() {
    Route::get('/', [HomeController::class, 'index']);
    
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

Route::middleware(['auth', 'verified'])->group(function() {
    Route::post('/make-cashflow-transaction', [AdminPanelController::class, 'makeCashflowTransaction'])->name('make-cashflow-transaction');
    Route::post('/makeorder', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('settlements', [SettlementController::class, 'index']);
    Route::post('order/products', [OrderController::class, 'products']);
    Route::get('admin_panel', [AdminPanelController::class, 'index'])->name('admin_panel');
    Route::post('/payment', [AdminPanelController::class, 'pay']);
    Route::post('/product/change_status', [AdminPanelController::class, 'changeStatus']);
    Route::get('/garage', [GarageController::class, 'index'])->name('garage');
    Route::get('/garage/create', [GarageController::class, 'create']);
    Route::post('/garage/store', [GarageController::class, 'store'])->name('garage.store');
    Route::post('/garage/destroy', [GarageController::class, 'destroy']);

    Route::post('import', [GmPricelistFromAdilController::class, 'store']);
    Route::post('import-in-office', [OfficePriceController::class, 'store']);
    Route::post('import-xui-poimi', [XuiPoimiPriceController::class, 'store']);
    Route::post('import-ingvar', [IngvarPriceController::class, 'store']);
    Route::post('import-voltage', [VoltagePriceController::class, 'store']);
    Route::post('import-blue-star', [BlueStarPriceController::class, 'store']);
    Route::post('import-interkom', [InterkomPriceController::class, 'store']);
    Route::post('import-adil-phaeton', [AdilPhaetonPriceController::class, 'store']);
    Route::get('additional-payment', [AdminPanelController::class, 'additionalPayment']);
    Route::post('choose_products_from_order', [AdminPanelController::class, 'chooseProductsFromOrder']);
    Route::post('makeCustomerReturn', [AdminPanelController::class, 'makeCustomerReturn']);
    Route::get('/supplierRefundComplete/{customerReturn}', 
        [CustomerReturnController::class, 'edit']
    )->name('supplierRefundComplete');
    Route::put('/customer-returns/{customerReturn}', 
        [CustomerReturnController::class, 'update']
    )->name('customer_returns.update');

    Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index'])
    ->name('dashboard.finance');

    

});

Route::middleware('auth')->group(function() {
    Route::get('verify-email', function(){
        return view('user.verify-email');
    })->name('verification.notice');
    
    Route::get('/email/verify/{id}/{hash}', function(EmailVerificationRequest $request){
        $request->fulfill();
    
        return redirect()->route('home');
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
    Route::get('logout', [UserController::class, 'logout'])->name('logout');
    Route::post('/supplier/payment', [AdminPanelController::class, 'supplierPayment'])->name('supplier.payment');
    Route::post('/orders/filter', [AdminPanelController::class, 'filter']);
    Route::post('/orders/filter/drop', [AdminPanelController::class, 'filterDrop']);
    Route::post('/manually_make_order', [AdminPanelController::class, 'manuallyMakeOrder']);
    Route::post('/cart/updatePrice', [CartController::class, 'updatePrice']);
    Route::post('/add_new_good_in_office', [AdminPanelController::class, 'addNewGoodInOffice']);
    Route::post('/delete_good_in_office', [AdminPanelController::class, 'destroy']);
});






