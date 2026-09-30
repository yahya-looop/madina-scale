<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

function createAuthorizationFixture(): array
{
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Authorization Test Role',
        'slug' => 'authorization-test-role-'.uniqid(),
        'description' => 'Temporary role for authorization feature tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $module = Module::create([
        'name' => 'Authorization Test Module',
        'slug' => 'authorization-test-module-'.uniqid(),
        'description' => 'Temporary module for authorization feature tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'View Authorization Test',
        'slug' => 'authorization-test.view.'.uniqid(),
        'description' => 'Temporary permission for authorization feature tests.',
    ]);

    return compact('user', 'role', 'module', 'permission');
}

test('user without permission is denied', function () {
    $fixture = createAuthorizationFixture();

    $user = $fixture['user'];
    $permission = $fixture['permission'];

    expect(
        Gate::forUser($user)->allows($permission->slug)
    )->toBeFalse();
});

test('user with assigned permission is allowed', function () {
    $fixture = createAuthorizationFixture();

    $user = $fixture['user'];
    $role = $fixture['role'];
    $permission = $fixture['permission'];

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    expect(
        Gate::forUser($user)->allows($permission->slug)
    )->toBeTrue();
});

test('inactive module denies its permission', function () {
    $fixture = createAuthorizationFixture();

    $user = $fixture['user'];
    $role = $fixture['role'];
    $module = $fixture['module'];
    $permission = $fixture['permission'];

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    $module->update([
        'is_active' => false,
    ]);

    expect(
        Gate::forUser($user)->allows($permission->slug)
    )->toBeFalse();
});

test('unknown permission is denied', function () {
    $user = User::factory()->create();

    expect(
        Gate::forUser($user)->allows('permission-that-does-not-exist')
    )->toBeFalse();
});

test('role without permission is denied', function () {
    $fixture = createAuthorizationFixture();

    $user = $fixture['user'];
    $role = $fixture['role'];
    $permission = $fixture['permission'];

    $user->roles()->attach($role->id);

    expect(
        Gate::forUser($user)->allows($permission->slug)
    )->toBeFalse();
});
