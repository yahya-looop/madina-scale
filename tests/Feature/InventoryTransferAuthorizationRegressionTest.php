<?php

use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Phase 2B authorization regression coverage.
 *
 * These tests intentionally reuse the fixture helpers defined by
 * InventoryTransferWorkflowTest.php: createTransferWorkflowUser(),
 * inventoryTestProduct(), and inventoryTestLocation().
 */
if (! function_exists('createPhase2BGlobalLocationManager')) {
    function createPhase2BGlobalLocationManager(): User
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
            'name' => 'Phase 2B Global Location Manager',
            'slug' => 'phase-2b-global-location-manager-'.Str::uuid(),
            'description' => 'Regression-test role for global location management.',
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach (['system.locations.manage', 'system.inventory.transfer'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => $slug,
                'description' => 'Phase 2B authorization regression permission.',
            ]);

            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }
}

test('global location manager may dispatch without an explicit source assignment', function () {
    $manager = createPhase2BGlobalLocationManager();
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();

    // Global location manager deliberately has no location-pivot assignment.
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);

    InventoryStock::query()->create([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0',
    ]);

    $this->actingAs($manager)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '2',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('data.status', 'in_transit');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '8.0000',
    ]);
});

test('removing destination assignment revokes the users ability to receive an existing transfer', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();

    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);

    InventoryStock::query()->create([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0',
    ]);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '4',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $transfer = InventoryTransfer::query()->firstOrFail();

    // Revoke assignment after dispatch; authorization must be checked at receipt time.
    $receiver->locations()->detach($destination->id);

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", [
        'quantity_received' => '4',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable();

    $this->assertDatabaseHas('inventory_transfers', [
        'id' => $transfer->id,
        'status' => 'in_transit',
        'quantity_received' => '0.0000',
    ]);

    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $destination->id,
    ]);
});
