<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a permission-management user with a specific permission.
 */
function createPermissionManagementUser(
    string $permissionName,
    string $permissionSlug,
): array {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $role = Role::factory()->create([
        'name' => 'Permission Management Test Role',
        'slug' => 'permission-management-test-role-'.uniqid(),
        'is_system' => false,
        'is_active' => true,
    ]);

    $module = Module::factory()->create([
        'name' => 'Permission Management Test Module',
        'slug' => 'permission-management-test-module-'.uniqid(),
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => $permissionName,
        'slug' => $permissionSlug,
        'description' => 'Temporary permission for permission management tests.',
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    return compact(
        'user',
        'role',
        'module',
        'permission',
    );
}

test('unauthenticated user cannot access permissions', function () {
    $response = $this->getJson('/api/v1/backend/permissions');

    $response->assertUnauthorized();
});

test('authenticated user without permission view permission is forbidden', function () {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson('/api/v1/backend/permissions');

    $response->assertForbidden();
});

test('user with permission view permission can list permissions', function () {
    $fixture = createPermissionManagementUser(
        'View Permissions',
        'system.permissions.view',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Visible Test Permission',
        'slug' => 'visible-test.permission',
        'description' => 'A permission visible in the management list.',
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson('/api/v1/backend/permissions');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data',
        ])
        ->assertJsonPath(
            'data.0.id',
            fn ($value) => is_int($value),
        );

    expect(
        collect($response->json('data'))
            ->pluck('id')
            ->contains($permission->id)
    )->toBeTrue();
});

test('authenticated user without permission create permission is forbidden', function () {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/api/v1/backend/permissions', [
            'module_id' => $module->id,
            'name' => 'Unauthorized Permission',
            'slug' => 'unauthorized.permission',
        ]);

    $response->assertForbidden();
});

test('user with permission create permission can create a permission', function () {
    $fixture = createPermissionManagementUser(
        'Create Permissions',
        'system.permissions.create',
    );

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/permissions', [
            'module_id' => $fixture['module']->id,
            'name' => 'Manage Roles',
            'slug' => 'system.roles.manage',
            'description' => 'Allows role management operations.',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Manage Roles',
        )
        ->assertJsonPath(
            'data.slug',
            'system.roles.manage',
        )
        ->assertJsonPath(
            'data.module.id',
            $fixture['module']->id,
        );

    expect(
        Permission::query()
            ->where('slug', 'system.roles.manage')
            ->exists()
    )->toBeTrue();
});

test('duplicate permission slug is rejected', function () {
    $fixture = createPermissionManagementUser(
        'Create Permissions',
        'system.permissions.create',
    );

    Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Existing Permission',
        'slug' => 'existing.permission',
        'description' => null,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/permissions', [
            'module_id' => $fixture['module']->id,
            'name' => 'Another Permission',
            'slug' => 'existing.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('permission cannot be created for an inactive module', function () {
    $fixture = createPermissionManagementUser(
        'Create Permissions',
        'system.permissions.create',
    );

    $inactiveModule = Module::factory()->create([
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/permissions', [
            'module_id' => $inactiveModule->id,
            'name' => 'Invalid Permission',
            'slug' => 'invalid.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'module_id',
        ]);
});

test('user with permission view permission can view a single permission', function () {
    $fixture = createPermissionManagementUser(
        'View Permissions',
        'system.permissions.view',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'View Single Permission',
        'slug' => 'view.single.permission',
        'description' => 'A single permission.',
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson("/api/v1/backend/permissions/{$permission->id}");

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $permission->id,
        )
        ->assertJsonPath(
            'data.name',
            'View Single Permission',
        )
        ->assertJsonPath(
            'data.module.id',
            $fixture['module']->id,
        );
});

test('authenticated user without permission update permission is forbidden', function () {
    $fixture = createPermissionManagementUser(
        'View Permissions',
        'system.permissions.view',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Existing Permission',
        'slug' => 'existing.permission',
        'description' => 'Existing permission.',
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/permissions/{$permission->id}", [
            'module_id' => $fixture['module']->id,
            'name' => 'Updated Permission',
            'slug' => 'updated.permission',
            'description' => 'Updated permission.',
        ]);

    $response->assertForbidden();
});

test('user with permission update permission can update a permission', function () {
    $fixture = createPermissionManagementUser(
        'Update Permissions',
        'system.permissions.update',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Old Permission',
        'slug' => 'old.permission',
        'description' => 'Old description.',
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/permissions/{$permission->id}", [
            'module_id' => $fixture['module']->id,
            'name' => 'Updated Permission',
            'slug' => 'updated.permission',
            'description' => 'Updated description.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $permission->id,
        )
        ->assertJsonPath(
            'data.name',
            'Updated Permission',
        )
        ->assertJsonPath(
            'data.slug',
            'updated.permission',
        )
        ->assertJsonPath(
            'data.description',
            'Updated description.',
        )
        ->assertJsonPath(
            'data.module.id',
            $fixture['module']->id,
        );

    expect($permission->refresh())
        ->name->toBe('Updated Permission');

    expect($permission->refresh())
        ->slug->toBe('updated.permission');

    expect($permission->refresh())
        ->description->toBe('Updated description.');
});

test('duplicate permission slug is rejected when updating a permission', function () {
    $fixture = createPermissionManagementUser(
        'Update Permissions',
        'system.permissions.update',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Permission To Update',
        'slug' => 'permission.to.update',
        'description' => null,
    ]);

    Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Existing Permission',
        'slug' => 'existing.permission',
        'description' => null,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/permissions/{$permission->id}", [
            'module_id' => $fixture['module']->id,
            'name' => 'Updated Permission',
            'slug' => 'existing.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('duplicate permission name is rejected when updating within the same module', function () {
    $fixture = createPermissionManagementUser(
        'Update Permissions',
        'system.permissions.update',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Permission To Update',
        'slug' => 'permission.to.update',
        'description' => null,
    ]);

    Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Existing Permission',
        'slug' => 'existing.permission',
        'description' => null,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/permissions/{$permission->id}", [
            'module_id' => $fixture['module']->id,
            'name' => 'Existing Permission',
            'slug' => 'updated.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
        ]);
});

test('permission cannot be updated to an inactive module', function () {
    $fixture = createPermissionManagementUser(
        'Update Permissions',
        'system.permissions.update',
    );

    $permission = Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Permission To Update',
        'slug' => 'permission.to.update',
        'description' => null,
    ]);

    $inactiveModule = Module::factory()->create([
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/permissions/{$permission->id}", [
            'module_id' => $inactiveModule->id,
            'name' => 'Updated Permission',
            'slug' => 'updated.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'module_id',
        ]);

    expect($permission->refresh())
        ->module_id->toBe($fixture['module']->id);
});

test('duplicate permission name is rejected when creating within the same module', function () {
    $fixture = createPermissionManagementUser(
        'Create Permissions',
        'system.permissions.create',
    );

    Permission::create([
        'module_id' => $fixture['module']->id,
        'name' => 'Existing Permission',
        'slug' => 'existing.permission',
        'description' => null,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/permissions', [
            'module_id' => $fixture['module']->id,
            'name' => 'Existing Permission',
            'slug' => 'another.permission',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
        ]);
});
