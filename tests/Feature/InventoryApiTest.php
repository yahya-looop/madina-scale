<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function createInventoryApiUser(array $permissionSlugs = []): User
{
    $user = User::factory()->create();

    $module = Module::firstOrCreate(
        ['slug' => 'inventory'],
        [
            'name' => 'Inventory',
            'description' => 'Inventory management.',
            'sort_order' => 236,
            'is_system' => true,
            'is_active' => true,
        ]
    );

    $role = Role::create([
        'name' => 'Inventory API Test Role',
        'slug' => 'inventory-api-test-' . uniqid(),
        'description' => 'Temporary role for Inventory API tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    foreach ($permissionSlugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'module_id' => $module->id,
                'name' => ucfirst(str_replace(
                    ['system.inventory.', '.'],
                    ['', ' '],
                    $slug
                )),
                'description' => 'Inventory API test permission.',
            ]
        );

        $role->permissions()->attach($permission->id);
    }

    $user->roles()->attach($role->id);

    return $user;
}

test('unauthenticated users cannot access inventory endpoints', function () {
    $this->getJson('/api/inventory/stocks')
        ->assertUnauthorized();

    $this->getJson('/api/inventory/movements')
        ->assertUnauthorized();

    $this->postJson('/api/inventory/receive', [])
        ->assertUnauthorized();
});

test('authenticated user without inventory permission receives forbidden response', function () {
    $user = createInventoryApiUser();

    $this->actingAs($user);

    $this->getJson('/api/inventory/stocks')
        ->assertForbidden();

    $this->getJson('/api/inventory/movements')
        ->assertForbidden();

    $this->postJson('/api/inventory/receive', [])
        ->assertForbidden();
});

test('user with inventory view permission can access stock and movement endpoints', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $this->actingAs($user);

    $this->getJson('/api/inventory/stocks')
        ->assertOk();

    $this->getJson('/api/inventory/movements')
        ->assertOk();
});

test('inventory view permission alone cannot perform stock mutations', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/inventory/receive', [])
        ->assertForbidden();

    $this->postJson('/api/inventory/issue', [])
        ->assertForbidden();

    $this->postJson('/api/inventory/adjust', [])
        ->assertForbidden();

    $this->postJson('/api/inventory/transfer', [])
        ->assertForbidden();
});

test('receive endpoint validates required stock movement fields', function () {
    $user = createInventoryApiUser([
        'system.inventory.receive',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/inventory/receive', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'location_id',
            'quantity',
        ]);
});

test('issue endpoint validates required stock movement fields', function () {
    $user = createInventoryApiUser([
        'system.inventory.issue',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/inventory/issue', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'location_id',
            'quantity',
        ]);
});

test('adjust endpoint validates required stock movement fields', function () {
    $user = createInventoryApiUser([
        'system.inventory.adjust',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/inventory/adjust', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'location_id',
            'quantity',
        ]);
});

test('transfer endpoint validates required transfer fields', function () {
    $user = createInventoryApiUser([
        'system.inventory.transfer',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/inventory/transfer', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'source_location_id',
            'destination_location_id',
            'quantity',
        ]);
});


use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

function inventoryApiCreateProduct(string $type = 'simple'): Product
{
    return inventoryTestProduct($type);
}

function inventoryApiCreateLocation(): Location
{
    return inventoryTestLocation();
}

function inventoryApiCreateVariant(Product $product): ProductVariant
{
    return inventoryTestVariant($product);
}

test('authorized receive API increases stock and creates a movement', function () {
    $user = createInventoryApiUser(['system.inventory.receive']);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();

    $this->actingAs($user);

    $response = $this->postJson('/api/inventory/receive', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '12.5000',
        'reason' => 'API receipt test',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.movement_type', 'receipt')
        ->assertJsonPath('data.balance_after', '12.5000');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '12.5000',
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'movement_type' => 'receipt',
        'quantity_delta' => '12.5000',
    ]);
});

test('authorized issue API reduces stock and records the balance', function () {
    $user = createInventoryApiUser(['system.inventory.issue']);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();

    app(\App\Services\InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/api/inventory/issue', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '3.2500',
        'reason' => 'API issue test',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.movement_type', 'issue')
        ->assertJsonPath('data.balance_after', '6.7500');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '6.7500',
    ]);
});

test('insufficient stock API request leaves balance and ledger unchanged', function () {
    $user = createInventoryApiUser(['system.inventory.issue']);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();

    app(\App\Services\InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ]);

    $movementCount = \App\Models\StockMovement::query()->count();

    $this->actingAs($user);

    $this->postJson('/api/inventory/issue', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ]);

    expect(\App\Models\StockMovement::query()->count())
        ->toBe($movementCount);
});

test('repeated receive API request with the same idempotency key does not duplicate stock', function () {
    $user = createInventoryApiUser(['system.inventory.receive']);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();
    $key = (string) Str::uuid();

    $payload = [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reason' => 'Idempotency API test',
        'idempotency_key' => $key,
    ];

    $this->actingAs($user);

    $first = $this->postJson('/api/inventory/receive', $payload);
    $first->assertCreated();

    $second = $this->postJson('/api/inventory/receive', $payload);
    $second->assertCreated();

    expect($second->json('data.id'))
        ->toBe($first->json('data.id'));

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ]);

    $this->assertDatabaseCount('stock_movements', 1);
});

test('authorized transfer API updates both locations and creates paired movements', function () {
    $user = createInventoryApiUser(['system.inventory.transfer']);
    $product = inventoryApiCreateProduct();
    $source = inventoryApiCreateLocation();
    $destination = inventoryApiCreateLocation();

    app(\App\Services\InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '10.0000',
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/api/inventory/transfer', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '4.0000',
        'reason' => 'API transfer test',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.outgoing.movement_type', 'transfer_out')
        ->assertJsonPath('data.incoming.movement_type', 'transfer_in');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '6.0000',
    ]);

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $destination->id,
        'quantity' => '4.0000',
    ]);

    expect(\App\Models\StockMovement::query()
        ->whereIn('movement_type', ['transfer_out', 'transfer_in'])
        ->count())->toBe(2);
});

test('failed transfer API request leaves both location balances unchanged', function () {
    $user = createInventoryApiUser(['system.inventory.transfer']);
    $product = inventoryApiCreateProduct();
    $source = inventoryApiCreateLocation();
    $destination = inventoryApiCreateLocation();

    app(\App\Services\InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '2.0000',
    ]);

    $movementCount = \App\Models\StockMovement::query()->count();

    $this->actingAs($user);

    $this->postJson('/api/inventory/transfer', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '5.0000',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '2.0000',
    ]);

    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $destination->id,
        'quantity' => '5.0000',
    ]);

    expect(\App\Models\StockMovement::query()->count())
        ->toBe($movementCount);
});

