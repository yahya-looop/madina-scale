<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertUnauthorized();
    }

    public function test_user_without_user_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_user_with_user_view_permission_can_list_users(): void
    {
        $user = User::factory()->create();

        $this->grantPermission($user, 'system.users.view');

        User::factory()->create([
            'name' => 'Managed User',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_user_without_user_create_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        $response->assertForbidden();
    }

    public function test_user_with_user_create_permission_can_create_user(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.create');

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'New User',
            )
            ->assertJsonPath(
                'data.email',
                'new@example.com',
            );

        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'status' => 'active',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.create');

        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Another User',
                'email' => 'existing@example.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_user_can_be_viewed_individually(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.view');

        $managedUser = User::factory()->create([
            'name' => 'Managed User',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/users/{$managedUser->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Managed User',
            );
    }

    public function test_user_without_user_update_permission_is_forbidden(): void
    {
        $admin = User::factory()->create();

        $managedUser = User::factory()->create();

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$managedUser->id}", [
                'name' => 'Updated User',
                'email' => $managedUser->email,
            ]);

        $response->assertForbidden();
    }

    public function test_user_with_user_update_permission_can_update_user(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.update');

        $managedUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$managedUser->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Updated Name',
            )
            ->assertJsonPath(
                'data.email',
                'updated@example.com',
            );

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_user_password_can_be_updated(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.update');

        $managedUser = User::factory()->create([
            'password' => 'OldPassword123!',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/users/{$managedUser->id}", [
                'name' => $managedUser->name,
                'email' => $managedUser->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertOk();

        $managedUser->refresh();

        $this->assertTrue(
            Hash::check('NewPassword123!', $managedUser->password),
        );

        $this->assertNotNull(
            $managedUser->password_changed_at,
        );
    }

    public function test_user_roles_can_be_assigned_during_creation(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.create');

        $role = Role::query()->create([
            'name' => 'Sales Manager',
            'slug' => 'sales-manager',
            'description' => 'Sales management role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Sales User',
                'email' => 'sales@example.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'role_ids' => [$role->id],
            ]);

        $response->assertCreated();

        $createdUser = User::query()
            ->where('email', 'sales@example.com')
            ->firstOrFail();

        $this->assertTrue(
            $createdUser->roles()
                ->whereKey($role->id)
                ->exists(),
        );
    }

    public function test_system_role_cannot_be_assigned_during_user_creation(): void
    {
        $admin = User::factory()->create();

        $this->grantPermission($admin, 'system.users.create');

        $systemRole = Role::query()->create([
            'name' => 'Developer',
            'slug' => 'developer',
            'description' => 'System developer role.',
            'is_system' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'System User',
                'email' => 'system@example.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'role_ids' => [$systemRole->id],
            ]);

        $response->assertUnprocessable();
    }

    public function test_user_pages_require_the_matching_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();

        $managedUser = User::factory()->create();

        $this->get('/users/'.$managedUser->id)->assertForbidden();
        $this->get('/users/'.$managedUser->id.'/edit')->assertForbidden();
    }

    public function test_user_pages_allow_users_with_matching_permissions(): void
    {
        $viewer = User::factory()->create();
        $this->grantPermission($viewer, 'system.users.view');

        $this->actingAs($viewer)
            ->get('/users')
            ->assertOk();

        $managedUser = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/users/'.$managedUser->id)
            ->assertOk();

        $creator = User::factory()->create();
        $this->grantPermission($creator, 'system.users.create');

        $this->actingAs($creator)
            ->get('/users/create')
            ->assertOk();

        $editor = User::factory()->create();
        $this->grantPermission($editor, 'system.users.update');

        $this->actingAs($editor)
            ->get('/users/'.$managedUser->id.'/edit')
            ->assertOk();
    }

    public function test_database_seeder_does_not_create_predictable_developer_login(): void
    {
        $this->seed();

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    private function grantPermission(
        User $user,
        string $permissionSlug,
    ): void {
        $module = Module::query()->firstOrCreate(
            ['slug' => 'system'],
            [
                'name' => 'System',
                'description' => 'System module.',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        $permission = Permission::query()->firstOrCreate(
            [
                'module_id' => $module->id,
                'slug' => $permissionSlug,
            ],
            [
                'name' => str($permissionSlug)
                    ->afterLast('.')
                    ->replace('_', ' ')
                    ->title()
                    ->toString(),
                'description' => null,
                'is_system' => false,
                'is_active' => true,
            ],
        );

        $role = Role::query()->create([
            'name' => 'User Management Test Role',
            'slug' => 'user-management-test-role-'.uniqid(),
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $role->permissions()->sync([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
