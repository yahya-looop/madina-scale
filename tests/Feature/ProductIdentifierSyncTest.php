<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function identifierTestMasterData(): array
{
    $suffix = strtolower((string) str()->uuid());

    $category = ProductCategory::create([
        'name' => 'Identifier Category '.$suffix,
        'slug' => 'identifier-category-'.$suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Identifier Brand '.$suffix,
        'slug' => 'identifier-brand-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Identifier Unit '.$suffix,
        'short_name' => 'IU',
        'slug' => 'identifier-unit-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return compact('category', 'brand', 'unit');
}

function identifierTestProductPayload(
    array $masterData,
    array $overrides = [],
): array {
    $suffix = strtoupper((string) str()->uuid());

    return array_merge([
        'category_id' => $masterData['category']->id,
        'brand_id' => $masterData['brand']->id,
        'unit_id' => $masterData['unit']->id,
        'name' => 'Identifier Product',
        'slug' => 'identifier-product-'.strtolower($suffix),
        'sku' => 'IDENT-SKU-'.$suffix,
        'barcode' => 'IDENT-BAR-'.$suffix,
        'product_type' => 'simple',
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ], $overrides);
}

function identifierTestCreateVariableProduct(
    array $masterData,
): Product {
    return Product::create(
        identifierTestProductPayload($masterData, [
            'name' => 'Variable Product',
            'product_type' => 'variable',
            'sku' => null,
            'barcode' => null,
        ])
    );
}

function identifierTestCreateVariant(
    Product $product,
    array $overrides = [],
): ProductVariant {
    $suffix = strtoupper((string) str()->uuid());

    return ProductVariant::create(array_merge([
        'product_id' => $product->id,
        'name' => 'Default Variant',
        'sku' => 'VAR-SKU-'.$suffix,
        'barcode' => 'VAR-BAR-'.$suffix,
        'cost_price' => 10,
        'selling_price' => 15,
        'is_default' => true,
        'status' => 'active',
        'sort_order' => 0,
    ], $overrides));
}

test('simple product SKU and barcode are registered', function () {
    $masterData = identifierTestMasterData();
    $payload = identifierTestProductPayload($masterData);

    $product = Product::create($payload);

    foreach ([
        'sku' => $payload['sku'],
        'barcode' => $payload['barcode'],
    ] as $type => $value) {
        $this->assertDatabaseHas('product_identifiers', [
            'owner_product_id' => $product->id,
            'owner_variant_id' => null,
            'identifier_type' => $type,
            'identifier_value' => $value,
            'normalized_value' => strtolower($value),
            'deleted_at' => null,
        ]);
    }
});

test('variable product identifiers are registered against variants', function () {
    $masterData = identifierTestMasterData();
    $product = identifierTestCreateVariableProduct($masterData);
    $variant = identifierTestCreateVariant($product);

    foreach ([
        'sku' => $variant->sku,
        'barcode' => $variant->barcode,
    ] as $type => $value) {
        $this->assertDatabaseHas('product_identifiers', [
            'owner_product_id' => null,
            'owner_variant_id' => $variant->id,
            'identifier_type' => $type,
            'normalized_value' => strtolower($value),
            'deleted_at' => null,
        ]);
    }

    $this->assertDatabaseMissing('product_identifiers', [
        'owner_product_id' => $product->id,
        'deleted_at' => null,
    ]);
});

test('changing only the product name does not create identifier history', function () {
    $masterData = identifierTestMasterData();
    $product = Product::create(
        identifierTestProductPayload($masterData)
    );

    $before = DB::table('product_identifiers')
        ->where('owner_product_id', $product->id)
        ->where('identifier_type', 'sku')
        ->count();

    $product->update(['name' => 'Renamed Product']);

    $after = DB::table('product_identifiers')
        ->where('owner_product_id', $product->id)
        ->where('identifier_type', 'sku')
        ->count();

    expect($after)->toBe($before);
});

test('changing SKU preserves the old identifier as history', function () {
    $masterData = identifierTestMasterData();
    $payload = identifierTestProductPayload($masterData);
    $product = Product::create($payload);

    $oldSku = strtolower($payload['sku']);
    $newSku = 'REPLACEMENT-'.strtoupper((string) str()->uuid());

    $product->update(['sku' => $newSku]);

    $this->assertDatabaseHas('product_identifiers', [
        'owner_product_id' => $product->id,
        'identifier_type' => 'sku',
        'normalized_value' => $oldSku,
    ]);

    $this->assertDatabaseHas('product_identifiers', [
        'owner_product_id' => $product->id,
        'identifier_type' => 'sku',
        'identifier_value' => $newSku,
        'normalized_value' => strtolower($newSku),
        'deleted_at' => null,
    ]);

    expect(
        DB::table('product_identifiers')
            ->where('owner_product_id', $product->id)
            ->where('identifier_type', 'sku')
            ->where('normalized_value', $oldSku)
            ->whereNotNull('deleted_at')
            ->exists()
    )->toBeTrue();
});

test('duplicate SKU is rejected case insensitively across products', function () {
    $masterData = identifierTestMasterData();
    $firstPayload = identifierTestProductPayload($masterData);

    Product::create($firstPayload);

    $secondPayload = identifierTestProductPayload($masterData, [
        'sku' => strtolower($firstPayload['sku']),
    ]);

    expect(fn () => Product::create($secondPayload))
        ->toThrow(QueryException::class);
});

test('SKU cannot be shared between a product and a variant', function () {
    $masterData = identifierTestMasterData();
    $simplePayload = identifierTestProductPayload($masterData);

    Product::create($simplePayload);

    $variableProduct = identifierTestCreateVariableProduct($masterData);

    expect(fn () => identifierTestCreateVariant(
        $variableProduct,
        ['sku' => strtolower($simplePayload['sku'])],
    ))->toThrow(QueryException::class);
});

test('duplicate barcode is rejected across products and variants', function () {
    $masterData = identifierTestMasterData();
    $firstPayload = identifierTestProductPayload($masterData);

    Product::create($firstPayload);

    $variableProduct = identifierTestCreateVariableProduct($masterData);

    expect(fn () => identifierTestCreateVariant(
        $variableProduct,
        ['barcode' => strtolower($firstPayload['barcode'])],
    ))->toThrow(QueryException::class);
});

test('soft deleting a simple product releases its identifiers and keeps history', function () {
    $masterData = identifierTestMasterData();
    $payload = identifierTestProductPayload($masterData);
    $product = Product::create($payload);

    $product->delete();

    $this->assertDatabaseMissing('product_identifiers', [
        'owner_product_id' => $product->id,
        'identifier_type' => 'sku',
        'deleted_at' => null,
    ]);

    $replacement = identifierTestProductPayload($masterData, [
        'sku' => $payload['sku'],
        'barcode' => $payload['barcode'],
    ]);

    $newProduct = Product::create($replacement);

    $this->assertDatabaseHas('product_identifiers', [
        'owner_product_id' => $newProduct->id,
        'identifier_type' => 'sku',
        'normalized_value' => strtolower($payload['sku']),
        'deleted_at' => null,
    ]);

    expect(
        DB::table('product_identifiers')
            ->where('owner_product_id', $product->id)
            ->where('identifier_type', 'sku')
            ->whereNotNull('deleted_at')
            ->exists()
    )->toBeTrue();
});

test('restoring a product fails if its old SKU has been reused', function () {
    $masterData = identifierTestMasterData();
    $originalPayload = identifierTestProductPayload($masterData);
    $original = Product::create($originalPayload);

    $original->delete();

    $replacement = identifierTestProductPayload($masterData, [
        'sku' => $originalPayload['sku'],
        'barcode' => 'NEW-BAR-'.strtoupper((string) str()->uuid()),
    ]);

    Product::create($replacement);

    $originalId = $original->id;

    // Run the conflicting restore in a separate PostgreSQL session.
    $connection = DB::connection('pgsql');
    $connection->beginTransaction();

    try {
        $original->restore();

        test()->fail('Restoring a reused SKU should throw a QueryException.');
    } catch (QueryException $exception) {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }

        expect(
            DB::table('products')
                ->where('id', $originalId)
                ->whereNotNull('deleted_at')
                ->exists()
        )->toBeTrue();
    }
});

test('soft deleting and restoring a variable product synchronizes variant identifiers', function () {
    $masterData = identifierTestMasterData();
    $product = identifierTestCreateVariableProduct($masterData);
    $variant = identifierTestCreateVariant($product);

    $product->delete();

    $this->assertDatabaseMissing('product_identifiers', [
        'owner_variant_id' => $variant->id,
        'identifier_type' => 'sku',
        'deleted_at' => null,
    ]);

    $product->restore();

    $this->assertDatabaseHas('product_identifiers', [
        'owner_variant_id' => $variant->id,
        'identifier_type' => 'sku',
        'normalized_value' => strtolower($variant->sku),
        'deleted_at' => null,
    ]);
});

test('variable products cannot have a parent SKU or barcode', function () {
    $masterData = identifierTestMasterData();
    $product = identifierTestCreateVariableProduct($masterData);

    expect(fn () => $product->update([
        'sku' => 'INVALID-PARENT-'.strtoupper((string) str()->uuid()),
    ]))->toThrow(QueryException::class);
});

test('active simple products cannot have an empty SKU', function () {
    $masterData = identifierTestMasterData();

    $payload = identifierTestProductPayload($masterData, [
        'sku' => null,
    ]);

    expect(fn () => Product::create($payload))
        ->toThrow(QueryException::class);
});
