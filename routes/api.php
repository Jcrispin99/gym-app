<?php

use App\Http\Controllers\Api\MembershipPlanApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductTemplateApiController;
use App\Http\Controllers\Api\AttributeApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\WarehouseApiController;
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
    Route::get('/product-templates', [ProductTemplateApiController::class, 'index']);
    Route::post('/product-templates', [ProductTemplateApiController::class, 'store']);
    Route::get('/product-templates/{productTemplate}', [ProductTemplateApiController::class, 'show']);
    Route::put('/product-templates/{productTemplate}', [ProductTemplateApiController::class, 'update']);
    Route::delete('/product-templates/{productTemplate}', [ProductTemplateApiController::class, 'destroy']);
    Route::post('/product-templates/{productTemplate}/toggle-status', [ProductTemplateApiController::class, 'toggleStatus']);

    // Membership Plans API
    Route::get('/pos/membership-plans', [MembershipPlanApiController::class, 'index']);

    // Warehouses API
    Route::get('/warehouses', [WarehouseApiController::class, 'index']);
    Route::post('/warehouses', [WarehouseApiController::class, 'store']);
    Route::get('/warehouses/{warehouse}', [WarehouseApiController::class, 'show']);
    Route::put('/warehouses/{warehouse}', [WarehouseApiController::class, 'update']);
    Route::delete('/warehouses/{warehouse}', [WarehouseApiController::class, 'destroy']);

    // Attributes API
    Route::get('/attributes', [AttributeApiController::class, 'index']);
    Route::post('/attributes', [AttributeApiController::class, 'store']);
    Route::get('/attributes/{attribute}', [AttributeApiController::class, 'show']);
    Route::put('/attributes/{attribute}', [AttributeApiController::class, 'update']);
    Route::delete('/attributes/{attribute}', [AttributeApiController::class, 'destroy']);
    Route::post('/attributes/{attribute}/toggle-status', [AttributeApiController::class, 'toggleStatus']);

    // Categories API
    Route::get('/categories', [CategoryApiController::class, 'index']);
    Route::post('/categories', [CategoryApiController::class, 'store']);
    Route::get('/categories/{category}', [CategoryApiController::class, 'show']);
    Route::put('/categories/{category}', [CategoryApiController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryApiController::class, 'destroy']);
    Route::post('/categories/{category}/toggle-status', [CategoryApiController::class, 'toggleStatus']);
});
