<?php

use App\Http\Controllers\Access\RolesPermissionsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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

Route::get('/', [StorefrontController::class, 'index'])->name('storefront.home');
Route::get('/shop', [StorefrontController::class, 'index'])->name('storefront.index');
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
