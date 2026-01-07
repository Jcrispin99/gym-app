<?php

use App\Http\Controllers\Api\ProductApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Products API
    Route::get('/products/search', [ProductApiController::class, 'search']);
    Route::get('/products/{id}', [ProductApiController::class, 'show']);
});
