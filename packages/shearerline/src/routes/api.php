<?php

use Illuminate\Support\Facades\Route;
use Shearerline\Http\Controllers\Api\DashboardController as ApiDashboardController;
use Shearerline\Http\Controllers\Api\ProductController as ApiProductController;
use Shearerline\Http\Controllers\Api\ProductCostController as ApiProductCostController;
use Shearerline\Http\Controllers\Api\SettlementController as ApiSettlementController;

Route::group([
    'prefix' => config('shearerline.api_route_prefix', 'api/shearerline'),
    'middleware' => config('shearerline.api_middleware', ['api']),
    'namespace' => 'Shearerline\Http\Controllers\Api',
], function () {
    Route::get('statistics', [ApiDashboardController::class, 'statistics'])->name('shearerline.api.statistics');

    Route::get('products/all', [ApiProductController::class, 'all'])->name('shearerline.api.products.all');
    Route::get('products/cost-types', [ApiProductController::class, 'costTypes'])->name('shearerline.api.products.costTypes');
    Route::get('products/grade-discounts', [ApiProductController::class, 'gradeDiscounts'])->name('shearerline.api.products.gradeDiscounts');
    Route::get('products/{product}/calculate-cost', [ApiProductController::class, 'calculateCost'])->name('shearerline.api.products.calculateCost');
    Route::get('products/{product}/calculate-cost-by-grade', [ApiProductController::class, 'calculateCostByGrade'])->name('shearerline.api.products.calculateCostByGrade');
    Route::post('products/batch-calculate-cost', [ApiProductController::class, 'batchCalculateCost'])->name('shearerline.api.products.batchCalculateCost');
    Route::post('products/batch-calculate-cost-by-grade', [ApiProductController::class, 'batchCalculateCostByGrade'])->name('shearerline.api.products.batchCalculateCostByGrade');
    Route::post('products/calculate-increased-cost', [ApiProductController::class, 'calculateIncreasedCost'])->name('shearerline.api.products.calculateIncreasedCost');
    Route::apiResource('products', ApiProductController::class, ['names' => 'shearerline.api.products']);

    Route::get('products/{product}/costs', [ApiProductCostController::class, 'index'])->name('shearerline.api.product-costs.index');
    Route::apiResource('product-costs', ApiProductCostController::class, ['names' => 'shearerline.api.product-costs'])->except(['index']);

    Route::get('settlements/types', [ApiSettlementController::class, 'types'])->name('shearerline.api.settlements.types');
    Route::post('settlements/calculate', [ApiSettlementController::class, 'calculate'])->name('shearerline.api.settlements.calculate');
    Route::post('settlements/{settlement}/confirm', [ApiSettlementController::class, 'confirm'])->name('shearerline.api.settlements.confirm');
    Route::post('settlements/{settlement}/settle', [ApiSettlementController::class, 'settle'])->name('shearerline.api.settlements.settle');
    Route::post('settlements/{settlement}/cancel', [ApiSettlementController::class, 'cancel'])->name('shearerline.api.settlements.cancel');
    Route::apiResource('settlements', ApiSettlementController::class, ['names' => 'shearerline.api.settlements']);
});
