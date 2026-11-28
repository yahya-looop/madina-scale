<?php

use App\Http\Controllers\PublicSeoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public infrastructure routes
|--------------------------------------------------------------------------
|
| The customer-facing storefront is served by Nuxt. Laravel retains SEO
| endpoints and the staff ERP route surface.
|
*/

Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');

require __DIR__.'/backend.php';

// Legacy staff login URL; the canonical login page remains /backend/login.
Route::redirect('/login', '/backend/login', 301)->name('login.legacy');

Route::redirect('/products/create', '/backend/products/create', 301);

// -------------------------------------------------------------------------
// Legacy ERP URL compatibility
// -------------------------------------------------------------------------

// Legacy staff page aliases remain for compatibility; the canonical ERP
// routes live under /backend.
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

