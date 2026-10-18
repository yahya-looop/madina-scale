<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Str;

function reservationTestUser(): User
{
    $user = User::factory()->create();
    $module = Module::firstOrCreate(['slug' => 'inventory'], [
        'name' => 'Inventory', 'description' => 'Inventory management.',
        'sort_order' => 236, 'is_system' => true, 'is_active' => true,
    ]);
    $permission = Permission::firstOrCreate(['slug' => 'system.inventory.reservations.manage'], [
        'module_id' => $module->id, 'name' => 'Manage Inventory Reservations',
        'description' => 'Create and resolve stock reservations.',
    ]);
    $view = Permission::firstOrCreate(['slug' => 'system.inventory.view'], [
        'module_id' => $module->id, 'name' => 'View Inventory', 'description' => 'View inventory.',
    ]);
    $role = Role::create([
        'name' => 'Reservation Test Role', 'slug' => 'reservation-test-' . Str::lower(Str::random(12)),
        'description' => 'Temporary role for reservation API tests.', 'is_system' => false, 'is_active' => true,
    ]);
    $role->permissions()->attach([$permission->id, $view->id]);
    $user->roles()->attach($role->id);
    return $user;
}

test('reservation reduces available quantity and release restores it', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10']);
    $this->actingAs($user);

    $created = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '4',
        'purpose' => 'customer_order', 'reference_type' => 'order', 'reference_id' => 'ORD-100',
    ])->assertCreated()->assertJsonPath('data.status', 'active');
    $id = $created->json('data.id');
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $location->id, 'reserved_quantity' => '4.0000']);

    $this->postJson("/api/inventory/reservations/{$id}/release")->assertOk()->assertJsonPath('data.status', 'released');
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $location->id, 'reserved_quantity' => '0.0000', 'quantity' => '10.0000']);
});

test('fulfilling a reservation reduces both on-hand and reserved stock and writes a ledger movement', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10']);
    $this->actingAs($user);
    $id = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '3', 'purpose' => 'internal_use',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/inventory/reservations/{$id}/fulfill")->assertOk()->assertJsonPath('data.status', 'fulfilled');
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '7.0000', 'reserved_quantity' => '0.0000']);
    $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'location_id' => $location->id, 'movement_type' => 'issue', 'reference_type' => 'inventory_reservation', 'reference_id' => (string) $id]);
});

test('reservation cannot exceed available stock', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '2']);
    $this->actingAs($user);

    $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '3', 'purpose' => 'customer_order',
    ])->assertUnprocessable();
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '2.0000', 'reserved_quantity' => '0.0000']);
});

test('fulfilling a customer-order reservation creates an order fulfillment movement, not a manual stock-out', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '10']);
    $this->actingAs($user);

    $id = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '3',
        'purpose' => 'customer_order',
        'reference_type' => 'order',
        'reference_id' => 'ORD-101',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/inventory/reservations/{$id}/fulfill")
        ->assertOk()
        ->assertJsonPath('data.status', 'fulfilled');

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'order_fulfillment',
        'reference_type' => 'inventory_reservation',
        'reference_id' => (string) $id,
    ]);
    $this->assertDatabaseMissing('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'issue',
        'reference_type' => 'inventory_reservation',
        'reference_id' => (string) $id,
    ]);
});



test('cancelling an active reservation releases reserved quantity without changing on-hand stock', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '8']);
    $this->actingAs($user);

    $id = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '3', 'purpose' => 'internal_use',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/inventory/reservations/{$id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $location->id,
        'quantity' => '8.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseMissing('stock_movements', [
        'reference_type' => 'inventory_reservation', 'reference_id' => (string) $id,
    ]);
});

test('a reservation cannot be fulfilled twice or deduct stock twice', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '8']);
    $this->actingAs($user);

    $id = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '3', 'purpose' => 'internal_use',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/inventory/reservations/{$id}/fulfill")->assertOk()->assertJsonPath('data.status', 'fulfilled');
    $this->postJson("/api/inventory/reservations/{$id}/fulfill")->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $location->id,
        'quantity' => '5.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseCount('stock_movements', 2); // one receipt + one fulfillment
});

test('an expired reservation releases its reserved quantity and cannot be fulfilled', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '8']);
    $this->actingAs($user);

    $id = $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '3',
        'purpose' => 'customer_order', 'expires_at' => now()->addMinute()->toISOString(),
    ])->assertCreated()->json('data.id');

    $this->travel(2)->minutes();
    $this->postJson("/api/inventory/reservations/{$id}/fulfill")
        ->assertOk()->assertJsonPath('data.status', 'expired');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $location->id,
        'quantity' => '8.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseHas('inventory_reservations', ['id' => $id, 'status' => 'expired']);
});

test('reservation purposes can be added without changing frontend code or validation enums', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    app(InventoryService::class)->receive(['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '8']);
    $this->actingAs($user);

    $this->postJson('/api/inventory/reservation-purposes', [
        'key' => 'quality_check', 'label' => 'Quality inspection',
    ])->assertCreated()->assertJsonPath('data.key', 'quality_check');

    $this->getJson('/api/inventory/reservation-purposes')
        ->assertOk()->assertJsonFragment(['key' => 'quality_check', 'label' => 'Quality inspection']);

    $this->postJson('/api/inventory/reservations', [
        'product_id' => $product->id, 'location_id' => $location->id,
        'quantity' => '2', 'purpose' => 'quality_check',
    ])->assertCreated()->assertJsonPath('data.purpose', 'quality_check');
});
