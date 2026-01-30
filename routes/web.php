<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

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

    // Sunat/Reniec Lookup API
    Route::get('/api/sunat/lookup', [\App\Http\Controllers\Api\PartnerLookupController::class, 'lookup'])->name('api.sunat.lookup');
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
    Route::get('customers', function () {
        return Inertia::render('Customers/Index');
    })->name('customers.index');

    Route::get('customers/create', function () {
        return Inertia::render('Customers/FormPage', [
            'mode' => 'create',
        ]);
    })->name('customers.create');

    Route::get('customers/{customer}/edit', function (string $customer) {
        return Inertia::render('Customers/FormPage', [
            'mode' => 'edit',
            'customer_id' => (int) $customer,
        ]);
    })->name('customers.edit');
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
    Route::get('attributes', function () {
        return Inertia::render('Attributes/Index');
    })->name('attributes.index');

    Route::get('attributes/create', function () {
        return Inertia::render('Attributes/FormPage', [
            'mode' => 'create',
        ]);
    })->name('attributes.create');

    Route::get('attributes/{attribute}/edit', function (string $attribute) {
        return Inertia::render('Attributes/FormPage', [
            'mode' => 'edit',
            'attribute_id' => (int) $attribute,
        ]);
    })->name('attributes.edit');
});

// Products routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('products', function () {
        return Inertia::render('Products/Index');
    })->name('products.index');

    Route::get('products/create', function () {
        return Inertia::render('Products/FormPage', [
            'mode' => 'create',
        ]);
    })->name('products.create');

    Route::get('products/{product}/edit', function ($product) {
        return Inertia::render('Products/FormPage', [
            'mode' => 'edit',
            'product_id' => (int) $product,
        ]);
    })->name('products.edit');
});

// Categories routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('categories', function () {
        return Inertia::render('Categories/Index');
    })->name('categories.index');

    Route::get('categories/create', function () {
        return Inertia::render('Categories/FormPage', [
            'mode' => 'create',
        ]);
    })->name('categories.create');

    Route::get('categories/{category}/edit', function (string $category) {
        return Inertia::render('Categories/FormPage', [
            'mode' => 'edit',
            'category_id' => (int) $category,
        ]);
    })->name('categories.edit');
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
    Route::get('warehouses', function () {
        return Inertia::render('Warehouses/Index');
    })->name('warehouses.index');

    Route::get('warehouses/create', function () {
        return Inertia::render('Warehouses/FormPage', [
            'mode' => 'create',
        ]);
    })->name('warehouses.create');

    Route::get('warehouses/{warehouse}/edit', function (string $warehouse) {
        return Inertia::render('Warehouses/FormPage', [
            'mode' => 'edit',
            'warehouse_id' => (int) $warehouse,
        ]);
    })->name('warehouses.edit');
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
    Route::get('purchases', function () {
        return Inertia::render('Purchases/Index');
    })->name('purchases.index');

    Route::get('purchases/create', function () {
        return Inertia::render('Purchases/FormPage', [
            'mode' => 'create',
        ]);
    })->name('purchases.create');

    Route::get('purchases/{purchase}/edit', function (string $purchase) {
        return Inertia::render('Purchases/FormPage', [
            'mode' => 'edit',
            'purchase_id' => (int) $purchase,
        ]);
    })->name('purchases.edit');
});

// Suppliers routes
Route::middleware(['auth'])->group(function () {
    Route::get('suppliers', function () {
        return Inertia::render('Suppliers/Index');
    })->name('suppliers.index');

    Route::get('suppliers/create', function () {
        return Inertia::render('Suppliers/FormPage', [
            'mode' => 'create',
        ]);
    })->name('suppliers.create');

    Route::get('suppliers/{supplier}/edit', function (string $supplier) {
        return Inertia::render('Suppliers/FormPage', [
            'mode' => 'edit',
            'supplier_id' => (int) $supplier,
        ]);
    })->name('suppliers.edit');
});

// Sales routes
Route::middleware(['auth'])->group(function () {
    Route::get('sales', function () {
        return Inertia::render('Sales/Index');
    })->name('sales.index');

    Route::get('sales/create', function () {
        return Inertia::render('Sales/FormPage', [
            'mode' => 'create',
        ]);
    })->name('sales.create');

    Route::get('sales/{sale}/edit', function (string $sale) {
        return Inertia::render('Sales/FormPage', [
            'mode' => 'edit',
            'sale_id' => (int) $sale,
        ]);
    })->name('sales.edit');
});

// POS Configs routes
Route::middleware(['auth'])->group(function () {
    Route::get('pos-configs', function () {
        return Inertia::render('PosConfigs/Index');
    })->name('pos-configs.index');

    Route::get('pos-configs/create', function () {
        return Inertia::render('PosConfigs/FormPage', [
            'mode' => 'create',
        ]);
    })->name('pos-configs.create');

    Route::get('pos-configs/{posConfig}/edit', function (string $posConfig) {
        return Inertia::render('PosConfigs/FormPage', [
            'mode' => 'edit',
            'pos_config_id' => (int) $posConfig,
        ]);
    })->name('pos-configs.edit');

    Route::get('pos-configs/{posConfig}/sessions', [\App\Http\Controllers\PosConfigSessionsController::class, 'sessions'])
        ->name('pos-configs.sessions');
    Route::get('pos-configs/{posConfig}/sessions/{session}/orders', [\App\Http\Controllers\PosConfigSessionsController::class, 'sessionOrders'])
        ->name('pos-configs.sessions.orders');
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
    Route::get('pos/{session}/orders', [\App\Http\Controllers\Pos\PosController::class, 'orders'])->name('pos.orders');
    Route::post('pos/{session}/payment', [\App\Http\Controllers\Pos\PosController::class, 'payment'])->name('pos.payment');
    Route::get('pos/{session}/payment', function ($session) {
        return redirect()->route('pos.dashboard', ['session' => $session])
            ->with('info', 'Debes agregar productos al carrito antes de procesar el pago');
    })->name('pos.payment.redirect');
    Route::post('pos/{session}/process', [\App\Http\Controllers\Pos\PosController::class, 'processPayment'])->name('pos.process');
    Route::get('pos/{session}/close', [\App\Http\Controllers\Pos\PosController::class, 'close'])->name('pos.close');
    Route::post('pos/{session}/close', [\App\Http\Controllers\Pos\PosController::class, 'storeClose'])->name('pos.storeClose');

    Route::get('pos/{session}/refund', [\App\Http\Controllers\Pos\PosRefundController::class, 'index'])->name('pos.refund.index');
    Route::get('pos/{session}/refund/orders', [\App\Http\Controllers\Pos\PosRefundController::class, 'orders'])->name('pos.refund.orders');
    Route::get('pos/{session}/refund/sales/{sale}', [\App\Http\Controllers\Pos\PosRefundController::class, 'origin'])->name('pos.refund.origin');
    Route::post('pos/{session}/refund/lookup', [\App\Http\Controllers\Pos\PosRefundController::class, 'lookupSale'])->name('pos.refund.lookup');
    Route::post('pos/{session}/refund/preview', [\App\Http\Controllers\Pos\PosRefundController::class, 'preview'])->name('pos.refund.preview');
    Route::post('pos/{session}/refund/process', [\App\Http\Controllers\Pos\PosRefundController::class, 'process'])->name('pos.refund.process');

    // POS API endpoints
    Route::get('api/pos/customers', [\App\Http\Controllers\Pos\PosController::class, 'apiCustomers'])->name('api.pos.customers');
    Route::post('pos/{session}/customers/lookup', [\App\Http\Controllers\Pos\PosController::class, 'apiPartnerLookup'])->name('pos.customers.lookup');
    Route::post('pos/{session}/customers/upsert', [\App\Http\Controllers\Pos\PosController::class, 'apiUpsertCustomer'])->name('pos.customers.upsert');
    Route::get('pos/{session}/credit-notes/{partner}', [\App\Http\Controllers\Pos\PosController::class, 'getCreditNotes'])->name('pos.credit-notes');
});
