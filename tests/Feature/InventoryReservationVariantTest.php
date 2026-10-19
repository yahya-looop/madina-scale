<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Str;

if (! function_exists('reservationTestUser')) {
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
}

/**
 * Regression coverage for variant-owned reservation stock. Uses the project's
 * existing inventoryTestProduct/inventoryTestVariant/location helpers.
 */
test('reservation fulfillment affects only the selected variant stock and records variant ledger dimensions', function () {
    $user = reservationTestUser();
    $product = inventoryTestProduct('variable');
    $variant = inventoryTestVariant($product);
    $location = inventoryTestLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    app(InventoryService::class)->receive([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '9.0000',
    ]);

    $this->actingAs($user);

    $created = $this->postJson('/api/inventory/reservations', [
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '4.0000',
        'purpose' => 'internal_use',
    ])->assertCreated()->assertJsonPath('data.status', 'active');

    $reservationId = $created->json('data.id');

    $this->postJson("/api/inventory/reservations/{$reservationId}/fulfill")
        ->assertOk()
        ->assertJsonPath('data.status', 'fulfilled');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => null,
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reserved_quantity' => '0.0000',
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => null,
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'movement_type' => 'issue',
        'reference_type' => 'inventory_reservation',
        'reference_id' => (string) $reservationId,
        'quantity_delta' => '-4.0000',
        'balance_before' => '9.0000',
        'balance_after' => '5.0000',
    ]);

    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
    ]);
});
