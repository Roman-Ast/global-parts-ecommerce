<?php

use Illuminate\Support\Facades\URL;
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
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\Admin\WhatsappController;
use App\Http\Controllers\Admin\KanbanController;

// ─── Robots.txt ───────────────────────────────────────────────────────────────
Route::get('/robots.txt', function () {
    $content = "User-agent: *\nAllow: /\nSitemap: " . config('app.url') . "/sitemap.xml";
    return response($content, 200)->header('Content-Type', 'text/plain');
});

Route::get('/test-host-error', function () {
    return view('components.hostError');
});

// ─── Публичные роуты ──────────────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/home', '/', 301);

Route::post('/getPart', [SparePartController::class, 'getSearchedPartAndCrosses'])->name('getPart');
Route::get('/fetch-images', [GlobalProductController::class, 'fetchGoogleImages'])->name('product.fetchImages');
Route::get('/getCatalog', [SparePartController::class, 'catalogSearch']);

Route::get('/api/search-prices', [GlobalProductController::class, 'getApiPrices']);
Route::get('/api/search-rossko', [GlobalProductController::class, 'getRosskoApi']);

Route::post('/sparepart-request', [SparePartRequestController::class, 'store']);
Route::post('/simpleAISearchWithoutVin', [AISimpleSearchController::class, 'searchArticlesByGPT']);
Route::post('/simpleAIVinSearch', [AISimpleSearchController::class, 'searchArticlesByGPTWithVin']);

// ─── Корзина (публичная) ───────────────────────────────────────────────────────
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add', [CartController::class, 'store'])->name('cart.add');
Route::post('/cart/delete', [CartController::class, 'deleteItem'])->name('cart.delete');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::get('cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/add-api', [GlobalProductController::class, 'addToCartApi']);

// ─── Каталог корейских авто ───────────────────────────────────────────────────
Route::get('/hyundai', fn() => view('korean-cars.index'));
Route::get('/hyundai/santafe20-24', fn() => view('korean-cars.santafe18-21'));
Route::get('hyundai/sonata19-23', fn() => view('korean-cars.sonata19-23'));
Route::get('hyundai/k520-23', fn() => view('korean-cars.k520-23'));
Route::get('hyundai/sportage21-25', fn() => view('korean-cars.sportage21-25'));

// ─── Каталог китайских авто ───────────────────────────────────────────────────
Route::get('/chinacars', fn() => view('china-cars.index'));
Route::get('/china/chery-tigo-7-pro', fn() => view('china-cars.chery-tiggo-7-pro'));
Route::get('china/sonata19-23', fn() => view('korean-cars.sonata19-23'));
Route::get('china/k520-23', fn() => view('korean-cars.k520-23'));
Route::get('china/sportage21-25', fn() => view('korean-cars.sportage21-25'));

// ─── Страница товара ──────────────────────────────────────────────────────────
Route::get('/product/{brand}/{article}', [GlobalProductController::class, 'show'])
    ->name('product.show')
    ->where('brand', '.*')
    ->where('article', '.*');

// ─── Гость: регистрация, логин, сброс пароля ─────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('register', [UserController::class, 'create'])->name('register');
    Route::post('register', [UserController::class, 'store'])->name('user.store');

    Route::get('login', [UserController::class, 'login'])->name('login');
    Route::post('login', [UserController::class, 'authLogin'])->name('login.auth');

    Route::get('forgot-password', fn() => view('user.forgot-password'))->name('password.request');
    Route::post('forgot-password', [UserController::class, 'forgotPasswordStore'])
        ->name('password.email')
        ->middleware('throttle:3,1');

    Route::get('reset-password/{token}', fn(string $token) => view('user.reset-password', ['token' => $token]))
        ->name('password.reset');
    Route::post('reset-password', [UserController::class, 'resetPasswordUpdate'])->name('password.update');
});

// ─── Авторизован: верификация email ───────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('logout', [UserController::class, 'logout'])->name('logout');

    // Страница "подтвердите email"
    Route::get('verify-email', fn() => view('user.verify-email'))->name('verification.notice');

   Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = App\Models\User::find($id);
    
    if (!$user) {
        return 'User not found';
    }
    
    if (!hash_equals(sha1($user->email), $hash)) {
        return 'Hash mismatch: ' . sha1($user->email) . ' vs ' . $hash;
    }
    
    if ($user->hasVerifiedEmail()) {
        return 'Already verified';
    }
    
    $user->markEmailAsVerified();
    
    return redirect()->route('home')
        ->with('message', 'Email успешно подтверждён!')
        ->with('class', 'alert-success');
})->name('verification.verify');

    // Повторная отправка письма
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('message', 'Ссылка верификации отправлена на вашу почту!');
    })->middleware('throttle:2,1')->name('verification.send');
});

// ─── Авторизован + email подтверждён ─────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
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

    Route::post('/cart/updatePrice', [CartController::class, 'updatePrice']);
    Route::post('/cart/add-api', [GlobalProductController::class, 'addToCartApi']);

    Route::post('/make-cashflow-transaction', [AdminPanelController::class, 'makeCashflowTransaction'])->name('make-cashflow-transaction');
    Route::post('/supplier/payment', [AdminPanelController::class, 'supplierPayment'])->name('supplier.payment');
    Route::post('/orders/filter', [AdminPanelController::class, 'filter']);
    Route::post('/orders/filter/drop', [AdminPanelController::class, 'filterDrop']);
    Route::post('/manually_make_order', [AdminPanelController::class, 'manuallyMakeOrder']);
    Route::post('/add_new_good_in_office', [AdminPanelController::class, 'addNewGoodInOffice']);
    Route::post('/delete_good_in_office', [AdminPanelController::class, 'destroy']);
    Route::get('additional-payment', [AdminPanelController::class, 'additionalPayment']);
    Route::post('choose_products_from_order', [AdminPanelController::class, 'chooseProductsFromOrder']);
    Route::post('makeCustomerReturn', [AdminPanelController::class, 'makeCustomerReturn']);

    Route::get('/supplierRefundComplete/{customerReturn}', [CustomerReturnController::class, 'edit'])->name('supplierRefundComplete');
    Route::put('/customer-returns/{customerReturn}', [CustomerReturnController::class, 'update'])->name('customer_returns.update');

    Route::get('/dashboard/finance', [FinanceDashboardController::class, 'index'])->name('dashboard.finance');

    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

    // Импорт прайсов
    Route::post('import', [GmPricelistFromAdilController::class, 'store']);
    Route::post('import-in-office', [OfficePriceController::class, 'store']);
    Route::post('import-xui-poimi', [XuiPoimiPriceController::class, 'store']);
    Route::post('import-ingvar', [IngvarPriceController::class, 'store']);
    Route::post('import-voltage', [VoltagePriceController::class, 'store']);
    Route::post('import-blue-star', [BlueStarPriceController::class, 'store']);
    Route::post('import-interkom', [InterkomPriceController::class, 'store']);
    Route::post('import-adil-phaeton', [AdilPhaetonPriceController::class, 'store']);
});

// ─── Админ: WhatsApp ──────────────────────────────────────────────────────────
Route::prefix('admin/whatsapp')->group(function () {
    Route::get('/', [WhatsappController::class, 'index'])->name('admin.whatsapp.index');
    Route::get('/{lead}', [WhatsappController::class, 'show'])->name('admin.whatsapp.show');
});

// ─── Админ: Kanban ────────────────────────────────────────────────────────────
Route::prefix('admin')->group(function () {
    Route::get('/kanban', [KanbanController::class, 'index'])->name('admin.kanban');
    Route::post('/kanban/update-status', [KanbanController::class, 'updateStatus'])->name('admin.kanban.update');
});

// ─── Fallback — ВСЕГДА последним ─────────────────────────────────────────────
Route::fallback(function () {
    return redirect('/', 301);
});