<?php

use App\Http\Controllers\Api\MembershipPlanApiController;
use App\Http\Controllers\Api\ProductApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {
    // Products API
    Route::get('/products/search', [ProductApiController::class, 'search']);
    Route::get('/products/{id}', [ProductApiController::class, 'show']);

    // Membership Plans API
    Route::get('/pos/membership-plans', [MembershipPlanApiController::class, 'index']);
});
