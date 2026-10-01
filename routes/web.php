<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

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
| All application management routes require an authenticated user.
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
    | Dashboard access requires authentication.
    | Dashboard capabilities are resolved by the dashboard layer.
    |
    */

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Role Management
    |--------------------------------------------------------------------------
    |
    | Available permissions:
    |
    | - system.roles.view
    | - system.roles.create
    | - system.roles.update
    | - system.roles.delete
    | - system.roles.manage_permissions
    |
    | System roles are protected by the request/service layer.
    |
    */

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:system.roles.view')
        ->name('roles.index');

    Route::get('/roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:system.roles.view')
        ->name('roles.show');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:system.roles.create')
        ->name('roles.store');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:system.roles.update')
        ->name('roles.update');

    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
        ->middleware('permission:system.roles.manage_permissions')
        ->name('roles.permissions.sync');

    /*
    |--------------------------------------------------------------------------
    | Permission Management
    |--------------------------------------------------------------------------
    |
    | Available permissions:
    |
    | - system.permissions.view
    | - system.permissions.create
    | - system.permissions.update
    | - system.permissions.delete
    |
    | Permission update is protected by the dedicated update permission.
    |
    */

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:system.permissions.view')
        ->name('permissions.index');

    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
        ->middleware('permission:system.permissions.view')
        ->name('permissions.show');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:system.permissions.create')
        ->name('permissions.store');

    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:system.permissions.update')
        ->name('permissions.update');

    /*
    |--------------------------------------------------------------------------
    | Module Management
    |--------------------------------------------------------------------------
    |
    | Available permissions:
    |
    | - system.modules.view
    | - system.modules.create
    | - system.modules.update
    | - system.modules.delete
    |
    | System modules are protected by the request/service layer.
    |
    */

    Route::get('/modules', [ModuleController::class, 'index'])
        ->middleware('permission:system.modules.view')
        ->name('modules.index');

    Route::get('/modules/{module}', [ModuleController::class, 'show'])
        ->middleware('permission:system.modules.view')
        ->name('modules.show');

    Route::post('/modules', [ModuleController::class, 'store'])
        ->middleware('permission:system.modules.create')
        ->name('modules.store');

    Route::put('/modules/{module}', [ModuleController::class, 'update'])
        ->middleware('permission:system.modules.update')
        ->name('modules.update');
});
