<?php

use App\Http\Controllers\Access\RolesPermissionsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
|
| Routes available only to unauthenticated users.
|
*/

Route::middleware('guest')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
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
