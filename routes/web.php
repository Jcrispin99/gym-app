<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Companies pages
Route::middleware(['auth'])->group(function () {
    Route::get('companies', [\App\Http\Controllers\CompanyController::class, 'indexPage'])->name('companies.page');
    Route::get('companies/create', [\App\Http\Controllers\CompanyController::class, 'create'])->name('companies.create');
    Route::get('companies/{company}/edit', [\App\Http\Controllers\CompanyController::class, 'edit'])->name('companies.edit');
});

// Company CRUD routes
Route::middleware(['auth'])->group(function () {
    Route::post('companies', [\App\Http\Controllers\CompanyController::class, 'store'])->name('companies.store');
    Route::put('companies/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])->name('companies.update');
    Route::delete('companies/{company}', [\App\Http\Controllers\CompanyController::class, 'destroy'])->name('companies.destroy');
});

// Company switcher routes
Route::middleware(['auth'])->group(function () {
    Route::get('/api/companies', [\App\Http\Controllers\CompanyController::class, 'index'])->name('companies.index');
    Route::post('/api/companies/switch', [\App\Http\Controllers\CompanyController::class, 'switch'])->name('companies.switch');

    // Product search API
    Route::get('/api/products/search', [\App\Http\Controllers\Api\ProductApiController::class, 'search'])->name('products.search');
    Route::get('/api/products/{id}', [\App\Http\Controllers\Api\ProductApiController::class, 'show'])->name('products.show');
});

// Users pages
Route::middleware(['auth'])->group(function () {
    Route::get('users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
    Route::get('users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
});

// Users CRUD routes
Route::middleware(['auth'])->group(function () {
    Route::post('users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');
});

// Members routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('members', \App\Http\Controllers\MemberController::class);
    Route::post('members/{member}/activate-portal', [\App\Http\Controllers\MemberController::class, 'activatePortal'])
        ->name('members.activate-portal');
});

// Customers routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);
    Route::post('customers/{customer}/activate-portal', [\App\Http\Controllers\CustomerController::class, 'activatePortal'])
        ->name('customers.activate-portal');
});

// Membership Plans routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('membership-plans', \App\Http\Controllers\MembershipPlanController::class)->parameters([
        'membership-plans' => 'membershipPlan',
    ]);
    Route::post('membership-plans/{membershipPlan}/toggle-status', [\App\Http\Controllers\MembershipPlanController::class, 'toggleStatus'])
        ->name('membership-plans.toggle-status');
    Route::get('membership-plans/{membershipPlan}/activity-log', [\App\Http\Controllers\MembershipPlanController::class, 'activityLog'])
        ->name('membership-plans.activity-log');
});

// Subscriptions routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'index'])
        ->name('subscriptions.index');
    Route::get('subscriptions/{subscription}', [\App\Http\Controllers\SubscriptionController::class, 'show'])
        ->name('subscriptions.show');
    Route::post('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'store'])
        ->name('subscriptions.store');
    Route::post('subscriptions/{subscription}/freeze', [\App\Http\Controllers\SubscriptionController::class, 'freeze'])
        ->name('subscriptions.freeze');
    Route::post('subscriptions/{subscription}/unfreeze', [\App\Http\Controllers\SubscriptionController::class, 'unfreeze'])
        ->name('subscriptions.unfreeze');
    Route::delete('subscriptions/{subscription}', [\App\Http\Controllers\SubscriptionController::class, 'destroy'])
        ->name('subscriptions.destroy');
});

// Attributes routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('attributes', \App\Http\Controllers\AttributeController::class);
    Route::post('attributes/{attribute}/toggle-status', [\App\Http\Controllers\AttributeController::class, 'toggleStatus'])
        ->name('attributes.toggle-status');
});

// Products routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('products', \App\Http\Controllers\ProductController::class);
    Route::post('products/{product}/toggle-status', [\App\Http\Controllers\ProductController::class, 'toggleStatus'])
        ->name('products.toggle-status');
});

// Categories routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('categories', \App\Http\Controllers\CategoryController::class);
    Route::post('categories/{category}/toggle-status', [\App\Http\Controllers\CategoryController::class, 'toggleStatus'])
        ->name('categories.toggle-status');
});

// Attendances routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('attendances', [\App\Http\Controllers\AttendanceController::class, 'index'])
        ->name('attendances.index');
    Route::get('attendances/check-in', [\App\Http\Controllers\AttendanceController::class, 'checkIn'])
        ->name('attendances.checkIn');
    Route::post('attendances/check-in', [\App\Http\Controllers\AttendanceController::class, 'storeCheckIn'])
        ->name('attendances.storeCheckIn');
    Route::get('attendances/lookup-dni', [\App\Http\Controllers\AttendanceController::class, 'lookupByDni'])
        ->name('attendances.lookupByDni');
    Route::post('attendances/{attendance}/check-out', [\App\Http\Controllers\AttendanceController::class, 'checkOut'])
        ->name('attendances.checkOut');
});

require __DIR__ . '/settings.php';

// Warehouses routes
Route::middleware(['auth'])->group(function () {
    Route::resource('warehouses', \App\Http\Controllers\WarehouseController::class);
});

// Journals routes
Route::middleware(['auth'])->group(function () {
    Route::resource('journals', \App\Http\Controllers\JournalController::class);
    Route::post('journals/{journal}/reset-sequence', [\App\Http\Controllers\JournalController::class, 'resetSequence'])
        ->name('journals.reset-sequence');
});

// Taxes routes
Route::middleware(['auth'])->group(function () {
    Route::resource('taxes', \App\Http\Controllers\TaxController::class);
    Route::post('taxes/{tax}/toggle-status', [\App\Http\Controllers\TaxController::class, 'toggleStatus'])
        ->name('taxes.toggle-status');
});

// Purchases routes
Route::middleware(['auth'])->group(function () {
    Route::resource('purchases', \App\Http\Controllers\PurchaseController::class);
    Route::post('purchases/{purchase}/post', [\App\Http\Controllers\PurchaseController::class, 'post'])
        ->name('purchases.post');
    Route::post('purchases/{purchase}/cancel', [\App\Http\Controllers\PurchaseController::class, 'cancel'])
        ->name('purchases.cancel');
});

// Suppliers routes
Route::middleware(['auth'])->group(function () {
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);
});

// Sales routes
Route::middleware(['auth'])->group(function () {
    Route::resource('sales', \App\Http\Controllers\SaleController::class);
    Route::post('sales/{sale}/post', [\App\Http\Controllers\SaleController::class, 'post'])
        ->name('sales.post');
    Route::post('sales/{sale}/cancel', [\App\Http\Controllers\SaleController::class, 'cancel'])
        ->name('sales.cancel');
});

// POS Configs routes
Route::middleware(['auth'])->group(function () {
    Route::resource('pos-configs', \App\Http\Controllers\PosConfigController::class);
    Route::post('pos-configs/{posConfig}/toggle-status', [\App\Http\Controllers\PosConfigController::class, 'toggleStatus'])
        ->name('pos-configs.toggle-status');
    Route::get('pos-configs/{posConfig}/sessions', [\App\Http\Controllers\PosConfigController::class, 'sessions'])
        ->name('pos-configs.sessions');
});

// POS Sessions routes
Route::middleware(['auth'])->group(function () {
    Route::resource('pos-sessions', \App\Http\Controllers\PosSessionController::class);
});

// POS Operation routes
Route::middleware(['auth'])->group(function () {
    Route::get('pos/open', [\App\Http\Controllers\Pos\PosController::class, 'open'])->name('pos.open');
    Route::post('pos/open', [\App\Http\Controllers\Pos\PosController::class, 'storeOpen'])->name('pos.storeOpen');
    Route::get('pos/{session}', [\App\Http\Controllers\Pos\PosController::class, 'dashboard'])->name('pos.dashboard');
    Route::post('pos/{session}/payment', [\App\Http\Controllers\Pos\PosController::class, 'payment'])->name('pos.payment');
    Route::get('pos/{session}/payment', function ($session) {
        return redirect()->route('pos.dashboard', ['session' => $session])
            ->with('info', 'Debes agregar productos al carrito antes de procesar el pago');
    })->name('pos.payment.redirect');
    Route::post('pos/{session}/process', [\App\Http\Controllers\Pos\PosController::class, 'processPayment'])->name('pos.process');
    Route::get('pos/{session}/close', [\App\Http\Controllers\Pos\PosController::class, 'close'])->name('pos.close');
    Route::post('pos/{session}/close', [\App\Http\Controllers\Pos\PosController::class, 'storeClose'])->name('pos.storeClose');

    // POS API endpoints
    Route::get('api/pos/customers', [\App\Http\Controllers\Pos\PosController::class, 'apiCustomers'])->name('api.pos.customers');
    Route::get('api/pos/partners/lookup', [\App\Http\Controllers\Pos\PosController::class, 'apiPartnerLookup'])->name('api.pos.partners.lookup');
    Route::post('api/pos/customers', [\App\Http\Controllers\Pos\PosController::class, 'apiUpsertCustomer'])->name('api.pos.customers.upsert');
});
