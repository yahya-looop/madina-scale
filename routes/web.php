<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PublicCmsPageController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\StorefrontCartController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Storefront
|--------------------------------------------------------------------------
|
| Public storefront catalogue, session cart and phone-first order placement.
| Orders remain pending until an official executive confirms them.
|
*/

Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');

Route::get('/', [StorefrontController::class, 'index'])->name('storefront.home');
Route::get('/products', [StorefrontController::class, 'index'])->name('storefront.products.index');
Route::get('/shop', [StorefrontController::class, 'legacyIndex'])->name('storefront.index');
Route::get('/cart', [StorefrontCartController::class, 'show'])->name('storefront.cart');
Route::post('/cart/items', [StorefrontCartController::class, 'add'])->middleware('throttle:60,1')->name('storefront.cart.add');
Route::patch('/cart/items/{variant}', [StorefrontCartController::class, 'update'])->whereNumber('variant')->name('storefront.cart.update');
Route::delete('/cart/items/{variant}', [StorefrontCartController::class, 'remove'])->whereNumber('variant')->name('storefront.cart.remove');
Route::post('/checkout', [StorefrontCartController::class, 'checkout'])->middleware('throttle:10,1')->name('storefront.checkout');
Route::get('/order-success', [StorefrontCartController::class, 'success'])->name('storefront.order-success');
Route::get('/shop/flash-sales', fn () => Inertia::render('Storefront/FlashSales'))
    ->name('storefront.flash-sales');
Route::get('/pages/{slug}', [PublicCmsPageController::class, 'show'])->where('slug', '[A-Za-z0-9\-]+')->name('storefront.pages.legacy');
// Keep the static legacy backend-create URL ahead of the dynamic public slug route.
Route::redirect('/products/create', '/backend/products/create', 301);

Route::get('/products/{slug}', [StorefrontController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('storefront.show');

Route::get('/shop/{slug}', fn (string $slug) => redirect()->route('storefront.show', ['slug' => $slug], 301))
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('storefront.legacy-show');

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
|
| Routes available only to unauthenticated users.
|
*/

require __DIR__.'/backend.php';

// -------------------------------------------------------------------------
// Legacy URL compatibility and public CMS slugs
// -------------------------------------------------------------------------

// Safe, non-conflicting legacy staff page URLs. The conflicting /products
// path is intentionally owned by the public storefront and is never
// redirected to backend product management.
$legacyBackendPages = [
    '/dashboard' => '/backend/dashboard',
    '/content/pages' => '/backend/content/pages',
    '/content/media' => '/backend/content/media',
    '/content/banners' => '/backend/content/banners',
    '/content/sections' => '/backend/content/sections',
    '/seo' => '/backend/seo',
    '/marketing' => '/backend/marketing',
    '/analytics' => '/backend/analytics',
    '/reports' => '/backend/reports',
    '/product-categories' => '/backend/product-categories',
    '/brands' => '/backend/brands',
    '/units' => '/backend/units',
    '/attributes' => '/backend/attributes',
    '/sales-crm/leads' => '/backend/sales-crm/leads',
    '/b2b' => '/backend/b2b',
    '/pricing' => '/backend/pricing',
    '/flash-sales' => '/backend/flash-sales',
    '/customers' => '/backend/customers',
    '/orders' => '/backend/orders',
    '/sales-returns' => '/backend/sales-returns',
    '/shipping-delivery' => '/backend/shipping-delivery',
    '/warranty-service' => '/backend/warranty-service',
    '/payments' => '/backend/payments',
    '/procurement' => '/backend/procurement',
    '/finance' => '/backend/finance',
    '/inventory' => '/backend/inventory',
    '/inventory/reservations' => '/backend/inventory/reservations',
    '/inventory/low-stock' => '/backend/inventory/low-stock',
    '/inventory/movements' => '/backend/inventory/movements',
    '/inventory/operations' => '/backend/inventory/operations',
    '/locations' => '/backend/locations',
    '/developer/api-providers' => '/backend/developer/api-providers',
    '/developer/console' => '/backend/developer/console',
    '/developer/feature-flags' => '/backend/developer/feature-flags',
    '/developer/menu-registry' => '/backend/developer/menu-registry',
    '/developer/modules' => '/backend/developer/modules',
    '/settings/roles-permissions' => '/backend/settings/roles-permissions',
    '/settings/activity-logs' => '/backend/settings/activity-logs',
    '/settings/system' => '/backend/settings/system',
    '/users' => '/backend/users',
];
foreach ($legacyBackendPages as $legacy => $backend) {
    Route::redirect($legacy, $backend, 301);
}

Route::get('/products/{product}/edit', fn (string $product) => redirect('/backend/products/'.$product.'/edit', 301))
    ->whereNumber('product');

// Legacy staff authentication URLs. The named login/dashboard routes now
// generate the /backend URLs; these aliases preserve existing bookmarks.
Route::get('/login', fn () => redirect()->route('login', [], 302))->name('login.legacy');
Route::post('/login', [LoginController::class, 'store'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('login.store.legacy');
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout.legacy');

// Legacy nested staff page URLs.
Route::get('/marketing/{view}', fn (string $view) => redirect('/backend/marketing/'.$view, 301))
    ->whereIn('view', ['campaigns', 'coupons']);
Route::get('/analytics/{view}', fn (string $view) => redirect('/backend/analytics/'.$view, 301))
    ->whereIn('view', ['sales', 'website', 'marketing']);

Route::get('/attributes/{attribute}/values', fn (string $attribute) => redirect('/backend/attributes/'.$attribute.'/values', 301))
    ->whereNumber('attribute');
Route::get('/locations/{location}/edit', fn (string $location) => redirect('/backend/locations/'.$location.'/edit', 301))
    ->whereNumber('location');
Route::get('/locations/{location}', fn (string $location) => redirect('/backend/locations/'.$location, 301))
    ->whereNumber('location');
Route::get('/users/create', fn () => redirect('/backend/users/create', 301));
Route::get('/users/{user}/edit', fn (string $user) => redirect('/backend/users/'.$user.'/edit', 301))
    ->whereNumber('user');
Route::get('/users/{user}', fn (string $user) => redirect('/backend/users/'.$user, 301))
    ->whereNumber('user');

// Root-level public CMS slugs. Reserved application paths are excluded so
// CMS content cannot shadow backend, API, commerce or infrastructure routes.
Route::get('/{slug}', [PublicCmsPageController::class, 'show'])
    ->where('slug', '(?!(?:backend|api|up|images|storage|build|assets|fonts|products|cart|checkout|account|shop|pages|order-success|flash-sales|login|logout|dashboard|content|seo|marketing|analytics|reports|product-categories|brands|units|attributes|sales-crm|b2b|pricing|customers|orders|sales-returns|shipping-delivery|warranty-service|payments|procurement|finance|inventory|locations|developer|settings|users|robots\\.txt|sitemap\\.xml)$)[A-Za-z0-9-]+')
    ->name('storefront.pages.show');
