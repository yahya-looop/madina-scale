<?php

use App\Http\Controllers\Api\V1\HeadOfficeController;
use App\Http\Controllers\CmsContentController;
use App\Http\Controllers\OrderOperationsController;
use App\Http\Controllers\PricingPromotionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront API — public, read-only endpoints
|--------------------------------------------------------------------------
|
| The /api/public/* endpoints remain as compatibility aliases. New clients
| should use /api/v1/storefront/*.
|
*/
Route::middleware('web')->get('/public/flash-sales', [PricingPromotionController::class, 'publicFlashSales']);
Route::middleware('web')->get('/public/cms/banners', [CmsContentController::class, 'publicBanners']);

Route::prefix('v1/storefront')->middleware('web')->group(function (): void {
    Route::get('/flash-sales', [PricingPromotionController::class, 'publicFlashSales']);
    Route::get('/cms/banners', [CmsContentController::class, 'publicBanners']);
});

/*
|--------------------------------------------------------------------------
| Backend API — staff authenticated ERP endpoints
|--------------------------------------------------------------------------
|
| Existing /api/* routes are retained for backward compatibility. The
| canonical namespace for first-party clients is /api/v1/backend/*.
|
*/
require __DIR__.'/api/backend.php';

Route::prefix('v1/backend')->group(function (): void {
    require __DIR__.'/api/backend.php';
});

/*
|--------------------------------------------------------------------------
| Webhooks — provider callbacks
|--------------------------------------------------------------------------
|
| Courier providers continue to use the established URL while integrations
| migrate to /api/v1/webhooks/couriers/{provider}.
|
*/
Route::post('/webhooks/couriers/{provider}', [OrderOperationsController::class, 'courierWebhook'])
    ->middleware('throttle:120,1');

Route::prefix('v1/webhooks')->group(function (): void {
    Route::post('/couriers/{provider}', [OrderOperationsController::class, 'courierWebhook'])
        ->middleware('throttle:120,1');
});

/*
|--------------------------------------------------------------------------
| Head Office ERP Integration API — v1 (existing external contract)
|--------------------------------------------------------------------------
|
| Kept at /api/v1/head-office/* to avoid changing the independently-issued
| Sanctum token contract and any external consumers.
|
*/
Route::prefix('v1/head-office')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/health', [HeadOfficeController::class, 'health'])
            ->middleware('abilities:head-office:health');

        Route::get('/catalog/products', [HeadOfficeController::class, 'products'])
            ->middleware('abilities:head-office:catalog:read');

        Route::get('/inventory/stocks', [HeadOfficeController::class, 'inventory'])
            ->middleware('abilities:head-office:inventory:read');

        Route::get('/sales/orders', [HeadOfficeController::class, 'orders'])
            ->middleware('abilities:head-office:orders:read');

        Route::get('/customers', [HeadOfficeController::class, 'customers'])
            ->middleware('abilities:head-office:customers:read');
    });
