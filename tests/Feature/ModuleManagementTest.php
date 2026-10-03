<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Create a permission fixture for module-management tests.
 *
 * The permission slug must match the permission required
 * by the production route exactly.
 */
function createModuleManagementPermission(
    string $name,
    string $slug,
): Permission {
    $permissionModule = Module::factory()->create([
        'name' => 'Module Management Test Permission Module',
        'slug' => 'module-management-permission-module-'.uniqid(),
        'is_system' => false,
        'is_active' => true,
    ]);

    return Permission::create([
        'module_id' => $permissionModule->id,
        'name' => $name,
        'slug' => $slug,
        'description' => 'Temporary permission for module management tests.',
    ]);
}

/**
 * Create a user with a specific module-management permission.
 */
function createModuleManagementUser(
    string $permissionName,
    string $permissionSlug,
): array {
    $user = User::factory()->create();

    $role = Role::factory()->create([
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = createModuleManagementPermission(
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

test('unauthenticated user cannot access modules', function () {
    $response = $this->getJson('/api/modules');

    $response->assertUnauthorized();
});

test('authenticated user without module view permission is forbidden', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->getJson('/api/modules');

    $response->assertForbidden();
});

test('user with module view permission can list modules', function () {
    $fixture = createModuleManagementUser(
        'View Modules',
        'system.modules.view',
    );

    Module::factory()->create([
        'name' => 'Visible Test Module',
        'slug' => 'visible-test-module',
        'description' => 'A module visible in the management list.',
        'sort_order' => 10,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson('/api/modules');

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

test('authenticated user without module create permission is forbidden', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/modules', [
            'name' => 'Unauthorized Module',
            'slug' => 'unauthorized-module',
        ]);

    $response->assertForbidden();
});

test('user with module create permission can create a module', function () {
    $fixture = createModuleManagementUser(
        'Create Modules',
        'system.modules.create',
    );

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/modules', [
            'name' => 'Operations Module',
            'slug' => 'operations-module',
            'description' => 'Operations management module.',
            'icon' => 'operations',
            'sort_order' => 20,
            'is_active' => true,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Operations Module',
        )
        ->assertJsonPath(
            'data.slug',
            'operations-module',
        );

    expect(
        Module::query()
            ->where('slug', 'operations-module')
            ->exists()
    )->toBeTrue();
});

test('duplicate module slug is rejected', function () {
    $fixture = createModuleManagementUser(
        'Create Modules',
        'system.modules.create',
    );

    Module::factory()->create([
        'name' => 'Existing Module',
        'slug' => 'existing-module',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/modules', [
            'name' => 'Another Module',
            'slug' => 'existing-module',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('module cannot be created with a negative sort order', function () {
    $fixture = createModuleManagementUser(
        'Create Modules',
        'system.modules.create',
    );

    $response = $this
        ->actingAs($fixture['user'])
        ->postJson('/api/modules', [
            'name' => 'Invalid Module',
            'slug' => 'invalid-module',
            'sort_order' => -1,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'sort_order',
        ]);
});

test('user with module view permission can view a single module', function () {
    $fixture = createModuleManagementUser(
        'View Modules',
        'system.modules.view',
    );

    $module = Module::factory()->create([
        'name' => 'Single Test Module',
        'slug' => 'single-test-module',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->getJson("/api/modules/{$module->id}");

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $module->id,
        )
        ->assertJsonPath(
            'data.slug',
            'single-test-module',
        );
});

test('authenticated user without module update permission is forbidden', function () {
    $user = User::factory()->create();

    $module = Module::factory()->create([
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->putJson("/api/modules/{$module->id}", [
            'name' => 'Unauthorized Update',
            'slug' => 'unauthorized-update',
        ]);

    $response->assertForbidden();
});

test('user with module update permission can update a module', function () {
    $fixture = createModuleManagementUser(
        'Update Modules',
        'system.modules.update',
    );

    $module = Module::factory()->create([
        'name' => 'Original Module',
        'slug' => 'original-module',
        'description' => 'Original description.',
        'icon' => 'original',
        'sort_order' => 10,
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/modules/{$module->id}", [
            'name' => 'Updated Module',
            'slug' => 'updated-module',
            'description' => 'Updated description.',
            'icon' => 'updated',
            'sort_order' => 20,
            'is_active' => false,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Updated Module',
        )
        ->assertJsonPath(
            'data.slug',
            'updated-module',
        )
        ->assertJsonPath(
            'data.is_active',
            false,
        );

    expect(
        $module->refresh()->name
    )->toBe('Updated Module');
});

test('duplicate module slug is rejected when updating a module', function () {
    $fixture = createModuleManagementUser(
        'Update Modules',
        'system.modules.update',
    );

    $module = Module::factory()->create([
        'name' => 'First Module',
        'slug' => 'first-module',
        'is_system' => false,
        'is_active' => true,
    ]);

    Module::factory()->create([
        'name' => 'Second Module',
        'slug' => 'second-module',
        'is_system' => false,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/modules/{$module->id}", [
            'name' => 'Updated First Module',
            'slug' => 'second-module',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

test('system module cannot be updated', function () {
    $fixture = createModuleManagementUser(
        'Update Modules',
        'system.modules.update',
    );

    $module = Module::factory()->create([
        'name' => 'System Module',
        'slug' => 'system-module',
        'is_system' => true,
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($fixture['user'])
        ->putJson("/api/modules/{$module->id}", [
            'name' => 'Attempted System Update',
            'slug' => 'attempted-system-update',
        ]);

    $response->assertForbidden();

    expect(
        $module->refresh()->name
    )->toBe('System Module');
});
