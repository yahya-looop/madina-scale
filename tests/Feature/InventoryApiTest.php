<?php

use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Str;

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
        'slug' => 'inventory-api-test-'.uniqid(),
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
    $this->getJson('/api/v1/backend/inventory/stocks')
        ->assertUnauthorized();

    $this->getJson('/api/v1/backend/inventory/movements')
        ->assertUnauthorized();

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertUnauthorized();

    $this->patchJson('/api/v1/backend/inventory/stocks/1/threshold', [
        'low_stock_threshold' => '5.0000',
    ])->assertUnauthorized();

    $this->postJson('/api/v1/backend/inventory/receive', [])
        ->assertUnauthorized();
});

test('authenticated user without inventory permission receives forbidden response', function () {
    $user = createInventoryApiUser();

    $this->actingAs($user);

    $this->getJson('/api/v1/backend/inventory/stocks')
        ->assertForbidden();

    $this->getJson('/api/v1/backend/inventory/movements')
        ->assertForbidden();

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertForbidden();

    $stock = InventoryStock::create([
        'product_id' => inventoryApiCreateProduct()->id,
        'location_id' => inventoryApiCreateLocation()->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '20.0000',
    ]);

    $this->patchJson(
        '/api/v1/backend/inventory/stocks/'.$stock->id.'/threshold',
        ['low_stock_threshold' => '20']
    )->assertForbidden();

    $this->postJson('/api/v1/backend/inventory/receive', [])
        ->assertForbidden();
});

test('user with inventory view permission can access stock and movement endpoints', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $this->actingAs($user);

    $this->getJson('/api/v1/backend/inventory/stocks')
        ->assertOk();

    $this->getJson('/api/v1/backend/inventory/movements')
        ->assertOk();

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertOk();
});

test('stock list returns only the latest movement for simple and variant stock without hydrating history', function () {
    $user = createInventoryApiUser(['system.inventory.view']);
    $location = inventoryApiCreateLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);

    $simpleProduct = inventoryApiCreateProduct();
    $variableProduct = inventoryApiCreateProduct('variable');
    $variant = inventoryApiCreateVariant($variableProduct);

    $simpleStock = InventoryStock::query()->create([
        'product_id' => $simpleProduct->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0.0000',
    ]);
    $variantStock = InventoryStock::query()->create([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '3.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0.0000',
    ]);

    $createMovement = function (array $dimensions, string $balance) use ($location): StockMovement {
        return StockMovement::query()->create($dimensions + [
            'location_id' => $location->id,
            'movement_type' => 'receipt',
            'quantity_delta' => '1.0000',
            'balance_before' => bcsub($balance, '1.0000', 4),
            'balance_after' => $balance,
        ]);
    };

    $createMovement(['product_id' => $simpleProduct->id], '1.0000');
    $latestSimple = $createMovement(['product_id' => $simpleProduct->id], '2.0000');
    $createMovement(['product_variant_id' => $variant->id], '2.0000');
    $latestVariant = $createMovement(['product_variant_id' => $variant->id], '3.0000');

    $this->actingAs($user)->getJson('/api/v1/backend/inventory/stocks')->assertOk()
        ->assertJsonPath('data.0.id', $simpleStock->id)
        ->assertJsonPath('data.0.latest_movement.id', $latestSimple->id)
        ->assertJsonPath('data.1.id', $variantStock->id)
        ->assertJsonPath('data.1.latest_movement.id', $latestVariant->id);
});

test('inventory view permission alone cannot perform stock mutations', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/receive', [])
        ->assertForbidden();

    $this->postJson('/api/v1/backend/inventory/issue', [])
        ->assertForbidden();

    $this->postJson('/api/v1/backend/inventory/adjust', [])
        ->assertForbidden();

    $this->postJson('/api/v1/backend/inventory/transfer', [])
        ->assertForbidden();

    $stock = InventoryStock::create([
        'product_id' => inventoryApiCreateProduct()->id,
        'location_id' => inventoryApiCreateLocation()->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '2.0000',
    ]);

    $this->patchJson('/api/v1/backend/inventory/stocks/'.$stock->id.'/threshold', [
        'low_stock_threshold' => '5.0000',
    ])->assertForbidden();
});

test('receive endpoint validates required stock movement fields', function () {
    $user = createInventoryApiUser([
        'system.inventory.receive',
    ]);

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/receive', [])
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

    $this->postJson('/api/v1/backend/inventory/issue', [])
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

    $this->postJson('/api/v1/backend/inventory/adjust', [])
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

    $this->postJson('/api/v1/backend/inventory/transfer', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'source_location_id',
            'destination_location_id',
            'quantity',
        ]);
});

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
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);

    $this->actingAs($user);

    $response = $this->postJson('/api/v1/backend/inventory/receive', [
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
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
    ]);

    $this->actingAs($user);

    $response = $this->postJson('/api/v1/backend/inventory/issue', [
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
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ]);

    $movementCount = StockMovement::query()->count();

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/issue', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ]);

    expect(StockMovement::query()->count())
        ->toBe($movementCount);
});

test('repeated receive API request with the same idempotency key does not duplicate stock', function () {
    $user = createInventoryApiUser(['system.inventory.receive']);
    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);
    $key = (string) Str::uuid();

    $payload = [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reason' => 'Idempotency API test',
        'idempotency_key' => $key,
    ];

    $this->actingAs($user);

    $first = $this->postJson('/api/v1/backend/inventory/receive', $payload);
    $first->assertCreated();

    $second = $this->postJson('/api/v1/backend/inventory/receive', $payload);
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

test('authorized transfer API dispatches to destination confirmation workflow', function () {
    $user = createInventoryApiUser(['system.inventory.transfer']);
    $receiver = createInventoryApiUser(['system.inventory.transfer']);
    $product = inventoryApiCreateProduct();
    $source = inventoryApiCreateLocation();
    $destination = inventoryApiCreateLocation();
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '10.0000',
    ]);

    $this->actingAs($user);
    $response = $this->postJson('/api/v1/backend/inventory/transfer', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '4.0000',
        'reason' => 'API transfer test',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'in_transit');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '6.0000',
    ]);
    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $destination->id,
    ]);

    $transfer = InventoryTransfer::query()->firstOrFail();
    $this->actingAs($receiver)->postJson("/api/v1/backend/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '4.0000', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', 'received');
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $destination->id, 'quantity' => '4.0000',
    ]);
    expect(StockMovement::query()->whereIn('movement_type', ['transfer_out', 'transfer_in'])->count())->toBe(2);
});

test('failed transfer API request leaves both location balances unchanged', function () {
    $user = createInventoryApiUser(['system.inventory.transfer']);
    $product = inventoryApiCreateProduct();
    $source = inventoryApiCreateLocation();
    $destination = inventoryApiCreateLocation();
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $user->locations()->attach($destination->id, ['access_level' => 'receive']);

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '2.0000',
    ]);

    $movementCount = StockMovement::query()->count();

    $this->actingAs($user);

    $this->postJson('/api/v1/backend/inventory/transfer', [
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

    expect(StockMovement::query()->count())
        ->toBe($movementCount);
});

test('low stock API returns only enabled thresholds using available quantity', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $location = inventoryApiCreateLocation();
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);

    $lowStockProduct = inventoryApiCreateProduct();
    $normalStockProduct = inventoryApiCreateProduct();
    $disabledAlertProduct = inventoryApiCreateProduct();

    InventoryStock::create([
        'product_id' => $lowStockProduct->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reserved_quantity' => '2.0000',
        'low_stock_threshold' => '3.0000',
    ]);

    InventoryStock::create([
        'product_id' => $normalStockProduct->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '2.0000',
        'low_stock_threshold' => '3.0000',
    ]);

    InventoryStock::create([
        'product_id' => $disabledAlertProduct->id,
        'location_id' => $location->id,
        'quantity' => '0.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0.0000',
    ]);

    $this->actingAs($user);

    $this->getJson('/api/v1/backend/inventory/low-stock')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $lowStockProduct->id)
        ->assertJsonPath('data.0.available_quantity', '3.0000')
        ->assertJsonPath('data.0.is_low_stock', true);
});

test('low stock API supports location and product filters with pagination', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $firstLocation = inventoryApiCreateLocation();
    $secondLocation = inventoryApiCreateLocation();
    $user->locations()->attach($firstLocation->id, ['access_level' => 'view']);

    $firstProduct = inventoryApiCreateProduct();
    $secondProduct = inventoryApiCreateProduct();

    InventoryStock::create([
        'product_id' => $firstProduct->id,
        'location_id' => $firstLocation->id,
        'quantity' => '2.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '5.0000',
    ]);

    InventoryStock::create([
        'product_id' => $secondProduct->id,
        'location_id' => $secondLocation->id,
        'quantity' => '1.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '5.0000',
    ]);

    $this->actingAs($user);

    $this->getJson(
        '/api/v1/backend/inventory/low-stock?location_id='.$firstLocation->id
        .'&product_id='.$firstProduct->id
        .'&per_page=1'
    )
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('per_page', 1)
        ->assertJsonPath('data.0.product_id', $firstProduct->id);
});

test('low stock API validates filters and pagination limits', function () {
    $user = createInventoryApiUser([
        'system.inventory.view',
    ]);

    $this->actingAs($user);

    $this->getJson('/api/v1/backend/inventory/low-stock?location_id=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location_id']);

    $this->getJson('/api/v1/backend/inventory/low-stock?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

test('user with inventory update permission can update low stock threshold without changing stock or ledger', function () {
    $user = createInventoryApiUser([
        'system.inventory.update',
    ]);

    $product = inventoryApiCreateProduct();
    $location = inventoryApiCreateLocation();
    $user->locations()->syncWithoutDetaching([$location->id => ['access_level' => 'manage']]);

    $stock = InventoryStock::create([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '2.0000',
        'low_stock_threshold' => '3.0000',
    ]);

    $movementCount = StockMovement::query()->count();

    $this->actingAs($user);

    $this->patchJson('/api/v1/backend/inventory/stocks/'.$stock->id.'/threshold', [
        'low_stock_threshold' => '20',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Low-stock threshold updated successfully.')
        ->assertJsonPath('data.low_stock_threshold', '20.0000')
        ->assertJsonPath('data.available_quantity', '8.0000')
        ->assertJsonPath('data.is_low_stock', true);

    $this->assertDatabaseHas('inventory_stocks', [
        'id' => $stock->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '2.0000',
        'low_stock_threshold' => '20.0000',
    ]);

    expect(StockMovement::query()->count())
        ->toBe($movementCount);
});

test('low stock threshold requires an integer of at least 20', function () {
    $user = createInventoryApiUser([
        'system.inventory.update',
    ]);

    $location = inventoryApiCreateLocation();
    $user->locations()->attach($location->id, ['access_level' => 'manage']);
    $stock = InventoryStock::create([
        'product_id' => inventoryApiCreateProduct()->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '20.0000',
    ]);

    $this->actingAs($user);

    // The minimum allowed threshold is 20, and valid values are whole numbers.
    foreach (['20', '50', '100'] as $validThreshold) {
        $this->patchJson('/api/v1/backend/inventory/stocks/'.$stock->id.'/threshold', [
            'low_stock_threshold' => $validThreshold,
        ])
            ->assertOk()
            ->assertJsonPath('data.low_stock_threshold', number_format((int) $validThreshold, 4, '.', ''));
    }

    foreach (['0', '19', '-5', '50.5', '1.12345'] as $invalidThreshold) {
        $this->patchJson('/api/v1/backend/inventory/stocks/'.$stock->id.'/threshold', [
            'low_stock_threshold' => $invalidThreshold,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['low_stock_threshold']);
    }
});

test('low stock threshold update returns not found for a missing stock row', function () {
    $user = createInventoryApiUser([
        'system.inventory.update',
    ]);

    $this->actingAs($user);

    $this->patchJson('/api/v1/backend/inventory/stocks/999999/threshold', [
        'low_stock_threshold' => '5.0000',
    ])->assertNotFound();
});

test('inventory stock and low-stock queries are limited to assigned locations', function () {
    $user = createInventoryApiUser(['system.inventory.view']);
    $assigned = inventoryApiCreateLocation();
    $unassigned = inventoryApiCreateLocation();
    $product = inventoryApiCreateProduct();
    $user->locations()->attach($assigned->id, ['access_level' => 'view']);

    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $assigned->id,
        'quantity' => '1.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '5.0000',
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $unassigned->id,
        'quantity' => '1.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '5.0000',
    ]);

    $this->actingAs($user)->getJson('/api/v1/backend/inventory/stocks')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.location_id', $assigned->id);
    $this->getJson('/api/v1/backend/inventory/low-stock')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.location_id', $assigned->id);
    $this->getJson('/api/v1/backend/inventory/stocks?location_id='.$unassigned->id)->assertForbidden();
});

test('receive requires receive or manage access to the target location', function () {
    $user = createInventoryApiUser(['system.inventory.receive']);
    $assigned = inventoryApiCreateLocation();
    $unassigned = inventoryApiCreateLocation();
    $product = inventoryApiCreateProduct();
    $user->locations()->attach($assigned->id, ['access_level' => 'receive']);

    $payload = ['product_id' => $product->id, 'quantity' => '2', 'reason' => 'Receiving test'];
    $this->actingAs($user)->postJson('/api/v1/backend/inventory/receive', $payload + ['location_id' => $unassigned->id])
        ->assertForbidden();
    $this->postJson('/api/v1/backend/inventory/receive', $payload + ['location_id' => $assigned->id])
        ->assertCreated();
});

test('issue and adjustment require manage access even when the global permission exists', function () {
    $user = createInventoryApiUser(['system.inventory.issue', 'system.inventory.adjust']);
    $receiveOnly = inventoryApiCreateLocation();
    $managed = inventoryApiCreateLocation();
    $product = inventoryApiCreateProduct();
    $user->locations()->attach($receiveOnly->id, ['access_level' => 'receive']);
    $user->locations()->attach($managed->id, ['access_level' => 'manage']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $receiveOnly->id,
        'quantity' => '10.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0.0000',
    ]);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $managed->id,
        'quantity' => '10.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0.0000',
    ]);

    $this->actingAs($user)->postJson('/api/v1/backend/inventory/issue', [
        'product_id' => $product->id, 'location_id' => $receiveOnly->id, 'quantity' => '1',
    ])->assertForbidden();
    $this->postJson('/api/v1/backend/inventory/adjust', [
        'product_id' => $product->id, 'location_id' => $receiveOnly->id, 'quantity' => '1',
    ])->assertForbidden();
    $this->postJson('/api/v1/backend/inventory/issue', [
        'product_id' => $product->id, 'location_id' => $managed->id, 'quantity' => '1',
    ])->assertOk();
});
