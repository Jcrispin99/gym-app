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

// Members (Customers) routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('members', \App\Http\Controllers\MemberController::class);
    Route::post('members/{member}/activate-portal', [\App\Http\Controllers\MemberController::class, 'activatePortal'])
        ->name('members.activate-portal');
});

// Membership Plans routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::resource('membership-plans', \App\Http\Controllers\MembershipPlanController::class)->parameters([
        'membership-plans' => 'membershipPlan'
    ]);
    Route::post('membership-plans/{membershipPlan}/toggle-status', [\App\Http\Controllers\MembershipPlanController::class, 'toggleStatus'])
        ->name('membership-plans.toggle-status');
    Route::get('membership-plans/{membershipPlan}/activity-log', [\App\Http\Controllers\MembershipPlanController::class, 'activityLog'])
        ->name('membership-plans.activity-log');
});

// Subscriptions routes
Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('subscriptions', [\App\Http\Controllers\SubscriptionController::class, 'store'])
        ->name('subscriptions.store');
    Route::post('subscriptions/{subscription}/freeze', [\App\Http\Controllers\SubscriptionController::class, 'freeze'])
        ->name('subscriptions.freeze');
    Route::post('subscriptions/{subscription}/unfreeze', [\App\Http\Controllers\SubscriptionController::class, 'unfreeze'])
        ->name('subscriptions.unfreeze');
    Route::delete('subscriptions/{subscription}', [\App\Http\Controllers\SubscriptionController::class, 'destroy'])
        ->name('subscriptions.destroy');
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

require __DIR__.'/settings.php';
