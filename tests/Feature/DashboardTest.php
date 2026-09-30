<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createDashboardFixture(): array
{
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $role = Role::create([
        'name' => 'Dashboard Test Role',
        'slug' => 'dashboard-test-role-'.uniqid(),
        'description' => 'Temporary role for dashboard feature tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $module = Module::create([
        'name' => 'Dashboard Test Module',
        'slug' => 'dashboard-test-module-'.uniqid(),
        'description' => 'Temporary module for dashboard feature tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'View Dashboard Test',
        'slug' => 'dashboard-test.view.'.uniqid(),
        'description' => 'Temporary permission for dashboard feature tests.',
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

test('guest cannot access dashboard', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated user can access dashboard', function () {
    $fixture = createDashboardFixture();

    $response = $this
        ->actingAs($fixture['user'])
        ->get(route('dashboard'));

    $response->assertSuccessful();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('user')
            ->where('user.id', $fixture['user']->id)
            ->where('user.name', $fixture['user']->name)
            ->where('user.email', $fixture['user']->email)
            ->has('roles')
            ->has('capabilities'),
    );
});

test('dashboard exposes only active roles', function () {
    $fixture = createDashboardFixture();

    $inactiveRole = Role::create([
        'name' => 'Inactive Dashboard Role',
        'slug' => 'inactive-dashboard-role-'.uniqid(),
        'description' => 'Inactive role that must not appear on dashboard.',
        'is_system' => false,
        'is_active' => false,
    ]);

    $fixture['user']->roles()->attach($inactiveRole->id);

    $response = $this
        ->actingAs($fixture['user'])
        ->get(route('dashboard'));

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Dashboard')
            ->has(
                'roles',
                1,
            )
            ->where(
                'roles.0.id',
                $fixture['role']->id,
            ),
    );
});

test('dashboard capability is false when permission is unavailable', function () {
    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('capabilities.canManageUsers', false)
            ->where('capabilities.canManageRoles', false)
            ->where('capabilities.canViewReports', false),
    );
});
