<?php

use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:system.users.view');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:system.users.view');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:system.users.create');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:system.users.update');


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:system.roles.view');

    Route::get('/roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:system.roles.view');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:system.roles.create');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:system.roles.update');

    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
        ->middleware('permission:system.roles.manage_permissions');


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:system.permissions.view');

    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
        ->middleware('permission:system.permissions.view');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:system.permissions.create');

    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:system.permissions.update');


    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    Route::get('/modules', [ModuleController::class, 'index'])
        ->middleware('permission:system.modules.view');

    Route::get('/modules/{module}', [ModuleController::class, 'show'])
        ->middleware('permission:system.modules.view');

    Route::post('/modules', [ModuleController::class, 'store'])
        ->middleware('permission:system.modules.create');

    Route::put('/modules/{module}', [ModuleController::class, 'update'])
        ->middleware('permission:system.modules.update');
});
