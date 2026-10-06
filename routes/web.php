<?php

use App\Http\Controllers\Access\RolesPermissionsController;
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
| Routes that are accessible only to unauthenticated users.
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
| All authenticated application pages and operations live here.
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
    | Main authenticated dashboard page.
    |
    */

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Settings pages are rendered through Inertia.
    | Settings data is handled separately through API routes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Role & Permissions
    |--------------------------------------------------------------------------
    |
    | Dedicated page for managing roles and permissions.
    |
    */

    Route::get('/settings/roles-permissions', RolesPermissionsController::class)
        ->name('roles-permissions');


    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    |
    | Activity log page.
    |
    | Authorization is checked before rendering the page.
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
    | Dedicated page for system-level settings.
    |
    | IMPORTANT:
    | This is the Inertia page route.
    |
    | The actual settings data is loaded by System.vue
    | through the API endpoint:
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
    | User Management Pages
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
