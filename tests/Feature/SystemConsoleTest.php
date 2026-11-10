<?php

use App\Models\ActivityLog;
use App\Models\FeatureFlag;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FeatureFlagService;
use App\Services\MenuRegistryService;
use Database\Seeders\FeatureFlagSeeder;
use Database\Seeders\MenuRegistrySeeder;
use Database\Seeders\SystemAuthorizationSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

function createSystemConsoleDeveloper(): User
{
    // Ensure the canonical permission catalog exists for permission-protected routes.
    app(SystemAuthorizationSeeder::class)->run();

    $user = User::factory()->create();
    $role = Role::query()
        ->where('slug', 'developer')
        ->where('is_system', true)
        ->where('is_active', true)
        ->firstOrFail();

    $user->roles()->syncWithoutDetaching([$role->id]);

    return $user;
}

function createSystemConsolePermission(Module $module, string $slug): Permission
{
    return Permission::query()->create([
        'module_id' => $module->id,
        'name' => $slug,
        'slug' => $slug,
        'description' => 'System console test permission.',
    ]);
}

test('developer can change module lifecycle state and the change is audited', function () {
    $developer = createSystemConsoleDeveloper();
    $module = Module::factory()->create([
        'slug' => 'test-module-state',
        'is_system' => true,
        'is_active' => true,
        'state' => 'enabled',
    ]);

    $this->actingAs($developer)
        ->patchJson("/api/modules/{$module->id}/state", ['state' => 'hidden'])
        ->assertOk()
        ->assertJsonPath('data.state', 'hidden');

    expect($module->fresh()->is_active)->toBeTrue()
        ->and($module->fresh()->state)->toBe('hidden');

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'module.state_changed',
        'module' => 'system',
        'subject_type' => Module::class,
        'subject_id' => $module->id,
    ]);
});

test('module state changes keep the legacy active flag synchronized', function () {
    $developer = createSystemConsoleDeveloper();
    $module = Module::factory()->create([
        'slug' => 'test-module-active-sync',
        'is_system' => false,
        'is_active' => true,
        'state' => 'enabled',
    ]);

    $this->actingAs($developer)
        ->patchJson("/api/modules/{$module->id}/state", ['state' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.state', 'disabled')
        ->assertJsonPath('data.is_active', false);

    expect($module->fresh()->is_active)->toBeFalse();

    $this->actingAs($developer)
        ->patchJson("/api/modules/{$module->id}/state", ['state' => 'enabled'])
        ->assertOk()
        ->assertJsonPath('data.state', 'enabled')
        ->assertJsonPath('data.is_active', true);

    expect($module->fresh()->is_active)->toBeTrue();
});

test('core modules cannot be disabled through the module state endpoint', function () {
    $developer = createSystemConsoleDeveloper();
    // The authorization seeder creates the canonical system module.
    // Reuse it rather than inserting a duplicate slug into the unique index.
    $module = Module::query()->where('slug', 'system')->firstOrFail();

    $this->actingAs($developer)
        ->patchJson("/api/modules/{$module->id}/state", ['state' => 'disabled'])
        ->assertForbidden();

    expect($module->fresh()->state)->toBe('enabled');
});

test('module states are enforced independently of developer permissions', function () {
    $developer = createSystemConsoleDeveloper();
    $module = Module::factory()->create(['state' => 'disabled', 'is_active' => false]);
    $view = createSystemConsolePermission($module, 'system.test_module.view');
    $create = createSystemConsolePermission($module, 'system.test_module.create');
    $authorization = app(AuthorizationService::class);

    expect($authorization->can($developer, $view->slug))->toBeFalse();

    $module->update(['state' => 'locked', 'is_active' => true]);

    expect($authorization->can($developer, $view->slug))->toBeTrue()
        ->and($authorization->can($developer, $create->slug))->toBeFalse();

    $module->update(['state' => 'hidden']);

    expect($authorization->can($developer, $create->slug))->toBeTrue();
});

test('authorization seeding preserves configured module lifecycle states', function () {
    app(SystemAuthorizationSeeder::class)->run();

    $module = Module::query()->where('slug', 'products')->firstOrFail();
    $module->update([
        'state' => 'locked',
        'is_active' => true,
    ]);

    app(SystemAuthorizationSeeder::class)->run();

    expect($module->fresh()->state)->toBe('locked')
        ->and($module->fresh()->is_active)->toBeTrue();
});

test('developer can create and configure feature flags with audit records', function () {
    $developer = createSystemConsoleDeveloper();

    $this->actingAs($developer)
        ->postJson('/api/feature-flags', [
            'name' => 'Experimental checkout',
            'key' => 'checkout.experimental.enabled',
            'is_enabled' => true,
            'rollout_percentage' => 35,
            'environment' => 'testing',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rollout_percentage', 35);

    $flag = FeatureFlag::query()->where('key', 'checkout.experimental.enabled')->firstOrFail();

    $this->actingAs($developer)
        ->putJson("/api/feature-flags/{$flag->id}", [
            'is_enabled' => false,
            'rollout_percentage' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.is_enabled', false);

    expect(ActivityLog::query()->whereIn('action', ['feature_flag.created', 'feature_flag.updated'])->count())->toBe(2);
});

test('feature flag evaluation respects enablement, environment and module state', function () {
    $module = Module::factory()->create(['state' => 'enabled', 'is_active' => true]);
    $flag = FeatureFlag::query()->create([
        'module_id' => $module->id,
        'key' => 'test.runtime.enabled',
        'name' => 'Runtime flag',
        'is_enabled' => true,
        'rollout_percentage' => 100,
        'environment' => 'all',
        'is_system' => false,
    ]);
    $service = app(FeatureFlagService::class);

    expect($service->enabled($flag->key))->toBeTrue();

    $flag->update(['environment' => 'production']);
    expect($service->enabled($flag->key))->toBeFalse();

    $flag->update(['environment' => 'all']);
    $module->update(['state' => 'disabled', 'is_active' => false]);
    expect($service->enabled($flag->key))->toBeFalse();
});

test('developer can create menu entries and menu visibility remains permission-aware', function () {
    $developer = createSystemConsoleDeveloper();
    $module = Module::factory()->create(['state' => 'enabled', 'is_active' => true]);
    $permission = createSystemConsolePermission($module, 'system.custom_menu.view');

    $this->actingAs($developer)
        ->postJson('/api/menu-registry', [
            'key' => 'custom.reports',
            'label' => 'Custom Reports',
            'route' => '/custom-reports',
            'icon' => 'chart-column',
            'permission' => $permission->slug,
            'module_id' => $module->id,
            'is_visible' => true,
        ])
        ->assertCreated();

    $item = MenuItem::query()->where('key', 'custom.reports')->firstOrFail();
    $service = app(MenuRegistryService::class);

    expect(collect($service->navigationFor($developer))->pluck('label'))->toContain('Custom Reports');

    $item->update(['is_visible' => false]);
    expect(collect($service->navigationFor($developer))->pluck('label'))->not->toContain('Custom Reports');
});

test('non-developer users cannot manage feature flags or menu registry', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['is_active' => true]);
    $user->roles()->attach($role->id);
    $systemModule = Module::factory()->create(['slug' => 'system', 'state' => 'enabled', 'is_active' => true]);
    $managePermission = createSystemConsolePermission($systemModule, 'system.system.manage');
    $role->permissions()->attach($managePermission->id);

    $this->actingAs($user)->getJson('/api/feature-flags')->assertForbidden();
    $this->actingAs($user)->getJson('/api/menu-registry')->assertForbidden();
});

test('menu registry prevents creating unsupported third-level navigation', function () {
    $developer = createSystemConsoleDeveloper();
    $root = MenuItem::query()->create([
        'key' => 'custom.root',
        'label' => 'Custom Root',
        'route' => null,
        'icon' => 'folder',
        'is_visible' => true,
        'is_system' => false,
    ]);
    MenuItem::query()->create([
        'key' => 'custom.root.child',
        'parent_id' => $root->id,
        'label' => 'Custom Child',
        'route' => '/custom-child',
        'icon' => 'file',
        'permission' => null,
        'is_visible' => true,
        'is_system' => false,
    ]);
    $anotherRoot = MenuItem::query()->create([
        'key' => 'custom.other',
        'label' => 'Another Root',
        'route' => null,
        'icon' => 'folder',
        'is_visible' => true,
        'is_system' => false,
    ]);

    $this->actingAs($developer)
        ->putJson("/api/menu-registry/{$root->id}", [
            'key' => $root->key,
            'label' => $root->label,
            'route' => null,
            'icon' => $root->icon,
            'permission' => null,
            'module_id' => null,
            'parent_id' => $anotherRoot->id,
            'sort_order' => 0,
            'is_visible' => true,
            'developer_only' => false,
        ])
        ->assertUnprocessable();
});

test('menu registry filters navigation by permission and hidden module state', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['is_active' => true]);
    $user->roles()->attach($role->id);
    $module = Module::factory()->create(['state' => 'enabled', 'is_active' => true]);
    $allowedPermission = createSystemConsolePermission($module, 'system.menu_allowed.view');
    $deniedPermission = createSystemConsolePermission($module, 'system.menu_denied.view');
    $role->permissions()->attach($allowedPermission->id);

    MenuItem::query()->create([
        'key' => 'permission.allowed',
        'label' => 'Allowed Page',
        'route' => '/allowed-page',
        'icon' => 'file',
        'permission' => $allowedPermission->slug,
        'module_id' => $module->id,
        'is_visible' => true,
    ]);
    MenuItem::query()->create([
        'key' => 'permission.denied',
        'label' => 'Denied Page',
        'route' => '/denied-page',
        'icon' => 'file-x',
        'permission' => $deniedPermission->slug,
        'module_id' => $module->id,
        'is_visible' => true,
    ]);

    $service = app(MenuRegistryService::class);

    expect(collect($service->navigationFor($user))->pluck('label'))
        ->toContain('Allowed Page')
        ->not->toContain('Denied Page');

    $module->update(['state' => 'hidden']);

    expect(collect($service->navigationFor($user))->pluck('label'))
        ->not->toContain('Allowed Page');
});

test('storefront catalogue is unavailable when its feature flag is disabled', function () {
    $flag = FeatureFlag::query()->where('key', 'storefront.checkout.enabled')->firstOrFail();
    $flag->update(['is_enabled' => false]);

    $this->get('/shop')->assertNotFound();
});

test('non-developer cannot change module state even when granted the system management permission', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['is_active' => true]);
    $user->roles()->attach($role->id);
    $systemModule = Module::factory()->create(['slug' => 'system', 'state' => 'enabled', 'is_active' => true]);
    $managePermission = createSystemConsolePermission($systemModule, 'system.system.manage');
    $role->permissions()->attach($managePermission->id);
    $module = Module::factory()->create(['state' => 'enabled', 'is_active' => true]);

    $this->actingAs($user)
        ->patchJson("/api/modules/{$module->id}/state", ['state' => 'disabled'])
        ->assertForbidden();

    expect($module->fresh()->state)->toBe('enabled');
});

test('developer can update system settings atomically with an audit snapshot', function () {
    $developer = createSystemConsoleDeveloper();

    $this->actingAs($developer)
        ->putJson('/api/settings', [
            'settings' => [
                ['key' => 'general.site_name', 'value' => 'Madina Scale Test', 'type' => 'string'],
                ['key' => 'system.maintenance_mode', 'value' => false, 'type' => 'boolean'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Settings updated successfully.');

    expect(Setting::query()->where('key', 'general.site_name')->value('value'))->toBe('Madina Scale Test')
        ->and(Setting::query()->where('key', 'system.maintenance_mode')->value('value'))->toBe('0');

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'settings.updated',
        'module' => 'settings',
    ]);
});

test('feature flag seeder preserves developer rollout configuration on rerun', function () {
    $flag = FeatureFlag::query()->where('key', 'storefront.checkout.enabled')->firstOrFail();
    $flag->update(['is_enabled' => false, 'rollout_percentage' => 42, 'environment' => 'staging']);

    app(FeatureFlagSeeder::class)->run();

    expect($flag->fresh()->is_enabled)->toBeFalse()
        ->and($flag->fresh()->rollout_percentage)->toBe(42)
        ->and($flag->fresh()->environment)->toBe('staging');
});

test('menu registry seeder preserves administrator visibility and labels on rerun', function () {
    app(MenuRegistrySeeder::class)->run();
    $dashboard = MenuItem::query()->where('key', 'dashboard')->firstOrFail();
    $dashboard->update(['label' => 'My Dashboard Label', 'is_visible' => false]);

    app(MenuRegistrySeeder::class)->run();

    expect($dashboard->fresh()->label)->toBe('My Dashboard Label')
        ->and($dashboard->fresh()->is_visible)->toBeFalse();
});

test('system maintenance mode blocks public traffic while preserving Developer access and login', function () {
    Setting::query()->create([
        'key' => 'system.maintenance_mode',
        'group' => 'system',
        'value' => '1',
        'type' => 'boolean',
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $this->get('/shop')->assertServiceUnavailable();
    $this->get('/login')->assertOk();

    $developer = createSystemConsoleDeveloper();
    $this->actingAs($developer)->get('/developer/console')->assertOk();

    $regularUser = User::factory()->create();
    $this->actingAs($regularUser)->get('/dashboard')->assertServiceUnavailable();
});

test('system feature flag code keys cannot be renamed through the service', function () {
    $flag = FeatureFlag::query()->where('key', 'storefront.checkout.enabled')->firstOrFail();

    expect(fn () => app(FeatureFlagService::class)->update($flag, ['key' => 'storefront.new-key']))
        ->toThrow(HttpException::class);

    expect($flag->fresh()->key)->toBe('storefront.checkout.enabled');
});

test('system settings reject invalid registered values and duplicate keys', function () {
    $developer = createSystemConsoleDeveloper();

    $this->actingAs($developer)
        ->putJson('/api/settings', [
            'settings' => [
                ['key' => 'currency.decimal_places', 'value' => 8, 'type' => 'integer'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settings.0.value');

    $this->actingAs($developer)
        ->putJson('/api/settings', [
            'settings' => [
                ['key' => 'general.site_name', 'value' => 'First', 'type' => 'string'],
                ['key' => 'general.site_name', 'value' => 'Second', 'type' => 'string'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.0.key', 'settings.1.key']);
});

test('critical maintenance settings remain Developer-only even when settings update is delegated', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['is_active' => true]);
    $user->roles()->attach($role->id);
    $settingsModule = Module::factory()->create(['slug' => 'settings', 'state' => 'enabled', 'is_active' => true]);
    $updatePermission = createSystemConsolePermission($settingsModule, 'system.settings.update');
    $role->permissions()->attach($updatePermission->id);

    $this->actingAs($user)
        ->putJson('/api/settings', [
            'settings' => [
                ['key' => 'system.maintenance_mode', 'value' => true, 'type' => 'boolean'],
            ],
        ])
        ->assertForbidden();

    expect(Setting::query()->where('key', 'system.maintenance_mode')->exists())->toBeFalse();
});
