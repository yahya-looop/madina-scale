<?php

use App\Models\InventoryStock;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Str;

if (! function_exists('createMilestone3cStockManager')) {
    function createMilestone3cStockManager(): User
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
            'name' => 'Milestone 3C Stock Manager',
            'slug' => 'milestone-3c-stock-manager-' . Str::lower(Str::random(12)),
            'description' => 'Temporary role for stock integrity tests.',
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach (['system.inventory.view', 'system.inventory.issue'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => ucfirst(str_replace(['system.inventory.', '.'], ['', ' '], $slug)),
                'description' => 'Stock integrity test permission.',
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }
}

test('repeated issues cannot consume reserved stock or oversell the remaining available balance', function () {
    $user = createMilestone3cStockManager();
    $location = inventoryTestLocation('m3c-concurrency');
    $product = inventoryTestProduct();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    $stock = InventoryStock::query()->create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '3.0000',
        'low_stock_threshold' => '0.0000',
    ]);

    $this->actingAs($user);

    $payload = [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '4.5000',
    ];

    $this->postJson('/api/inventory/issue', $payload)->assertOk();

    // The second request represents a competing request based on the same original UI state.
    // The service must re-check the locked/current balance, not trust the client's stale balance.
    $this->postJson('/api/inventory/issue', $payload)->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $stock->id,
        'quantity' => '5.5000',
        'reserved_quantity' => '3.0000',
    ]);
    expect(StockMovement::query()->where('location_id', $location->id)->where('movement_type', 'issue')->count())
        ->toBe(1);
});
