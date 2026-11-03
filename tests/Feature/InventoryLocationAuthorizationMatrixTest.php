<?php

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Str;

if (! function_exists('createMilestone3cInventoryUser')) {
    function createMilestone3cInventoryUser(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'inventory'], [
            'name' => 'Inventory',
            'description' => 'Inventory management.',
            'sort_order' => 236,
            'is_system' => true,
            'is_active' => true,
        ]);
        $role = Role::create([
            'name' => 'Milestone 3C Inventory Role',
            'slug' => 'milestone-3c-inventory-'.Str::lower(Str::random(12)),
            'description' => 'Temporary role for location authorization matrix tests.',
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach (array_unique($permissionSlugs) as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => ucfirst(str_replace(['system.inventory.', '.'], ['', ' '], $slug)),
                'description' => 'Inventory authorization matrix test permission.',
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }
}

test('view access is isolated to assigned locations across stock low-stock and movement APIs', function () {
    $user = createMilestone3cInventoryUser(['system.inventory.view']);
    $visibleLocation = inventoryTestLocation('m3c-visible');
    $hiddenLocation = inventoryTestLocation('m3c-hidden');
    $product = inventoryTestProduct();

    $user->locations()->attach($visibleLocation->id, ['access_level' => 'view']);
    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $visibleLocation->id,
        'quantity' => '5.0000',
    ]);
    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $hiddenLocation->id,
        'quantity' => '5.0000',
    ]);

    InventoryStock::query()->where('product_id', $product->id)->update(['low_stock_threshold' => '20.0000']);

    $this->actingAs($user);

    $this->getJson('/api/inventory/stocks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location_id', $visibleLocation->id);

    $this->getJson('/api/inventory/low-stock')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location_id', $visibleLocation->id);

    $this->getJson('/api/inventory/movements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location_id', $visibleLocation->id);

    $this->getJson('/api/inventory/stocks?location_id='.$hiddenLocation->id)->assertForbidden();
    $this->getJson('/api/inventory/low-stock?location_id='.$hiddenLocation->id)->assertForbidden();
    $this->getJson('/api/inventory/movements?location_id='.$hiddenLocation->id)->assertForbidden();
});

test('receive access permits receipts but denies issue adjustment and threshold changes', function () {
    $user = createMilestone3cInventoryUser([
        'system.inventory.view',
        'system.inventory.receive',
        'system.inventory.issue',
        'system.inventory.adjust',
        'system.inventory.update',
    ]);
    $location = inventoryTestLocation('m3c-receive-only');
    $product = inventoryTestProduct();
    $user->locations()->attach($location->id, ['access_level' => 'receive']);

    $this->actingAs($user);

    $this->postJson('/api/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $stock = InventoryStock::query()->where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();

    $this->postJson('/api/inventory/issue', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '1.0000',
    ])->assertForbidden();

    $this->postJson('/api/inventory/adjust', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '-1.0000',
    ])->assertForbidden();

    $this->patchJson('/api/inventory/stocks/'.$stock->id.'/threshold', [
        'low_stock_threshold' => 20,
    ])->assertForbidden();

    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $stock->id,
        'quantity' => '5.0000',
        'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('inventory location options expose only active assigned locations and their access levels', function () {
    $user = createMilestone3cInventoryUser(['system.inventory.view']);
    $viewLocation = inventoryTestLocation('options-view');
    $receiveLocation = inventoryTestLocation('options-receive');
    $manageLocation = inventoryTestLocation('options-manage');
    $unassignedLocation = inventoryTestLocation('options-hidden');
    $inactiveLocation = inventoryTestLocation('options-inactive');
    $inactiveLocation->update(['is_active' => false]);

    $user->locations()->attach($viewLocation->id, ['access_level' => 'view']);
    $user->locations()->attach($receiveLocation->id, ['access_level' => 'receive']);
    $user->locations()->attach($manageLocation->id, ['access_level' => 'manage']);
    $user->locations()->attach($inactiveLocation->id, ['access_level' => 'manage']);

    $response = $this->actingAs($user)->getJson('/api/inventory/locations')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows->has($viewLocation->id))->toBeTrue()
        ->and($rows->has($receiveLocation->id))->toBeTrue()
        ->and($rows->has($manageLocation->id))->toBeTrue()
        ->and($rows->has($unassignedLocation->id))->toBeFalse()
        ->and($rows->has($inactiveLocation->id))->toBeFalse()
        ->and($rows[$viewLocation->id]['access_level'])->toBe('view')
        ->and($rows[$receiveLocation->id]['access_level'])->toBe('receive')
        ->and($rows[$manageLocation->id]['access_level'])->toBe('manage');
});

test('global location manager can load all active inventory locations as manageable', function () {
    $user = createMilestone3cInventoryUser([
        'system.inventory.view',
        'system.locations.manage',
    ]);
    $assignedLocation = inventoryTestLocation('global-assigned');
    $unassignedLocation = inventoryTestLocation('global-unassigned');

    $user->locations()->attach($assignedLocation->id, ['access_level' => 'view']);

    $activeLocationCount = Location::query()->where('is_active', true)->count();
    $response = $this->actingAs($user)->getJson('/api/inventory/locations')
        ->assertOk()
        ->assertJsonCount($activeLocationCount, 'data');

    $rows = collect($response->json('data'))->keyBy('id');

    expect($rows->has($assignedLocation->id))->toBeTrue()
        ->and($rows->has($unassignedLocation->id))->toBeTrue()
        ->and($rows[$assignedLocation->id]['access_level'])->toBe('manage')
        ->and($rows[$unassignedLocation->id]['access_level'])->toBe('manage');
});
