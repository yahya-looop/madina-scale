<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function variantTestGrantPermission(User $user, string $permissionSlug): void
{
    $suffix = uniqid();

    $module = Module::create([
        'name' => 'Variant Test Module',
        'slug' => 'variant-test-module-' . $suffix,
        'description' => 'Module for variant tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'Variant Test Permission',
        'slug' => $permissionSlug,
        'description' => 'Permission for variant tests.',
    ]);

    $role = Role::create([
        'name' => 'Variant Test Role',
        'slug' => 'variant-test-role-' . $suffix,
        'description' => 'Role for variant tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);
}

function variantTestProductPayload(array $overrides = []): array
{
    $suffix = uniqid();

    $category = ProductCategory::create([
        'name' => 'Variant Category ' . $suffix,
        'slug' => 'variant-category-' . $suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Variant Brand ' . $suffix,
        'slug' => 'variant-brand-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Variant Unit ' . $suffix,
        'short_name' => 'VU',
        'slug' => 'variant-unit-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return array_merge([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Variable Product ' . $suffix,
        'slug' => 'variable-product-' . $suffix,
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'short_description' => 'Variant test product.',
        'description' => 'Product for variant feature tests.',
        'status' => 'draft',
        'visibility' => 'visible',
        'sort_order' => 0,
    ], $overrides);
}

function variantTestCreateProduct(array $overrides = []): Product
{
    return Product::create(variantTestProductPayload($overrides));
}

function variantTestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Variant ' . uniqid(),
        'sku' => 'VAR-SKU-' . strtoupper(uniqid()),
        'barcode' => 'VAR-BAR-' . strtoupper(uniqid()),
        'cost_price' => 10,
        'selling_price' => 20,
        'wholesale_price' => 15,
        'promotional_price' => null,
        'promotion_starts_at' => null,
        'promotion_ends_at' => null,
        'is_default' => false,
        'status' => 'active',
        'sort_order' => 0,
    ], $overrides);
}

test('variant listing requires authentication', function () {
    $product = variantTestCreateProduct();

    $this->getJson("/api/products/{$product->id}/variants")
        ->assertUnauthorized();
});

test('variant listing requires view permission', function () {
    $user = User::factory()->create();
    $product = variantTestCreateProduct();

    $this->actingAs($user)
        ->getJson("/api/products/{$product->id}/variants")
        ->assertForbidden();
});

test('authorized user can create and view a variant', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.create');
    variantTestGrantPermission($user, 'system.products.view');

    $product = variantTestCreateProduct();
    $payload = variantTestPayload();

    $created = $this->actingAs($user)
        ->postJson("/api/products/{$product->id}/variants", $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', $payload['name'])
        ->assertJsonPath('data.product_id', $product->id);

    $variantId = $created->json('data.id');

    $this->getJson("/api/products/{$product->id}/variants/{$variantId}")
        ->assertOk()
        ->assertJsonPath('data.id', $variantId)
        ->assertJsonPath('data.sku', $payload['sku']);

    $this->assertDatabaseHas('product_variants', [
        'id' => $variantId,
        'product_id' => $product->id,
        'name' => $payload['name'],
    ]);
});

test('variant creation rejects a simple product', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.create');

    $product = variantTestCreateProduct([
        'product_type' => 'simple',
        'sku' => 'SIMPLE-' . strtoupper(uniqid()),
        'barcode' => 'SIMPLE-BAR-' . strtoupper(uniqid()),
    ]);

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantTestPayload()
        )
        ->assertUnprocessable();
});

test('variant update and delete cannot cross product boundaries', function () {
    $user = User::factory()->create();

    variantTestGrantPermission($user, 'system.products.update');
    variantTestGrantPermission($user, 'system.products.delete');

    $productA = variantTestCreateProduct();
    $productB = variantTestCreateProduct();

    $variant = ProductVariant::create(array_merge(
        ['product_id' => $productB->id],
        variantTestPayload()
    ));

    $this->actingAs($user)
        ->putJson(
            "/api/products/{$productA->id}/variants/{$variant->id}",
            ['name' => 'Unauthorized Change']
        )
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(
            "/api/products/{$productA->id}/variants/{$variant->id}"
        )
        ->assertNotFound();

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'product_id' => $productB->id,
        'name' => $variant->name,
    ]);
});

test('variant creation rejects duplicate sku regardless of case', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.create');

    $productA = variantTestCreateProduct();
    $productB = variantTestCreateProduct();

    $sku = 'DUP-VAR-' . strtoupper(uniqid());

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$productA->id}/variants",
            variantTestPayload(['sku' => $sku])
        )
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$productB->id}/variants",
            variantTestPayload(['sku' => strtolower($sku)])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('variant creation rejects duplicate barcode regardless of case', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.create');

    $productA = variantTestCreateProduct();
    $productB = variantTestCreateProduct();

    $barcode = 'DUP-VBAR-' . strtoupper(uniqid());

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$productA->id}/variants",
            variantTestPayload(['barcode' => $barcode])
        )
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$productB->id}/variants",
            variantTestPayload(['barcode' => strtolower($barcode)])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode']);
});

test('variant update validates promotion date range', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.update');

    $product = variantTestCreateProduct();

    $variant = ProductVariant::create(array_merge(
        ['product_id' => $product->id],
        variantTestPayload()
    ));

    $this->actingAs($user)
        ->putJson(
            "/api/products/{$product->id}/variants/{$variant->id}",
            [
                'promotion_starts_at' => '2026-10-20 10:00:00',
                'promotion_ends_at' => '2026-10-19 10:00:00',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'promotion_ends_at',
        ]);
});

test('authorized user can soft delete a variant', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.delete');

    $product = variantTestCreateProduct();

    $variant = ProductVariant::create(array_merge(
        ['product_id' => $product->id],
        variantTestPayload()
    ));

    $this->actingAs($user)
        ->deleteJson("/api/products/{$product->id}/variants/{$variant->id}")
        ->assertOk();

    $this->assertSoftDeleted('product_variants', [
        'id' => $variant->id,
    ]);
});

test('variant creation rejects an inactive attribute', function () {
    $user = User::factory()->create();
    variantTestGrantPermission($user, 'system.products.create');

    $product = variantTestCreateProduct();
    $suffix = uniqid();

    $attribute = Attribute::create([
        'name' => 'Inactive Attribute ' . $suffix,
        'slug' => 'inactive-attribute-' . $suffix,
        'type' => 'select',
        'status' => 'inactive',
        'sort_order' => 0,
    ]);

    $value = AttributeValue::create([
        'attribute_id' => $attribute->id,
        'name' => 'Test Value',
        'slug' => 'test-value-' . $suffix,
        'value' => 'test-value',
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantTestPayload([
                'attribute_values' => [
                    ['attribute_id' => $attribute->id, 'attribute_value_id' => $value->id],
                ],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'attribute_values.0.attribute_id',
        ]);
});
