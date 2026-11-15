<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use Illuminate\Support\Facades\Route;

Route::group([], function (): void {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('/login/password', [CustomerAuthController::class, 'passwordLogin'])
        ->middleware('throttle:5,1')->name('customer.login.password');
    Route::post('/login/otp', [CustomerAuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:3,1')->name('customer.login.otp.send');
    Route::post('/login/otp/verify', [CustomerAuthController::class, 'verifyLoginOtp'])
        ->middleware('throttle:8,1')->name('customer.login.otp.verify');
    Route::post('/register/otp', [CustomerAuthController::class, 'sendRegistrationOtp'])
        ->middleware('throttle:3,1')->name('customer.register.otp.send');
    Route::post('/register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:5,1')->name('customer.register');
});

Route::get('/account', [CustomerAuthController::class, 'account'])->name('customer.account');
Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
