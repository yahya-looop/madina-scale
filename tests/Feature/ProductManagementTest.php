<?php

use App\Http\Controllers\ProductController;
use App\Models\Brand;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

function productDimensionTestPng(int $width, int $height): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    };

    $row = "\x00".str_repeat("\x00", $width * 3);
    $imageData = gzcompress(str_repeat($row, $height));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('N2C5', $width, $height, 8, 2, 0, 0, 0))
        .$chunk('IDAT', $imageData)
        .$chunk('IEND', '');
}

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function productTestGrantPermission(User $user, string $permissionSlug): void
{
    $module = Module::create([
        'name' => 'Product Test Module',
        'slug' => 'product-test-module-'.uniqid(),
        'description' => 'Module for product feature tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'Product Test Permission',
        'slug' => $permissionSlug,
        'description' => 'Permission for product feature tests.',
    ]);

    $role = Role::create([
        'name' => 'Product Test Role',
        'slug' => 'product-test-role-'.uniqid(),
        'description' => 'Role for product feature tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);
}

function productTestMasterData(): array
{
    $suffix = uniqid();

    $category = ProductCategory::create([
        'name' => 'Test Category '.$suffix,
        'slug' => 'test-category-'.$suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Test Brand '.$suffix,
        'slug' => 'test-brand-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Test Unit '.$suffix,
        'short_name' => 'TU',
        'slug' => 'test-unit-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return compact('category', 'brand', 'unit');
}

function productTestPayload(array $masterData, array $overrides = []): array
{
    return array_merge([
        'category_id' => $masterData['category']->id,
        'brand_id' => $masterData['brand']->id,
        'unit_id' => $masterData['unit']->id,
        'name' => 'Test Product',
        'slug' => 'test-product-'.uniqid(),
        'sku' => 'SKU-'.strtoupper(uniqid()),
        'model_number' => 'MODEL-001',
        'barcode' => 'BAR-'.uniqid(),
        'product_type' => 'simple',
        'short_description' => 'Product test short description.',
        'description' => 'Product test description.',
        'status' => 'draft',
        'visibility' => 'visible',
        'sort_order' => 0,
    ], $overrides);
}

test('product listing requires authentication', function () {
    $this->getJson('/api/products')
        ->assertUnauthorized();
});

test('product listing requires view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/products')
        ->assertForbidden();
});

test('authorized user can list products', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.view');

    $this->actingAs($user)
        ->getJson('/api/products')
        ->assertOk()
        ->assertJsonStructure([
            'data',
        ]);
});

test('authorized user can view a product', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.view');

    $masterData = productTestMasterData();

    $product = Product::create(productTestPayload($masterData));

    $this->actingAs($user)
        ->getJson('/api/products/'.$product->id)
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', $product->name);
});

test('authorized user can create a product', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', $payload['name']);

    $this->assertDatabaseHas('products', [
        'name' => $payload['name'],
        'category_id' => $masterData['category']->id,
        'unit_id' => $masterData['unit']->id,
    ]);
});

test('variable product creation persists every submitted variant and selects one default', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
        'variants' => [
            [
                'name' => 'Black / Small',
                'sku' => 'var-black-small-'.strtoupper(uniqid()),
                'barcode' => 'bar-black-small-'.strtoupper(uniqid()),
                'cost_price' => 40,
                'selling_price' => 75,
                'is_default' => false,
                'status' => 'active',
                'sort_order' => 0,
            ],
            [
                'name' => 'White / Medium',
                'sku' => 'var-white-medium-'.strtoupper(uniqid()),
                'barcode' => 'bar-white-medium-'.strtoupper(uniqid()),
                'cost_price' => 45,
                'selling_price' => 85,
                'is_default' => true,
                'status' => 'active',
                'sort_order' => 1,
            ],
        ],
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated();

    $productId = (int) $response->json('data.id');

    expect(Product::query()->findOrFail($productId)->product_type)->toBe('variable');
    expect(Product::query()->findOrFail($productId)->variants()->count())->toBe(2);
    expect(Product::query()->findOrFail($productId)->variants()->where('is_default', true)->value('name'))
        ->toBe('White / Medium');

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $productId,
        'name' => 'Black / Small',
        'selling_price' => '75.00',
        'is_default' => false,
    ]);
    $this->assertDatabaseHas('product_variants', [
        'product_id' => $productId,
        'name' => 'White / Medium',
        'sku' => strtoupper($payload['variants'][1]['sku']),
        'selling_price' => '85.00',
        'is_default' => true,
    ]);
});

test('variable product creation chooses the first active variant when no default is submitted', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
        'variants' => [
            [
                'name' => 'First Available Variant',
                'selling_price' => 30,
                'is_default' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Second Available Variant',
                'selling_price' => 40,
                'is_default' => false,
                'status' => 'active',
            ],
        ],
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated();

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $response->json('data.id'),
        'name' => 'First Available Variant',
        'is_default' => true,
    ]);
});

test('variable product creation rejects multiple default variants', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
        'variants' => [
            ['name' => 'First Default', 'selling_price' => 30, 'is_default' => true],
            ['name' => 'Second Default', 'selling_price' => 40, 'is_default' => true],
        ],
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.1.is_default']);

    $this->assertDatabaseCount('products', 0);
});

test('variable product creation rejects duplicate variant identifiers without partial writes', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $duplicateSku = 'DUPLICATE-VARIANT-'.strtoupper(uniqid());
    $payload = productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
        'variants' => [
            [
                'name' => 'Variant One',
                'sku' => $duplicateSku,
                'selling_price' => 50,
                'is_default' => true,
            ],
            [
                'name' => 'Variant Two',
                'sku' => strtolower($duplicateSku),
                'selling_price' => 60,
                'is_default' => false,
            ],
        ],
    ]);
    $productCount = Product::query()->count();

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.1.sku']);

    expect(Product::query()->count())->toBe($productCount);
    $this->assertDatabaseCount('product_variants', 0);
});

test('variable product creation rejects identifiers already owned by another variant', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $existingProduct = Product::create(productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]));
    $existingSku = 'OWNED-VARIANT-'.strtoupper(uniqid());
    $existingProduct->variants()->create([
        'name' => 'Existing Variant',
        'sku' => $existingSku,
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => true,
        'status' => 'active',
    ]);

    $simplePayload = productTestPayload($masterData, [
        'name' => 'Conflicting Simple Product',
        'slug' => 'conflicting-simple-product-'.uniqid(),
        'sku' => strtolower($existingSku),
        'barcode' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $simplePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);

    $payload = productTestPayload($masterData, [
        'name' => 'Second Variable Product',
        'slug' => 'second-variable-product-'.uniqid(),
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'variants' => [[
            'name' => 'Conflicting Variant',
            'sku' => strtolower($existingSku),
            'selling_price' => 25,
            'is_default' => true,
        ]],
    ]);
    $productCount = Product::query()->count();

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.0.sku']);

    expect(Product::query()->count())->toBe($productCount);
});

test('variable product creation requires at least one variant', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants']);
});

test('product creation can save initial pricing on a default variant', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData, [
        'cost_price' => 42.50,
        'selling_price' => 79.99,
        'wholesale_price' => 69.00,
        'promotional_price' => 74.99,
    ]);

    $response = $this->actingAs($user)->postJson('/api/products', $payload);
    $response->assertCreated();
    $productId = (int) $response->json('data.id');

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $productId,
        'selling_price' => '79.99',
        'cost_price' => '42.50',
        'wholesale_price' => '69.00',
        'promotional_price' => '74.99',
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $productId,
        'sku' => null,
        'barcode' => null,
    ]);

    // The price-only variant must not block ordinary simple-product edits.
    productTestGrantPermission($user, 'system.products.update');
    $this->actingAs($user)
        ->putJson('/api/products/'.$productId, array_merge($payload, [
            'name' => 'Updated Test Product',
        ]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Test Product');
});

test('variable product updates do not require resubmitting variant records', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.update');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]));
    $variant = $product->variants()->create([
        'name' => 'Existing Variant',
        'sku' => 'EXISTING-VARIANT-'.strtoupper(uniqid()),
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->putJson('/api/products/'.$product->id, [
            'category_id' => $masterData['category']->id,
            'brand_id' => $masterData['brand']->id,
            'unit_id' => $masterData['unit']->id,
            'name' => 'Updated Variable Product',
            'product_type' => 'variable',
            'status' => 'active',
            'visibility' => 'visible',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Variable Product');

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'product_id' => $product->id,
        'sku' => $variant->sku,
    ]);
});

test('changing a simple product to variable clears parent identifiers and retains its price record', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.update');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));
    $priceRecord = $product->variants()->create([
        'name' => 'Standard',
        'cost_price' => 12,
        'selling_price' => 25,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->putJson('/api/products/'.$product->id, [
            'category_id' => $masterData['category']->id,
            'brand_id' => $masterData['brand']->id,
            'unit_id' => $masterData['unit']->id,
            'name' => $product->name,
            'product_type' => 'variable',
            'status' => 'active',
            'visibility' => 'visible',
            'sku' => $product->sku,
            'barcode' => $product->barcode,
        ])
        ->assertOk()
        ->assertJsonPath('data.product_type', 'variable')
        ->assertJsonPath('data.sku', null)
        ->assertJsonPath('data.barcode', null);

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]);
    $this->assertDatabaseHas('product_variants', [
        'id' => $priceRecord->id,
        'product_id' => $product->id,
        'selling_price' => '25.00',
        'is_default' => true,
    ]);
});

test('variable products with active variants cannot be changed to simple products', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.update');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]));
    $product->variants()->create([
        'name' => 'Existing Variant',
        'sku' => 'LOCKED-VARIANT-'.strtoupper(uniqid()),
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->putJson('/api/products/'.$product->id, [
            'category_id' => $masterData['category']->id,
            'brand_id' => $masterData['brand']->id,
            'unit_id' => $masterData['unit']->id,
            'name' => $product->name,
            'product_type' => 'simple',
            'sku' => 'SIMPLE-'.strtoupper(uniqid()),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_type']);

    expect($product->fresh()->product_type)->toBe('variable');
});

test('product search matches variable product variant SKU and barcode', function () {
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.view');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData, [
        'product_type' => 'variable',
        'sku' => null,
        'barcode' => null,
    ]));
    $variantSku = 'SEARCH-VARIANT-'.strtoupper(uniqid());
    $product->variants()->create([
        'name' => 'Searchable Variant',
        'sku' => $variantSku,
        'barcode' => 'SEARCH-BAR-'.strtoupper(uniqid()),
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->getJson('/api/products?search='.$variantSku)
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id]);
});

test('product creation rejects a missing required category', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData);
    unset($payload['category_id']);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

test('product creation rejects a missing sku for simple products', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData);
    unset($payload['sku']);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('product creation rejects a soft deleted category', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $masterData['category']->delete();

    $payload = productTestPayload($masterData);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id']);
});

test('product creation rejects a soft deleted brand', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $masterData['brand']->delete();

    $payload = productTestPayload($masterData);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['brand_id']);
});

test('product creation rejects a soft deleted unit', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $masterData['unit']->delete();

    $payload = productTestPayload($masterData);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

test('product creation generates a unique slug when the requested slug exists', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();

    $payload = productTestPayload($masterData, [
        'slug' => 'duplicate-product-slug',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated();

    $secondPayload = productTestPayload($masterData, [
        'slug' => 'duplicate-product-slug',
        'name' => 'Second Test Product',
    ]);

    $response = $this->actingAs($user)
        ->postJson('/api/products', $secondPayload)
        ->assertCreated();

    expect($response->json('data.slug'))
        ->toBe('duplicate-product-slug-2');
});

test('product creation rejects a duplicate sku regardless of case', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();

    $payload = productTestPayload($masterData, [
        'sku' => 'DUPLICATE-SKU',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated();

    $duplicatePayload = productTestPayload($masterData, [
        'sku' => 'duplicate-sku',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $duplicatePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('product creation rejects a duplicate barcode regardless of case', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();

    $payload = productTestPayload($masterData, [
        'barcode' => 'DUPLICATE-BARCODE',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertCreated();

    $duplicatePayload = productTestPayload($masterData, [
        'barcode' => 'duplicate-barcode',
    ]);

    $this->actingAs($user)
        ->postJson('/api/products', $duplicatePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode']);
});

test('authorized user can update a product', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.update');

    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));

    $payload = productTestPayload($masterData, [
        'name' => 'Updated Test Product',
        'slug' => 'updated-test-product',
        'sku' => 'UPDATED-'.strtoupper(uniqid()),
    ]);

    $this->actingAs($user)
        ->putJson('/api/products/'.$product->id, $payload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Test Product');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Test Product',
    ]);
});

test('product media can be promoted, reordered and have alternative text updated', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.view');
    productTestGrantPermission($user, 'system.products.update');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));

    $firstPath = 'products/'.$product->id.'/images/first.webp';
    $secondPath = 'products/'.$product->id.'/images/second.webp';
    Storage::disk('public')->put($firstPath, 'first-image');
    Storage::disk('public')->put($secondPath, 'second-image');
    $first = ProductMedia::create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => $firstPath,
        'collection' => 'images',
        'alt_text' => 'First image',
        'sort_order' => 0,
        'is_primary' => true,
    ]);
    $second = ProductMedia::create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => $secondPath,
        'collection' => 'images',
        'alt_text' => 'Second image',
        'sort_order' => 1,
        'is_primary' => false,
    ]);

    $this->actingAs($user)
        ->postJson("/api/products/{$product->id}/media/{$second->id}/primary")
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    $this->patchJson("/api/products/{$product->id}/media/{$second->id}", [
        'alt_text' => 'Updated second image description',
    ])->assertOk()->assertJsonPath('data.alt_text', 'Updated second image description');

    $this->patchJson("/api/products/{$product->id}/media/reorder", [
        'media_ids' => [$second->id, $first->id],
    ])->assertOk();

    $this->patchJson("/api/products/{$product->id}/media/reorder", [
        'media_ids' => [$first->id, $second->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['media_ids']);

    $this->assertDatabaseHas('product_media', [
        'id' => $first->id,
        'is_primary' => false,
        'sort_order' => 1,
    ]);
    $this->assertDatabaseHas('product_media', [
        'id' => $second->id,
        'is_primary' => true,
        'sort_order' => 0,
        'alt_text' => 'Updated second image description',
    ]);
});

test('product media actions enforce permissions and nested product ownership', function () {
    Storage::fake('public');
    $viewer = User::factory()->create();
    productTestGrantPermission($viewer, 'system.products.view');
    $masterData = productTestMasterData();
    $firstProduct = Product::create(productTestPayload($masterData));
    $secondProduct = Product::create(productTestPayload($masterData, [
        'name' => 'Second Product',
        'slug' => 'second-product-'.uniqid(),
        'sku' => 'SECOND-SKU-'.strtoupper(uniqid()),
        'barcode' => 'SECOND-BAR-'.strtoupper(uniqid()),
    ]));
    $media = ProductMedia::create([
        'product_id' => $secondProduct->id,
        'disk' => 'public',
        'path' => 'products/'.$secondProduct->id.'/images/second.webp',
        'collection' => 'images',
        'is_primary' => true,
    ]);

    $this->actingAs($viewer)
        ->postJson("/api/products/{$firstProduct->id}/media/{$media->id}/primary")
        ->assertNotFound();

    $this->patchJson("/api/products/{$secondProduct->id}/media/{$media->id}", [
        'alt_text' => 'Unauthorized update',
    ])->assertForbidden();

    $this->deleteJson("/api/products/{$secondProduct->id}/media/{$media->id}")
        ->assertForbidden();
});

test('deleting the primary product image promotes the next image and removes its file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.delete');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));

    $primaryPath = 'products/'.$product->id.'/images/primary.webp';
    $replacementPath = 'products/'.$product->id.'/images/replacement.webp';
    Storage::disk('public')->put($primaryPath, 'primary-image');
    Storage::disk('public')->put($replacementPath, 'replacement-image');
    $primary = ProductMedia::create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => $primaryPath,
        'collection' => 'images',
        'sort_order' => 0,
        'is_primary' => true,
    ]);
    $replacement = ProductMedia::create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => $replacementPath,
        'collection' => 'images',
        'sort_order' => 1,
        'is_primary' => false,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/products/{$product->id}/media/{$primary->id}")
        ->assertOk()
        ->assertJsonPath('data.primary_media_id', $replacement->id);

    $this->assertSoftDeleted('product_media', ['id' => $primary->id]);
    $this->assertDatabaseHas('product_media', [
        'id' => $replacement->id,
        'is_primary' => true,
        'deleted_at' => null,
    ]);
    Storage::disk('public')->assertMissing($primaryPath);
});

test('authorized user can soft delete a product', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.delete');

    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));

    $this->actingAs($user)
        ->deleteJson('/api/products/'.$product->id)
        ->assertOk();

    $this->assertSoftDeleted('products', [
        'id' => $product->id,
    ]);
});

test('product creation allows an optional image and records primary media', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.create');

    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData);

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=',
        true,
    );

    if ($png === false) {
        throw new RuntimeException('Could not decode PNG test fixture.');
    }

    $payload['image'] = UploadedFile::fake()->createWithContent(
        'product.png',
        $png,
    );

    $this->actingAs($user)
        ->post('/api/products', $payload, [
            'Accept' => 'application/json',
        ])
        ->assertCreated();

    $product = Product::query()
        ->where('name', $payload['name'])
        ->firstOrFail();

    $this->assertDatabaseHas('product_media', [
        'product_id' => $product->id,
        'collection' => 'images',
        'is_primary' => true,
    ]);

    $media = $product->primaryMedia()->firstOrFail();

    $this->assertTrue(Storage::disk('public')->exists($media->path));
});

test('product image uploads reject excessive image dimensions', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.create');
    $masterData = productTestMasterData();
    $payload = productTestPayload($masterData);
    $payload['image'] = UploadedFile::fake()->createWithContent('oversized.png', productDimensionTestPng(5001, 1));

    $this->actingAs($user)
        ->postJson('/api/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    $this->assertDatabaseCount('products', 0);
    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});

test('failed product media file deletion restores the active media record and primary state', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    productTestGrantPermission($user, 'system.products.delete');
    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));
    $path = 'products/'.$product->id.'/images/primary-failure.webp';
    $fakeDisk = Storage::disk('public');
    $fakeDisk->put($path, 'primary-image');
    $media = ProductMedia::create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => $path,
        'collection' => 'images',
        'is_primary' => true,
    ]);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
    $disk->shouldReceive('delete')->once()->with($path)->andReturn(false);
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

    $this->actingAs($user)
        ->deleteJson("/api/products/{$product->id}/media/{$media->id}")
        ->assertStatus(500);

    $this->assertDatabaseHas('product_media', [
        'id' => $media->id,
        'deleted_at' => null,
        'is_primary' => true,
    ]);
    $fakeDisk->assertExists($path);
});

test('replaced product image cleanup logs a failed storage delete without failing the update', function () {
    $path = 'products/42/images/old-primary.webp';
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->with($path)->andReturn(true);
    $disk->shouldReceive('delete')->once()->with($path)->andReturn(false);
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
    Log::spy();

    $controller = app(ProductController::class);
    $method = new ReflectionMethod($controller, 'deleteReplacedProductMediaFile');
    $method->invoke($controller, 42, 'public', $path);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Could not delete replaced product media file.', Mockery::on(
            fn (array $context): bool => $context['product_id'] === 42
                && $context['path'] === $path,
        ));
});

test('replaced product image cleanup refuses paths outside the product image directory', function () {
    Storage::shouldReceive('disk')->never();
    Log::spy();

    $controller = app(ProductController::class);
    $method = new ReflectionMethod($controller, 'deleteReplacedProductMediaFile');
    $method->invoke($controller, 42, 'public', 'products/43/images/other.webp');

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Skipped unsafe replaced product media cleanup.', Mockery::type('array'));
});
