<?php

use App\Models\User;
use App\Services\AccountSecurityService;
use Illuminate\Support\Carbon;

test('active user with no lock can authenticate', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'locked_until' => null,
    ]);

    $service = app(AccountSecurityService::class);

    expect($service->canAuthenticate($user))->toBeTrue();
});

test('inactive user cannot authenticate', function () {
    $user = User::factory()->create([
        'status' => 'inactive',
        'locked_until' => null,
    ]);

    $service = app(AccountSecurityService::class);

    expect($service->canAuthenticate($user))->toBeFalse();
});

test('user with future lock cannot authenticate', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'locked_until' => Carbon::now()->addMinutes(10),
    ]);

    $service = app(AccountSecurityService::class);

    expect($service->isLocked($user))->toBeTrue()
        ->and($service->canAuthenticate($user))->toBeFalse();
});

test('expired lock does not prevent authentication', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'locked_until' => Carbon::now()->subMinute(),
    ]);

    $service = app(AccountSecurityService::class);

    expect($service->isLocked($user))->toBeFalse()
        ->and($service->canAuthenticate($user))->toBeTrue();
});

test('failed login increments failure counter', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'failed_login_attempts' => 0,
        'locked_until' => null,
    ]);

    $service = app(AccountSecurityService::class);

    $service->recordFailedLogin($user);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(1)
        ->and($user->locked_until)->toBeNull();
});

test('fifth failed login temporarily locks account', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'failed_login_attempts' => 4,
        'locked_until' => null,
    ]);

    $service = app(AccountSecurityService::class);

    $service->recordFailedLogin($user);

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(5)
        ->and($user->locked_until)->not->toBeNull()
        ->and($service->isLocked($user))->toBeTrue();
});

test('successful login resets security counters and records login metadata', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'failed_login_attempts' => 4,
        'locked_until' => Carbon::now()->addMinutes(10),
        'last_login_at' => null,
        'last_login_ip' => null,
    ]);

    $service = app(AccountSecurityService::class);

    $service->recordSuccessfulLogin($user, '127.0.0.1');

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull()
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->toBe('127.0.0.1');
});

test('failed login increments use the latest locked database counter instead of a stale model', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'failed_login_attempts' => 3,
        'locked_until' => null,
    ]);
    $staleUser = $user->fresh();

    $user->forceFill(['failed_login_attempts' => 4])->save();

    app(AccountSecurityService::class)->recordFailedLogin($staleUser);

    expect($user->fresh()->failed_login_attempts)->toBe(5)
        ->and($user->fresh()->locked_until)->not->toBeNull();
});
