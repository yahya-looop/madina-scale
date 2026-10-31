<?php

use App\Http\Controllers\Access\RolesPermissionsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\PublicCmsPageController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\UserPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public Storefront
|--------------------------------------------------------------------------
|
| Public, read-only product catalogue. Checkout and order processing will be
| added in a later phase and will continue to use the ERP inventory source.
|
*/

Route::get('/robots.txt', [PublicSeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [PublicSeoController::class, 'sitemap'])->name('seo.sitemap');

Route::get('/', [StorefrontController::class, 'index'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('storefront.index');
Route::get('/shop/flash-sales', fn () => Inertia::render('Storefront/FlashSales'))
    ->name('storefront.flash-sales');
Route::get('/pages/{slug}', [PublicCmsPageController::class, 'show'])->where('slug', '[A-Za-z0-9\-]+')->name('storefront.pages.show');
Route::get('/shop/{slug}', [StorefrontController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('storefront.show');


/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
|
| Routes available only to unauthenticated users.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| All authenticated application pages live inside this group.
|
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Main authenticated application dashboard.
    |
    */

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/content/pages', function () {
        abort_unless(request()->user()?->can('system.content.view'), 403);
        return Inertia::render('Content/Pages');
    })->name('content.pages');

    Route::get('/content/media', function () { abort_unless(request()->user()?->can('system.content.view'),403); return Inertia::render('Content/Media'); })->name('content.media');
    Route::get('/content/banners', function () { abort_unless(request()->user()?->can('system.content.view'),403); return Inertia::render('Content/Banners'); })->name('content.banners');
    Route::get('/content/sections', function () { abort_unless(request()->user()?->can('system.content.view'),403); return Inertia::render('Content/Sections'); })->name('content.sections');

    Route::get('/seo', function () { abort_unless(request()->user()?->can('system.seo.view'),403); return Inertia::render('Content/Seo'); })->name('seo.index');
    Route::get('/marketing', [\App\Http\Controllers\MarketingController::class, 'page'])->middleware('permission:system.marketing.view')->name('marketing.index');
    Route::get('/marketing/{view}', [\App\Http\Controllers\MarketingController::class, 'page'])->whereIn('view',['campaigns','coupons'])->middleware('permission:system.marketing.view')->name('marketing.view');
    Route::get('/analytics', [\App\Http\Controllers\BusinessAnalyticsController::class, 'page'])->middleware('permission:system.analytics.view')->name('analytics.index');
    Route::get('/analytics/{view}', [\App\Http\Controllers\BusinessAnalyticsController::class, 'page'])->whereIn('view',['sales','website','marketing'])->middleware('permission:system.analytics.view')->name('analytics.view');

    Route::get('/reports', [ReportController::class, 'page'])
        ->middleware('permission:system.reports.view')
        ->name('reports.index');

    /*
    |--------------------------------------------------------------------------
    | Product Master Data Pages
    |--------------------------------------------------------------------------
    |
    | Product categories, brands, and units are rendered through Inertia.
    | Their data is managed through protected API endpoints.
    |
    */

    Route::get('/product-categories', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.categories.view'),
            403,
        );

        return Inertia::render('ProductCategories/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('product-categories.index');

    Route::get('/brands', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.brands.view'),
            403,
        );

        return Inertia::render('Brands/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('brands.index');

    Route::get('/units', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.units.view'),
            403,
        );

        return Inertia::render('Units/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('units.index');

    /*
    |--------------------------------------------------------------------------
    | Product Attributes Pages
    |--------------------------------------------------------------------------
    */

    Route::get('/attributes', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.attributes.view'),
            403,
        );

        return Inertia::render('Attributes/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('attributes.index');

    Route::get('/attributes/{attribute}/values', function (int $attribute) {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.attributes.values.view'),
            403,
        );

        return Inertia::render('Attributes/Values', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'attributeId' => $attribute,
        ]);
    })->whereNumber('attribute')->name('attributes.values.index');

    /*
    |--------------------------------------------------------------------------
    | Product Management Pages
    |--------------------------------------------------------------------------
    |
    | Product pages are rendered through Inertia.
    | Product data is handled independently through the API endpoints.
    |
    */

    Route::get('/products', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.view'),
            403,
        );

        return Inertia::render('Products/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('products.index');

    Route::get('/products/create', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.create'),
            403,
        );

        return Inertia::render('Products/Create', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('products.create');

    Route::get('/products/{product}/edit', function (int $product) {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.update'),
            403,
        );

        return Inertia::render('Products/Edit', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'productId' => $product,
        ]);
    })->whereNumber('product')->name('products.edit');

    Route::get('/products/{product}', function (int $product) {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.products.view'),
            403,
        );

        return Inertia::render('Products/Show', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'productId' => $product,
        ]);
    })->whereNumber('product')->name('products.show');

    /*
    |--------------------------------------------------------------------------
    | Sales CRM — Lead Management
    |--------------------------------------------------------------------------
    */
    Route::get('/sales-crm/leads', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.crm.leads.view'), 403);
        return Inertia::render('SalesCRM/Leads/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('sales-crm.leads.index');

    /*
    |--------------------------------------------------------------------------
    | B2B & Wholesale Management
    |--------------------------------------------------------------------------
    */
    Route::get('/b2b', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.b2b.view'), 403);
        return Inertia::render('B2B/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('b2b.index');

    /*
    |--------------------------------------------------------------------------
    | Pricing & Promotions
    |--------------------------------------------------------------------------
    */
    Route::get('/pricing', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.pricing.view'), 403);
        return Inertia::render('Pricing/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('pricing.index');

    Route::get('/flash-sales', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.pricing.view'), 403);
        return Inertia::render('Pricing/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'initialTab' => 'flash',
        ]);
    })->name('flash-sales.index');

    /*
    |--------------------------------------------------------------------------
    | Customer Management
    |--------------------------------------------------------------------------
    */
    Route::get('/customers', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.customers.view'), 403);

        return Inertia::render('Customers/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('customers.index');

    /*
    |--------------------------------------------------------------------------
    | Orders Management
    |--------------------------------------------------------------------------
    */
    Route::get('/orders', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.orders.view'), 403);

        return Inertia::render('Orders/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('orders.index');


    /*
    |--------------------------------------------------------------------------
    | Accounts & Finance
    |--------------------------------------------------------------------------
    */
    Route::get('/sales-returns', function () {
        $user = request()->user();
        abort_unless($user !== null && $user->can('system.sales_returns.view'), 403);
        return Inertia::render('SalesReturns/Index', ['userName' => $user->name, 'userEmail' => $user->email]);
    })->name('sales-returns.index');

    Route::get('/shipping-delivery', [\App\Http\Controllers\ShippingDeliveryController::class, 'page'])->name('shipping-delivery.index');

    Route::get('/warranty-service', [\App\Http\Controllers\WarrantyServiceController::class, 'page'])
        ->name('warranty-service.index');

    Route::get('/payments', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.payments.view'), 403);
        return Inertia::render('Payments/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('payments.index');

    Route::get('/finance', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.finance.view'), 403);

        return Inertia::render('Finance/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('finance.index');

    /*
    |--------------------------------------------------------------------------
    | Inventory Pages
    |--------------------------------------------------------------------------
    */

    Route::get('/inventory', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.inventory.view'),
            403,
        );

        return Inertia::render('Inventory/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('inventory.index');

    Route::get('/inventory/reservations', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.inventory.view'), 403);

        return Inertia::render('Inventory/Reservations', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'canManageReservations' => $user->can('system.inventory.reservations.manage'),
        ]);
    })->name('inventory.reservations');

    Route::get('/inventory/low-stock', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.inventory.view'),
            403,
        );

        return Inertia::render('Inventory/LowStock', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('inventory.low-stock');

    Route::get('/inventory/movements', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.inventory.view'),
            403,
        );

        return Inertia::render('Inventory/Movements', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('inventory.movements');

    Route::get('/inventory/operations', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);
        abort_unless(
            $user->can('system.inventory.view'),
            403,
        );

        return Inertia::render('Inventory/Operations', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('inventory.operations');

    /*
    |--------------------------------------------------------------------------
    | Business Locations
    |--------------------------------------------------------------------------
    |
    | Business location pages are rendered through Inertia.
    | Location data itself is handled through protected API endpoints.
    |
    | Page authorization is intentionally aligned with the corresponding
    | location permission so users cannot open a page they cannot use.
    |
    */

    Route::get('/locations', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.locations.view'),
            403,
        );

        return Inertia::render('Locations/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('locations.index');

    Route::get('/locations/{location}/edit', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.locations.update'),
            403,
        );

        return Inertia::render('Locations/Edit', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('locations.edit');

    Route::get('/locations/{location}', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.locations.view'),
            403,
        );

        return Inertia::render('Locations/Show', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('locations.show');

    /*
    |--------------------------------------------------------------------------
    | Developer
    |--------------------------------------------------------------------------
    |
    | Developer-level platform management pages.
    |
    | Authorization is enforced server-side through the module
    | management permission. Developer users receive unrestricted
    | access through the application's AuthorizationService.
    |
    */

    Route::get('/developer/api-providers', function () {
        $user = request()->user();
        abort_unless($user !== null, 401);
        abort_unless($user->can('system.api_providers.view'), 403);
        return Inertia::render('Developer/ApiProviders', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('developer.api-providers');

    Route::get('/developer/modules', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.modules.view'),
            403,
        );

        return Inertia::render('Developer/Modules', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('developer.modules');

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Settings pages are rendered through Inertia.
    | Settings data is handled independently through API endpoints.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Role & Permissions
    |--------------------------------------------------------------------------
    |
    | Dedicated access-control management page.
    |
    */

    Route::get('/settings/roles-permissions', RolesPermissionsController::class)
        ->name('roles-permissions');

    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    |
    | Activity log management page.
    |
    | Authorization is checked before the Inertia page is rendered.
    |
    */

    Route::get('/settings/activity-logs', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.activity_logs.view'),
            403,
        );

        return Inertia::render('ActivityLogs/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('activity-logs');

    /*
    |--------------------------------------------------------------------------
    | System Settings
    |--------------------------------------------------------------------------
    |
    | Dedicated system settings page.
    |
    | The page itself is rendered through Inertia.
    | Settings data is loaded by:
    |
    |     GET /api/settings/system
    |
    */

    Route::get('/settings/system', function () {
        $user = request()->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $user->can('system.settings.view'),
            403,
        );

        return Inertia::render('Settings/System', [
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    })->name('settings.system');

    /*
    |--------------------------------------------------------------------------
    | User Management
    |--------------------------------------------------------------------------
    |
    | User management pages rendered through Inertia.
    |
    */

    Route::get('/users', [UserPageController::class, 'index'])
        ->name('users.index');

    Route::get('/users/create', [UserPageController::class, 'create'])
        ->name('users.create');

    Route::get('/users/{user}/edit', [UserPageController::class, 'edit'])
        ->name('users.edit');

    Route::get('/users/{user}', [UserPageController::class, 'show'])
        ->name('users.show');

});
