<?php

use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('receipt increases stock and records a ledger movement', function () {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();

    $movement = app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '12.5000',
        'reason' => 'Opening receipt',
    ]);

    expect($movement->movement_type)->toBe('receipt')
        ->and((string) $movement->quantity_delta)->toBe('12.5000')
        ->and((string) $movement->balance_before)->toBe('0.0000')
        ->and((string) $movement->balance_after)->toBe('12.5000');

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '12.5000',
    ]);

    $this->assertDatabaseCount('stock_movements', 1);
});

test('issue decreases stock without allowing a negative available balance', function () {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();

    $service = app(InventoryService::class);

    $service->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '10.0000',
    ]);

    $movement = $service->issue([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '3.2500',
    ]);

    expect((string) $movement->quantity_delta)->toBe('-3.2500')
        ->and((string) $movement->balance_after)->toBe('6.7500');

    try {
        $service->issue([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => '7.0000',
        ]);

        test()->fail('Expected insufficient-stock validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('quantity');
    }

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('6.7500');

    $this->assertDatabaseCount('stock_movements', 2);
});

test('a repeated receipt with the same idempotency key does not duplicate stock', function () {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    $key = (string) Str::uuid();

    $service = app(InventoryService::class);

    $payload = [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'idempotency_key' => $key,
        'reason' => 'Supplier receipt',
    ];

    $first = $service->receive($payload);
    $second = $service->receive($payload);

    expect($second->id)->toBe($first->id);

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('5.0000');

    $this->assertDatabaseCount('stock_movements', 1);
});

test('reusing an idempotency key with a different quantity is rejected', function () {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    $key = (string) Str::uuid();

    $service = app(InventoryService::class);

    $service->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'idempotency_key' => $key,
    ]);

    try {
        $service->receive([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => '8.0000',
            'idempotency_key' => $key,
        ]);

        test()->fail('Expected idempotency validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('idempotency_key');
    }

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('quantity')
    )->toBe('5.0000');

    $this->assertDatabaseCount('stock_movements', 1);
});

test('transfer decreases source and increases destination atomically', function () {
    $product = inventoryTestProduct();
    $source = inventoryTestLocation('source-'.strtolower(uniqid()));
    $destination = inventoryTestLocation('dest-'.strtolower(uniqid()));

    $service = app(InventoryService::class);

    $service->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '20.0000',
    ]);

    $result = $service->transfer([
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '7.5000',
        'reason' => 'Warehouse transfer',
    ]);

    expect($result['outgoing']->movement_type)->toBe('transfer_out')
        ->and($result['incoming']->movement_type)->toBe('transfer_in')
        ->and($result['outgoing']->transfer_group_id)
        ->toBe($result['incoming']->transfer_group_id);

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $source->id)
            ->value('quantity')
    )->toBe('12.5000');

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $destination->id)
            ->value('quantity')
    )->toBe('7.5000');

    $this->assertDatabaseCount('stock_movements', 3);
});

test('variable products keep stock on the variant rather than the parent', function () {
    $product = inventoryTestProduct('variable');
    $variant = inventoryTestVariant($product);
    $location = inventoryTestLocation();

    $movement = app(InventoryService::class)->receive([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '4.0000',
    ]);

    expect($movement->product_id)->toBeNull()
        ->and($movement->product_variant_id)->toBe($variant->id);

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => null,
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '4.0000',
    ]);
});

test('a variable product cannot receive stock directly', function () {
    $product = inventoryTestProduct('variable');
    $location = inventoryTestLocation();

    try {
        app(InventoryService::class)->receive([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => '3.0000',
        ]);

        test()->fail('Expected product-type validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('product_id');
    }

    $this->assertDatabaseCount('inventory_stocks', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('repeating a transfer with the same idempotency key does not transfer stock twice', function () {
    $product = inventoryTestProduct();
    $source = inventoryTestLocation('retry-source-'.strtolower(uniqid()));
    $destination = inventoryTestLocation('retry-dest-'.strtolower(uniqid()));

    $service = app(InventoryService::class);

    $service->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '20.0000',
    ]);

    $payload = [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '7.5000',
        'idempotency_key' => (string) Str::uuid(),
        'reason' => 'Retry safety test',
    ];

    $first = $service->transfer($payload);
    $second = $service->transfer($payload);

    expect($second['transfer_group_id'])->toBe($first['transfer_group_id'])
        ->and($second['outgoing']->id)->toBe($first['outgoing']->id)
        ->and($second['incoming']->id)->toBe($first['incoming']->id);

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $source->id)
            ->value('quantity')
    )->toBe('12.5000');

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $destination->id)
            ->value('quantity')
    )->toBe('7.5000');

    $this->assertDatabaseCount('stock_movements', 3);
});

test('a transfer idempotency key cannot be reused for a different transfer', function () {
    $product = inventoryTestProduct();
    $source = inventoryTestLocation('key-source-'.strtolower(uniqid()));
    $destination = inventoryTestLocation('key-dest-'.strtolower(uniqid()));
    $otherDestination = inventoryTestLocation('key-other-'.strtolower(uniqid()));

    $service = app(InventoryService::class);

    $service->receive([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '20.0000',
    ]);

    $key = (string) Str::uuid();

    $payload = [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '5.0000',
        'idempotency_key' => $key,
    ];

    $service->transfer($payload);

    try {
        $service->transfer([
            ...$payload,
            'destination_location_id' => $otherDestination->id,
        ]);

        test()->fail('Expected idempotency validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('idempotency_key');
    }

    expect(
        (string) InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $source->id)
            ->value('quantity')
    )->toBe('15.0000');

    expect(
        InventoryStock::query()
            ->where('product_id', $product->id)
            ->where('location_id', $otherDestination->id)
            ->exists()
    )->toBeFalse();

    $this->assertDatabaseCount('stock_movements', 3);
});

test('database rejects product-level inventory ownership for a variable product', function () {
    $product = inventoryTestProduct('variable');
    $location = inventoryTestLocation();

    try {
        DB::transaction(function () use ($product, $location): void {
            DB::table('inventory_stocks')->insert([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'location_id' => $location->id,
                'quantity' => '0.0000',
                'reserved_quantity' => '0.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        test()->fail('Expected the database owner relationship guard to reject the row.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('simple products');
    }

    $this->assertDatabaseCount('inventory_stocks', 0);
});

test('database prevents changing a simple product type after inventory history exists', function () {
    $product = inventoryTestProduct('simple');
    $location = inventoryTestLocation();

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '2.0000',
    ]);

    try {
        DB::transaction(function () use ($product): void {
            DB::table('products')->where('id', $product->id)->update([
                'product_type' => 'variable',
                'sku' => null,
                'updated_at' => now(),
            ]);
        });

        test()->fail('Expected the database product type guard to reject the change.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('cannot be converted to variable');
    }

    expect($product->fresh()->product_type)->toBe('simple');
});

test('database prevents soft deleting a variant with on-hand stock', function () {
    $product = inventoryTestProduct('variable');
    $variant = inventoryTestVariant($product);
    $location = inventoryTestLocation();

    app(InventoryService::class)->receive([
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'quantity' => '1.0000',
    ]);

    try {
        DB::transaction(function () use ($variant): void {
            DB::table('product_variants')->where('id', $variant->id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        test()->fail('Expected the database variant inventory guard to reject deletion.');
    } catch (QueryException $exception) {
        expect($exception->getMessage())->toContain('cannot be deleted');
    }

    expect(ProductVariant::query()->whereKey($variant->id)->exists())->toBeTrue();
});
