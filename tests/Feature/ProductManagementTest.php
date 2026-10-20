<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Models\Brand;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function productTestGrantPermission(User $user, string $permissionSlug): void
{
    $module = Module::create([
        'name' => 'Product Test Module',
        'slug' => 'product-test-module-' . uniqid(),
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
        'slug' => 'product-test-role-' . uniqid(),
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
        'name' => 'Test Category ' . $suffix,
        'slug' => 'test-category-' . $suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Test Brand ' . $suffix,
        'slug' => 'test-brand-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Test Unit ' . $suffix,
        'short_name' => 'TU',
        'slug' => 'test-unit-' . $suffix,
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
        'slug' => 'test-product-' . uniqid(),
        'sku' => 'SKU-' . strtoupper(uniqid()),
        'model_number' => 'MODEL-001',
        'barcode' => 'BAR-' . uniqid(),
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
        ->getJson('/api/products/' . $product->id)
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
        ->putJson('/api/products/' . $productId, array_merge($payload, [
            'name' => 'Updated Test Product',
        ]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Test Product');
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
        'sku' => 'UPDATED-' . strtoupper(uniqid()),
    ]);

    $this->actingAs($user)
        ->putJson('/api/products/' . $product->id, $payload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Test Product');

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Test Product',
    ]);
});

test('authorized user can soft delete a product', function () {
    $user = User::factory()->create();

    productTestGrantPermission($user, 'system.products.delete');

    $masterData = productTestMasterData();
    $product = Product::create(productTestPayload($masterData));

    $this->actingAs($user)
        ->deleteJson('/api/products/' . $product->id)
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

