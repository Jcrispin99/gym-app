<?php

use App\Http\Controllers\Api\MembershipPlanApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductTemplateApiController;
use App\Http\Controllers\Api\PurchaseApiController;
use App\Http\Controllers\Api\SaleApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\PosConfigApiController;
use App\Http\Controllers\Api\SupplierApiController;
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

    // Purchases API
    Route::get('/purchases', [PurchaseApiController::class, 'index']);
    Route::get('/purchases/form-options', [PurchaseApiController::class, 'formOptions']);
    Route::post('/purchases', [PurchaseApiController::class, 'store']);
    Route::get('/purchases/{purchase}', [PurchaseApiController::class, 'show']);
    Route::put('/purchases/{purchase}', [PurchaseApiController::class, 'update']);
    Route::delete('/purchases/{purchase}', [PurchaseApiController::class, 'destroy']);
    Route::post('/purchases/{purchase}/post', [PurchaseApiController::class, 'post']);
    Route::post('/purchases/{purchase}/cancel', [PurchaseApiController::class, 'cancel']);

    // Suppliers API
    Route::get('/suppliers', [SupplierApiController::class, 'index']);
    Route::get('/suppliers/form-options', [SupplierApiController::class, 'formOptions']);
    Route::post('/suppliers', [SupplierApiController::class, 'store']);
    Route::get('/suppliers/{supplier}', [SupplierApiController::class, 'show']);
    Route::put('/suppliers/{supplier}', [SupplierApiController::class, 'update']);
    Route::delete('/suppliers/{supplier}', [SupplierApiController::class, 'destroy']);

    // Customers API
    Route::get('/customers', [CustomerApiController::class, 'index']);
    Route::get('/customers/form-options', [CustomerApiController::class, 'formOptions']);
    Route::post('/customers', [CustomerApiController::class, 'store']);
    Route::get('/customers/{customer}', [CustomerApiController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerApiController::class, 'update']);
    Route::delete('/customers/{customer}', [CustomerApiController::class, 'destroy']);

    // POS Configs API
    Route::get('/pos-configs', [PosConfigApiController::class, 'index']);
    Route::get('/pos-configs/form-options', [PosConfigApiController::class, 'formOptions']);
    Route::post('/pos-configs', [PosConfigApiController::class, 'store']);
    Route::get('/pos-configs/{posConfig}', [PosConfigApiController::class, 'show']);
    Route::put('/pos-configs/{posConfig}', [PosConfigApiController::class, 'update']);
    Route::delete('/pos-configs/{posConfig}', [PosConfigApiController::class, 'destroy']);
    Route::post('/pos-configs/{posConfig}/toggle-status', [PosConfigApiController::class, 'toggleStatus']);

    // Sales API
    Route::get('/sales', [SaleApiController::class, 'index']);
    Route::get('/sales/form-options', [SaleApiController::class, 'formOptions']);
    Route::post('/sales', [SaleApiController::class, 'store']);
    Route::get('/sales/{sale}', [SaleApiController::class, 'show']);
    Route::put('/sales/{sale}', [SaleApiController::class, 'update']);
    Route::delete('/sales/{sale}', [SaleApiController::class, 'destroy']);
    Route::post('/sales/{sale}/post', [SaleApiController::class, 'post']);
    Route::post('/sales/{sale}/cancel', [SaleApiController::class, 'cancel']);
    Route::post('/sales/{sale}/credit-note', [SaleApiController::class, 'createCreditNote']);
    Route::post('/sales/{sale}/sunat/retry', [SaleApiController::class, 'retrySunat']);
});
