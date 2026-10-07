<?php

use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\LocationController;
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
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:system.activity_logs.view');

    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])
        ->middleware('permission:system.activity_logs.view');


    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('permission:system.settings.view');

    Route::get('/settings/system', [SettingsController::class, 'system'])
        ->middleware('permission:system.settings.view');

    Route::get('/settings/{group}', [SettingsController::class, 'group'])
        ->middleware('permission:system.settings.view');

    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:system.settings.update');



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
    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    */

    Route::get('/locations', [LocationController::class, 'index'])
        ->middleware('permission:system.locations.view');

    Route::get('/locations/{location}', [LocationController::class, 'show'])
        ->middleware('permission:system.locations.view');

    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('permission:system.locations.create');

    Route::put('/locations/{location}', [LocationController::class, 'update'])
        ->middleware('permission:system.locations.update');

    Route::patch('/locations/{location}/activate', [LocationController::class, 'activate'])
        ->middleware('permission:system.locations.manage');

    Route::patch('/locations/{location}/deactivate', [LocationController::class, 'deactivate'])
        ->middleware('permission:system.locations.manage');
});
