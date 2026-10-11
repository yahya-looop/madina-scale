<?php

use Illuminate\Support\Facades\Route;

test('login endpoint has a five requests per minute rate limit', function () {
    $route = Route::getRoutes()->getByName('login.store');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:5,1');
});

test('login endpoint returns 429 after the rate limit is exceeded', function () {
    // Use a unique IP so this test does not depend on another test's limiter state.
    $ip = '192.0.2.10';

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.store'), [
                'email' => 'rate-limit-test@example.com',
                'password' => 'wrong-password',
            ]);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post(route('login.store'), [
            'email' => 'rate-limit-test@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertTooManyRequests();
});
