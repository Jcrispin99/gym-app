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

require __DIR__.'/settings.php';
