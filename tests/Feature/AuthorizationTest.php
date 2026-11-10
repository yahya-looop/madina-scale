<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
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

    return compact(
        'user',
        'role',
        'module',
        'permission',
    );
}

/**
 * Create a user assigned to the immutable Developer system role.
 *
 * @return array{user: User, role: Role}
 */
function createDeveloperAuthorizationFixture(
    string $userStatus = 'active',
    bool $roleActive = true,
): array {
    $user = User::factory()->create([
        'status' => $userStatus,
    ]);

    $role = Role::create([
        'name' => 'Developer',
        'slug' => 'developer',
        'description' => 'Developer system role for authorization tests.',
        'is_system' => true,
        'is_active' => $roleActive,
    ]);

    $user->roles()->attach($role->id);

    return compact('user', 'role');
}

test('user without permission is denied', function () {
    $fixture = createAuthorizationFixture();

    expect(
        Gate::forUser($fixture['user'])->allows($fixture['permission']->slug)
    )->toBeFalse();
});

test('user with assigned permission is allowed', function () {
    $fixture = createAuthorizationFixture();

    $fixture['role']->permissions()->attach($fixture['permission']->id);
    $fixture['user']->roles()->attach($fixture['role']->id);

    expect(
        Gate::forUser($fixture['user'])->allows($fixture['permission']->slug)
    )->toBeTrue();
});

test('inactive module denies its permission', function () {
    $fixture = createAuthorizationFixture();

    $fixture['role']->permissions()->attach($fixture['permission']->id);
    $fixture['user']->roles()->attach($fixture['role']->id);

    $fixture['module']->update(['is_active' => false]);

    expect(
        Gate::forUser($fixture['user'])->allows($fixture['permission']->slug)
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

    $fixture['user']->roles()->attach($fixture['role']->id);

    expect(
        Gate::forUser($fixture['user'])->allows($fixture['permission']->slug)
    )->toBeFalse();
});

test('inactive user is denied even with an assigned permission', function () {
    $fixture = createAuthorizationFixture();

    $fixture['role']->permissions()->attach($fixture['permission']->id);
    $fixture['user']->roles()->attach($fixture['role']->id);
    $fixture['user']->update(['status' => 'inactive']);

    expect(
        Gate::forUser($fixture['user'])->allows($fixture['permission']->slug)
    )->toBeFalse();
});

test('blank permission is denied by authorization service', function () {
    $user = User::factory()->create();
    $authorization = app(AuthorizationService::class);

    expect($authorization->can($user, ''))->toBeFalse();
    expect($authorization->can($user, '   '))->toBeFalse();
});

test('active developer can access a registered permission without role assignment', function () {
    $fixture = createAuthorizationFixture();
    $developer = createDeveloperAuthorizationFixture();

    $authorization = app(AuthorizationService::class);

    expect($authorization->isDeveloper($developer['user']))->toBeTrue();
    expect($authorization->can($developer['user'], $fixture['permission']->slug))->toBeTrue();
    expect(Gate::forUser($developer['user'])->allows($fixture['permission']->slug))->toBeTrue();
});

test('developer cannot access an unknown permission', function () {
    $developer = createDeveloperAuthorizationFixture();
    $authorization = app(AuthorizationService::class);

    expect(
        $authorization->can($developer['user'], 'permission-that-does-not-exist')
    )->toBeFalse();

    // system.* abilities are routed through AuthorizationService by Gate::before.
    expect(
        Gate::forUser($developer['user'])->allows('system.permission-that-does-not-exist')
    )->toBeFalse();
});

test('inactive developer user is not recognized and receives no permission bypass', function () {
    $fixture = createAuthorizationFixture();
    $developer = createDeveloperAuthorizationFixture('inactive');
    $authorization = app(AuthorizationService::class);

    expect($authorization->isDeveloper($developer['user']))->toBeFalse();
    expect($authorization->can($developer['user'], $fixture['permission']->slug))->toBeFalse();
    expect(Gate::forUser($developer['user'])->allows($fixture['permission']->slug))->toBeFalse();
});

test('inactive developer system role does not grant developer access', function () {
    $fixture = createAuthorizationFixture();
    $developer = createDeveloperAuthorizationFixture('active', false);
    $authorization = app(AuthorizationService::class);

    expect($authorization->isDeveloper($developer['user']))->toBeFalse();
    expect($authorization->can($developer['user'], $fixture['permission']->slug))->toBeFalse();
});

test('developer cannot bypass a disabled module', function () {
    $fixture = createAuthorizationFixture();
    $developer = createDeveloperAuthorizationFixture();

    $fixture['module']->update(['is_active' => false]);

    expect(
        app(AuthorizationService::class)->can($developer['user'], $fixture['permission']->slug)
    )->toBeFalse();
});

test('developer can read but cannot write while a module is locked', function () {
    $developer = createDeveloperAuthorizationFixture();

    $module = Module::create([
        'name' => 'Locked Authorization Test Module',
        'slug' => 'locked-authorization-test-module-'.uniqid(),
        'description' => 'Temporary locked module for authorization tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
        'state' => 'locked',
    ]);

    $readPermission = Permission::create([
        'module_id' => $module->id,
        'name' => 'View Locked Module',
        'slug' => 'locked-authorization-'.uniqid().'.view',
    ]);

    $writePermission = Permission::create([
        'module_id' => $module->id,
        'name' => 'Update Locked Module',
        'slug' => 'locked-authorization-'.uniqid().'.update',
    ]);

    $authorization = app(AuthorizationService::class);

    expect($authorization->can($developer['user'], $readPermission->slug))->toBeTrue();
    expect($authorization->can($developer['user'], $writePermission->slug))->toBeFalse();
});

test('canAll requires every permission', function () {
    $fixture = createAuthorizationFixture();

    $fixture['role']->permissions()->attach($fixture['permission']->id);
    $fixture['user']->roles()->attach($fixture['role']->id);

    $authorization = app(AuthorizationService::class);

    expect(
        $authorization->canAll($fixture['user'], [
            $fixture['permission']->slug,
            'permission-that-does-not-exist',
        ])
    )->toBeFalse();

    expect(
        $authorization->canAll($fixture['user'], [$fixture['permission']->slug])
    )->toBeTrue();

    expect($authorization->canAll($fixture['user'], []))->toBeTrue();
});

test('canAny requires at least one valid permission', function () {
    $fixture = createAuthorizationFixture();

    $fixture['role']->permissions()->attach($fixture['permission']->id);
    $fixture['user']->roles()->attach($fixture['role']->id);

    $authorization = app(AuthorizationService::class);

    expect(
        $authorization->canAny($fixture['user'], [
            'permission-that-does-not-exist',
            $fixture['permission']->slug,
        ])
    )->toBeTrue();

    expect(
        $authorization->canAny($fixture['user'], ['permission-that-does-not-exist'])
    )->toBeFalse();

    expect($authorization->canAny($fixture['user'], []))->toBeFalse();
});
