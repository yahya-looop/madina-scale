<?php

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Str;

// Keep this file runnable on its own as well as in the complete Pest suite.
if (! function_exists('createInventoryApiUser')) {
    function createInventoryApiUser(array $permissionSlugs = []): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'inventory'], [
            'name' => 'Inventory', 'description' => 'Inventory management.',
            'sort_order' => 236, 'is_system' => true, 'is_active' => true,
        ]);
        $role = Role::create([
            'name' => 'Inventory API Test Role',
            'slug' => 'inventory-api-test-'.uniqid(),
            'description' => 'Temporary role for Inventory API tests.',
            'is_system' => false, 'is_active' => true,
        ]);
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => ucfirst(str_replace(['system.inventory.', '.'], ['', ' '], $slug)),
                'description' => 'Inventory API test permission.',
            ]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $user;
    }
}

if (! function_exists('inventoryApiCreateProduct')) {
    function inventoryApiCreateProduct(string $type = 'simple'): Product
    {
        return inventoryTestProduct($type);
    }
}
if (! function_exists('inventoryApiCreateLocation')) {
    function inventoryApiCreateLocation(): Location
    {
        return inventoryTestLocation();
    }
}
if (! function_exists('inventoryApiCreateVariant')) {
    function inventoryApiCreateVariant(Product $product): ProductVariant
    {
        return inventoryTestVariant($product);
    }
}

test('zero-stock item can be restocked through the receipt API and leaves low-stock list when replenished above threshold', function () {
    $user = createInventoryApiUser([
        'system.inventory.receive',
        'system.inventory.view',
    ]);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    $stock = InventoryStock::create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '0.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '20.0000',
    ]);

    $this->actingAs($user);

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertOk()
        ->assertJsonFragment(['id' => $stock->id]);

    $this->postJson('/api/v1/backend/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '25.0000',
        'reason' => 'Restock regression test',
        'idempotency_key' => (string) Str::uuid(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.movement_type', 'receipt')
        ->assertJsonPath('data.balance_before', '0.0000')
        ->assertJsonPath('data.balance_after', '25.0000');

    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $stock->id,
        'quantity' => '25.0000',
        'reserved_quantity' => '0.0000',
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'receipt',
        'quantity_delta' => '25.0000',
        'balance_before' => '0.0000',
        'balance_after' => '25.0000',
        'reason' => 'Restock regression test',
        'ip_address' => '127.0.0.1',
    ]);

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertOk()
        ->assertJsonMissing(['id' => $stock->id]);
});

test('restock receipt supports a variant without creating parent-product stock', function () {
    $user = createInventoryApiUser(['system.inventory.receive']);
    $product = inventoryApiCreateProduct('variable');
    $variant = inventoryApiCreateVariant($product);
    $location = inventoryApiCreateLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/receive', [
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '6.0000',
        'reason' => 'Variant restock regression test',
        'idempotency_key' => (string) Str::uuid(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.product_variant_id', $variant->id)
        ->assertJsonPath('data.balance_after', '6.0000');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => null,
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '6.0000',
        'reserved_quantity' => '0.0000',
    ]);

    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
    ]);
});

test('low-stock restock transfers stock between locations and refuses non-low destination items', function () {
    $user = createInventoryApiUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryApiCreateProduct();
    $source = inventoryApiCreateLocation();
    $destination = inventoryApiCreateLocation();
    app(InventoryService::class)->receive([
        'product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '12',
    ]);
    $destinationStock = InventoryStock::create([
        'product_id' => $product->id, 'location_id' => $destination->id,
        'quantity' => '2.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '20.0000',
    ]);
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver = createInventoryApiUser(['system.inventory.transfer']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/restock', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '5',
        'reason' => 'Low-stock replenishment', 'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('message', 'Restock transfer dispatched and is awaiting destination confirmation.')
        ->assertJsonPath('data.status', 'in_transit');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '7.0000',
    ]);
    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $destinationStock->id, 'quantity' => '2.0000',
    ]);
    $this->assertDatabaseHas('stock_movements', ['location_id' => $source->id, 'movement_type' => 'transfer_out', 'quantity_delta' => '-5.0000']);
    $this->assertDatabaseMissing('stock_movements', ['location_id' => $destination->id, 'movement_type' => 'transfer_in', 'quantity_delta' => '5.0000']);

    $destinationStock->update(['quantity' => '25.0000']);
    $this->postJson('/api/v1/backend/inventory/restock', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '1',
    ])->assertUnprocessable();
});

test('issue and adjustment APIs write request IP address during immutable ledger insertion', function () {
    $user = createInventoryApiUser([
        'system.inventory.receive',
        'system.inventory.issue',
        'system.inventory.adjust',
    ]);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
    ])->assertCreated();

    $this->postJson('/api/v1/backend/inventory/issue', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ])->assertOk();

    $this->postJson('/api/v1/backend/inventory/adjust', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '1.0000',
        'reason' => 'Ledger IP regression test',
    ])->assertOk();

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'issue',
        'ip_address' => '127.0.0.1',
    ]);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'adjustment',
        'ip_address' => '127.0.0.1',
    ]);

    $this->assertDatabaseCount('stock_movements', 3);
});
