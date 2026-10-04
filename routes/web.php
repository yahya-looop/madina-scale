<?php

use App\Http\Controllers\Access\RolesPermissionsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/settings/roles-permissions', RolesPermissionsController::class)
        ->name('roles-permissions');

    /*
    |--------------------------------------------------------------------------
    | User Management Pages
    |--------------------------------------------------------------------------
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
