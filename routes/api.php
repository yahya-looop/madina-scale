<?php

use App\Http\Controllers\Api\V1\HeadOfficeController;
use App\Http\Controllers\Api\V1\Storefront\CatalogController;
use App\Http\Controllers\Api\V1\Storefront\PublicOrderTrackingController;
use App\Http\Controllers\Api\V1\Storefront\PublicStorefrontSupportController;
use App\Http\Controllers\Api\V1\Storefront\CartController as StorefrontCartApiController;
use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\PublicCmsPageController;
use App\Http\Controllers\CmsContentController;
use App\Http\Controllers\OrderOperationsController;
use App\Http\Controllers\PricingPromotionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront API — public catalogue and session-backed customer endpoints
|--------------------------------------------------------------------------
|
| The /api/public/* endpoints remain as compatibility aliases. Nuxt uses
| the versioned /api/v1/storefront/* session-backed API.
|
*/
Route::middleware('web')->get('/public/flash-sales', [PricingPromotionController::class, 'publicFlashSales']);
Route::middleware('web')->get('/public/cms/banners', [CmsContentController::class, 'publicBanners']);

Route::prefix('v1/storefront')->middleware('web')->group(function (): void {
    Route::get('/csrf-token', fn (\Illuminate\Http\Request $request) => response()->json(['token' => csrf_token()]));
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/products', [CatalogController::class, 'products']);
    Route::get('/products/{slug}', [CatalogController::class, 'product'])->where('slug', '[A-Za-z0-9-]+');
    Route::get('/flash-sales', [PricingPromotionController::class, 'publicFlashSales']);
    Route::get('/cms/banners', [CmsContentController::class, 'publicBanners']);
    Route::get('/cms/pages/{slug}', [PublicCmsPageController::class, 'show'])->where('slug', '[A-Za-z0-9-]+');
    Route::get('/support/contact', [PublicStorefrontSupportController::class, 'contact']);
    Route::post('/orders/track', [PublicOrderTrackingController::class, 'track'])->middleware('throttle:5,1');

    // Customer session authentication for the Nuxt storefront.
    Route::prefix('auth')->group(function (): void {
        Route::get('/me', [CustomerAuthController::class, 'me']);
        Route::post('/login/password', [CustomerAuthController::class, 'passwordLogin'])->middleware('throttle:5,1');
        Route::post('/login/otp', [CustomerAuthController::class, 'sendLoginOtp'])->middleware('throttle:3,1');
        Route::post('/login/otp/verify', [CustomerAuthController::class, 'verifyLoginOtp'])->middleware('throttle:8,1');
        Route::post('/register/otp', [CustomerAuthController::class, 'sendRegistrationOtp'])->middleware('throttle:3,1');
        Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
    });
    Route::get('/account', [CustomerAuthController::class, 'account']);

    // Session-backed guest cart API for the Nuxt storefront. The web middleware
    // intentionally preserves Laravel session and CSRF behavior.
    Route::get('/cart', [StorefrontCartApiController::class, 'show']);
    Route::post('/cart/items', [StorefrontCartApiController::class, 'add'])->middleware('throttle:60,1');
    Route::patch('/cart/items/{variant}', [StorefrontCartApiController::class, 'update'])->whereNumber('variant');
    Route::delete('/cart/items/{variant}', [StorefrontCartApiController::class, 'remove'])->whereNumber('variant');
    Route::get('/delivery/quotes', [\App\Http\Controllers\StorefrontCartController::class, 'deliveryQuotes'])->middleware('throttle:60,1');
    Route::post('/checkout', [\App\Http\Controllers\StorefrontCartController::class, 'checkout'])->middleware('throttle:10,1');
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
