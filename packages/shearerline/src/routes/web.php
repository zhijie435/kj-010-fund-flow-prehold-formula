<?php

use Illuminate\Support\Facades\Route;
use Shearerline\Http\Controllers\DashboardController;
use Shearerline\Http\Controllers\ProductController;
use Shearerline\Http\Controllers\ProductCostController;
use Shearerline\Http\Controllers\SettlementController;

Route::group([
    'prefix' => config('shearerline.web_route_prefix', 'shearerline'),
    'middleware' => config('shearerline.web_middleware', ['web']),
    'namespace' => 'Shearerline\Http\Controllers',
], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('shearerline.dashboard');

    Route::resource('products', ProductController::class, ['names' => 'shearerline.products']);
    Route::get('products/{product}/costs', [ProductCostController::class, 'index'])->name('shearerline.product-costs.index');
    Route::resource('product-costs', ProductCostController::class, ['names' => 'shearerline.product-costs'])->except(['index']);

    Route::resource('settlements', SettlementController::class, ['names' => 'shearerline.settlements']);
});
