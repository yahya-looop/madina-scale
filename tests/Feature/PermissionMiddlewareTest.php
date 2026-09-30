<?php

use App\Http\Middleware\CheckPermission;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'permission:middleware-test.view'])
        ->get('/__tests__/permission-protected', function () {
            return response()->json([
                'message' => 'authorized',
            ]);
        })
        ->name('test.permission.protected');
});

function createMiddlewareFixture(): array
{
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Middleware Test Role',
        'slug' => 'middleware-test-role-'.uniqid(),
        'description' => 'Temporary role for middleware tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $module = Module::create([
        'name' => 'Middleware Test Module',
        'slug' => 'middleware-test-module-'.uniqid(),
        'description' => 'Temporary module for middleware tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'View Middleware Test',
        'slug' => 'middleware-test.view',
        'description' => 'Temporary permission for middleware tests.',
    ]);

    return compact('user', 'role', 'module', 'permission');
}

test('unauthenticated user cannot access permission protected route', function () {
    $response = $this->get('/__tests__/permission-protected');

    $response->assertUnauthorized();
});

test('authenticated user without permission receives forbidden response', function () {
    $fixture = createMiddlewareFixture();

    $this->actingAs($fixture['user']);

    $response = $this->get('/__tests__/permission-protected');

    $response->assertForbidden();
});

test('authenticated user with permission can access protected route', function () {
    $fixture = createMiddlewareFixture();

    $fixture['role']
        ->permissions()
        ->attach($fixture['permission']->id);

    $fixture['user']
        ->roles()
        ->attach($fixture['role']->id);

    $this->actingAs($fixture['user']);

    $response = $this->get('/__tests__/permission-protected');

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'authorized',
        ]);
});

test('inactive module blocks access even when permission is assigned', function () {
    $fixture = createMiddlewareFixture();

    $fixture['role']
        ->permissions()
        ->attach($fixture['permission']->id);

    $fixture['user']
        ->roles()
        ->attach($fixture['role']->id);

    $fixture['module']->update([
        'is_active' => false,
    ]);

    $this->actingAs($fixture['user']);

    $response = $this->get('/__tests__/permission-protected');

    $response->assertForbidden();
});
