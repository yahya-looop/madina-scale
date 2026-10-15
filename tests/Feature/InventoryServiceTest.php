<?php

use App\Models\Brand;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Services\InventoryService;
use Illuminate\Validation\ValidationException;

function inventoryTestProduct(string $type = 'simple'): Product
{
    $suffix = strtolower(uniqid());

    $category = ProductCategory::query()->create([
        'name' => 'Inventory Category ' . $suffix,
        'slug' => 'inventory-category-' . $suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::query()->create([
        'name' => 'Inventory Brand ' . $suffix,
        'slug' => 'inventory-brand-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::query()->create([
        'name' => 'Inventory Unit ' . $suffix,
        'short_name' => 'IU',
        'slug' => 'inventory-unit-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return Product::query()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Inventory Product ' . $suffix,
        'slug' => 'inventory-product-' . $suffix,
        'sku' => $type === 'simple'
        ? 'INV-P-' . strtoupper($suffix)
        : null,
        'product_type' => $type,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);
}

function inventoryTestLocation(string $suffix = ''): Location
{
    $suffix = $suffix ?: strtolower(uniqid());

    return Location::query()->create([
        'name' => 'Inventory Location ' . $suffix,
        'code' => 'INV-' . strtoupper($suffix),
        'type' => 'warehouse',
        'is_active' => true,
        'is_primary' => false,
        'country_code' => 'BD',
    ]);
}

function inventoryTestVariant(Product $product): ProductVariant
{
    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Inventory Test Variant',
        'sku' => 'INV-' . strtoupper(uniqid()),
        'barcode' => null,
        'cost_price' => 10,
        'selling_price' => 20,
        'wholesale_price' => null,
        'promotional_price' => null,
        'is_default' => true,
        'status' => 'active',
        'sort_order' => 0,
    ]);
}

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
    $key = (string) Illuminate\Support\Str::uuid();

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
    $key = (string) Illuminate\Support\Str::uuid();

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
    $source = inventoryTestLocation('source-' . strtolower(uniqid()));
    $destination = inventoryTestLocation('dest-' . strtolower(uniqid()));

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
