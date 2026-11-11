<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Create a permission fixture for role-management tests.
 *
 * The permission slug must match the permission required
 * by the production route exactly.
 */
function createRoleManagementPermission(
    string $name,
    string $slug,
): Permission {
    $module = Module::factory()->create([
        'name' => 'Role Management Test Module',
        'slug' => 'role-management-test-module-'.uniqid(),
        'is_active' => true,
    ]);

    return Permission::create([
        'module_id' => $module->id,
        'name' => $name,
        'slug' => $slug,
        'description' => 'Temporary permission for role management tests.',
    ]);
}

/**
 * Create a user with a specific role-management permission.
 */
function createRoleManagementUser(
    string $permissionName,
    string $permissionSlug,
): array {
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Role Management Test Role',
        'slug' => 'role-management-test-role-'.uniqid(),
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = createRoleManagementPermission(
        $permissionName,
        $permissionSlug,
    );

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    return compact(
        'user',
        'role',
        'permission',
    );
}

test('unauthenticated user cannot access roles', function () {
    $response = $this->getJson('/api/v1/backend/roles');

    $response->assertUnauthorized();
});

test('authenticated user without role view permission is forbidden', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/v1/backend/roles');

    $response->assertForbidden();
});

test('user with role view permission can list roles', function () {
    $fixture = createRoleManagementUser(
        'View Roles',
        'system.roles.view',
    );

    Role::create([
        'name' => 'Visible Test Role',
        'slug' => 'visible-test-role',
        'description' => 'A role visible in the management list.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson('/api/v1/backend/roles');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data',
        ])
        ->assertJsonPath(
            'data.0.name',
            fn ($value) => is_string($value),
        );
});

test('authenticated user without role create permission is forbidden', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/v1/backend/roles', [
            'name' => 'Unauthorized Role',
            'slug' => 'unauthorized-role',
        ]);

    $response->assertForbidden();
});

test('user with role create permission can create a role', function () {
    $fixture = createRoleManagementUser(
        'Create Roles',
        'system.roles.create',
    );

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/roles', [
            'name' => 'Operations Manager',
            'slug' => 'operations-manager',
            'description' => 'Operations management role.',
            'is_active' => true,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Operations Manager',
        )
        ->assertJsonPath(
            'data.slug',
            'operations-manager',
        );

    expect(
        Role::query()
            ->where('slug', 'operations-manager')
            ->exists()
    )->toBeTrue();
});

test('duplicate role slug is rejected', function () {
    $fixture = createRoleManagementUser(
        'Create Roles',
        'system.roles.create',
    );

    Role::create([
        'name' => 'Existing Role',
        'slug' => 'existing-role',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/v1/backend/roles', [
            'name' => 'Another Role',
            'slug' => 'existing-role',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('user with role view permission can view a single role', function () {
    $fixture = createRoleManagementUser(
        'View Roles',
        'system.roles.view',
    );

    $role = Role::create([
        'name' => 'Operations Manager',
        'slug' => 'operations-manager',
        'description' => 'Operations management role.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson("/api/v1/backend/roles/{$role->id}");

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $role->id,
        )
        ->assertJsonPath(
            'data.name',
            'Operations Manager',
        )
        ->assertJsonPath(
            'data.slug',
            'operations-manager',
        );
});

test('authenticated user without role update permission is forbidden', function () {
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Existing Role',
        'slug' => 'existing-role',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson("/api/v1/backend/roles/{$role->id}", [
            'name' => 'Updated Role',
            'slug' => 'updated-role',
        ]);

    $response->assertForbidden();
});

test('user with role update permission can update a role', function () {
    $fixture = createRoleManagementUser(
        'Update Roles',
        'system.roles.update',
    );

    $role = Role::create([
        'name' => 'Old Role Name',
        'slug' => 'old-role-name',
        'description' => 'Old description.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/roles/{$role->id}", [
            'name' => 'Updated Role Name',
            'slug' => 'updated-role-name',
            'description' => 'Updated description.',
            'is_active' => false,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $role->id,
        )
        ->assertJsonPath(
            'data.name',
            'Updated Role Name',
        )
        ->assertJsonPath(
            'data.slug',
            'updated-role-name',
        )
        ->assertJsonPath(
            'data.description',
            'Updated description.',
        )
        ->assertJsonPath(
            'data.is_active',
            false,
        );

    expect($role->refresh())
        ->name->toBe('Updated Role Name');

    expect($role->refresh())
        ->slug->toBe('updated-role-name');

    expect($role->refresh())
        ->is_active->toBeFalse();
});

test('duplicate role slug is rejected when updating a role', function () {
    $fixture = createRoleManagementUser(
        'Update Roles',
        'system.roles.update',
    );

    $role = Role::create([
        'name' => 'Role To Update',
        'slug' => 'role-to-update',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    Role::create([
        'name' => 'Existing Role',
        'slug' => 'existing-role',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/roles/{$role->id}", [
            'name' => 'Updated Role',
            'slug' => 'existing-role',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('system role cannot be updated', function () {
    $fixture = createRoleManagementUser(
        'Update Roles',
        'system.roles.update',
    );

    $systemRole = Role::create([
        'name' => 'Developer',
        'slug' => 'developer-system-test',
        'description' => 'Protected system role.',
        'is_system' => true,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/roles/{$systemRole->id}", [
            'name' => 'Modified Developer',
            'slug' => 'modified-developer',
        ]);

    $response->assertForbidden();

    expect($systemRole->refresh())
        ->name->toBe('Developer');

    expect($systemRole->refresh())
        ->slug->toBe('developer-system-test');
});

test('unauthenticated user cannot synchronize role permissions', function () {
    $role = Role::create([
        'name' => 'Permission Sync Role',
        'slug' => 'permission-sync-role',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->putJson("/api/v1/backend/roles/{$role->id}/permissions", [
            'permission_ids' => [],
        ]);

    $response->assertUnauthorized();
});

test('user without role permission management permission is forbidden', function () {
    $user = User::factory()->create();

    $role = Role::create([
        'name' => 'Permission Sync Role',
        'slug' => 'permission-sync-role',
        'description' => null,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson("/api/v1/backend/roles/{$role->id}/permissions", [
            'permission_ids' => [],
        ]);

    $response->assertForbidden();
});

test('user with role permission management permission can synchronize permissions', function () {
    $fixture = createRoleManagementUser(
        'Manage Role Permissions',
        'system.roles.manage_permissions',
    );

    $role = Role::create([
        'name' => 'Operations Manager',
        'slug' => 'operations-manager',
        'description' => 'Operations management role.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $permissionModule = Module::factory()->create([
        'name' => 'Operations Test Module',
        'slug' => 'operations-test-module-'.uniqid(),
        'is_active' => true,
    ]);

    $permissionOne = Permission::create([
        'module_id' => $permissionModule->id,
        'name' => 'View Operations',
        'slug' => 'operations.view-'.uniqid(),
        'description' => 'View operations.',
    ]);

    $permissionTwo = Permission::create([
        'module_id' => $permissionModule->id,
        'name' => 'Update Operations',
        'slug' => 'operations.update-'.uniqid(),
        'description' => 'Update operations.',
    ]);

    $role->permissions()->attach($permissionOne->id);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/roles/{$role->id}/permissions", [
            'permission_ids' => [
                $permissionTwo->id,
            ],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.0.id',
            $permissionTwo->id,
        );

    expect($role->refresh()->permissions()->pluck('id')->all())
        ->toBe([$permissionTwo->id]);
});

test('system role cannot have its permissions synchronized', function () {
    $fixture = createRoleManagementUser(
        'Manage Role Permissions',
        'system.roles.manage_permissions',
    );

    $systemRole = Role::create([
        'name' => 'System Role',
        'slug' => 'system-role-permission-test',
        'description' => 'Protected system role.',
        'is_system' => true,
        'is_active' => true,
    ]);

    $permissionModule = Module::factory()->create([
        'name' => 'System Role Permission Test Module',
        'slug' => 'system-role-permission-test-module-'.uniqid(),
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $permissionModule->id,
        'name' => 'Protected Permission',
        'slug' => 'protected.permission-'.uniqid(),
        'description' => 'Permission for protected role test.',
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/v1/backend/roles/{$systemRole->id}/permissions", [
            'permission_ids' => [
                $permission->id,
            ],
        ]);

    $response->assertForbidden();

    expect(
        $systemRole->refresh()->permissions()->exists()
    )->toBeFalse();
});
