<?php

use App\Models\User;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function authenticationServiceFixture(): array
{
    $user = User::factory()->create([
        'email' => 'auth-test@example.com',
        'password' => 'correct-password',
        'status' => 'active',
        'failed_login_attempts' => 0,
        'locked_until' => null,
        'last_login_at' => null,
        'last_login_ip' => null,
        'password_changed_at' => null,
    ]);

    return [$user, app(AuthenticationService::class)];
}

test('authenticates active user with valid credentials', function () {
    [$user, $service] = authenticationServiceFixture();

    $authenticatedUser = $service->authenticate(
        'auth-test@example.com',
        'correct-password',
        '127.0.0.1',
    );

    expect($authenticatedUser->is($user))->toBeTrue()
        ->and(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

test('rejects unknown email with generic credentials error', function () {
    [, $service] = authenticationServiceFixture();

    expect(fn () => $service->authenticate(
        'unknown@example.com',
        'correct-password',
    ))->toThrow(ValidationException::class);

    expect(Auth::check())->toBeFalse();
});

test('rejects incorrect password with generic credentials error', function () {
    [$user, $service] = authenticationServiceFixture();

    expect(fn () => $service->authenticate(
        $user->email,
        'wrong-password',
    ))->toThrow(ValidationException::class);

    expect($user->fresh()->failed_login_attempts)->toBe(1);
    expect(Auth::check())->toBeFalse();
});

test('does not authenticate inactive user', function () {
    [$user, $service] = authenticationServiceFixture();

    $user->forceFill([
        'status' => 'inactive',
    ])->save();

    expect($user->fresh()->status)->toBe('inactive');

    expect(fn () => $service->authenticate(
        $user->email,
        'correct-password',
    ))->toThrow(ValidationException::class);

    expect(Auth::check())->toBeFalse();
});

test('does not authenticate locked user', function () {
    [$user, $service] = authenticationServiceFixture();

    $lockedUntil = now()->addMinutes(10);

    $user->forceFill([
        'locked_until' => $lockedUntil,
    ])->save();

    expect($user->fresh()->locked_until->isFuture())->toBeTrue();

    expect(fn () => $service->authenticate(
        $user->email,
        'correct-password',
    ))->toThrow(ValidationException::class);

    expect(Auth::check())->toBeFalse();
});

test('successful authentication records login metadata', function () {
    [$user, $service] = authenticationServiceFixture();

    $authenticatedUser = $service->authenticate(
        $user->email,
        'correct-password',
        '192.168.1.100',
    );

    $freshUser = $authenticatedUser->fresh();

    expect($freshUser->last_login_at)->not->toBeNull()
        ->and($freshUser->last_login_ip)->toBe('192.168.1.100')
        ->and($freshUser->failed_login_attempts)->toBe(0)
        ->and($freshUser->locked_until)->toBeNull();
});

test('successful authentication clears previous failed login attempts', function () {
    [$user, $service] = authenticationServiceFixture();

    $user->forceFill([
        'failed_login_attempts' => 3,
    ])->save();

    $service->authenticate(
        $user->email,
        'correct-password',
    );

    expect($user->fresh()->failed_login_attempts)->toBe(0);
});

test('logout ends the authenticated session', function () {
    [$user, $service] = authenticationServiceFixture();

    $service->authenticate(
        $user->email,
        'correct-password',
    );

    expect(Auth::check())->toBeTrue();

    $service->logout();

    expect(Auth::check())->toBeFalse();
});
