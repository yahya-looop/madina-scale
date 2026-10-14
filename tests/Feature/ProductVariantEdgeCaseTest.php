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

function variantEdgeGrantPermission(User $user, string $slug): void
{
    $suffix = uniqid();

    $module = Module::create([
        'name' => 'Variant Edge Module',
        'slug' => 'variant-edge-module-' . $suffix,
        'description' => 'Variant edge-case tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'Variant Edge Permission',
        'slug' => $slug,
        'description' => 'Permission for variant edge-case tests.',
    ]);

    $role = Role::create([
        'name' => 'Variant Edge Role',
        'slug' => 'variant-edge-role-' . $suffix,
        'description' => 'Role for variant edge-case tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);
}

function variantEdgeCreateProduct(): Product
{
    $suffix = uniqid();

    $category = ProductCategory::create([
        'name' => 'Edge Category ' . $suffix,
        'slug' => 'edge-category-' . $suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Edge Brand ' . $suffix,
        'slug' => 'edge-brand-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Edge Unit ' . $suffix,
        'short_name' => 'EU',
        'slug' => 'edge-unit-' . $suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Edge Product ' . $suffix,
        'slug' => 'edge-product-' . $suffix,
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'short_description' => 'Edge-case test product.',
        'description' => 'Product for variant edge-case tests.',
        'status' => 'draft',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);
}

function variantEdgePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Edge Variant ' . uniqid(),
        'sku' => 'EDGE-SKU-' . strtoupper(uniqid()),
        'barcode' => 'EDGE-BAR-' . strtoupper(uniqid()),
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

function variantEdgeCreateAttribute(string $name, string $status = 'active'): Attribute
{
    $suffix = uniqid();

    return Attribute::create([
        'name' => $name . ' ' . $suffix,
        'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . $suffix,
        'type' => 'select',
        'status' => $status,
        'sort_order' => 0,
    ]);
}

function variantEdgeCreateValue(
    Attribute $attribute,
    string $status = 'active'
): AttributeValue {
    $suffix = uniqid();

    return AttributeValue::create([
        'attribute_id' => $attribute->id,
        'name' => 'Edge Value ' . $suffix,
        'slug' => 'edge-value-' . $suffix,
        'value' => 'edge-' . $suffix,
        'status' => $status,
        'sort_order' => 0,
    ]);
}

test('variant creation rejects a value belonging to another attribute', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();

    $attributeA = variantEdgeCreateAttribute('Color');
    $attributeB = variantEdgeCreateAttribute('Size');

    $valueFromB = variantEdgeCreateValue($attributeB);

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [
                    [
                        'attribute_id' => $attributeA->id,
                        'attribute_value_id' => $valueFromB->id,
                    ],
                ],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'attribute_values.0.attribute_value_id',
        ]);

    $this->assertDatabaseCount('product_variants', 0);
});

test('variant creation rejects an inactive attribute value', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();
    $attribute = variantEdgeCreateAttribute('Color');
    $value = variantEdgeCreateValue($attribute, 'inactive');

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [
                    [
                        'attribute_id' => $attribute->id,
                        'attribute_value_id' => $value->id,
                    ],
                ],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'attribute_values.0.attribute_value_id',
        ]);

    $this->assertDatabaseCount('product_variants', 0);
});

test('variant creation rejects a non-selectable attribute type', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();
    $attribute = variantEdgeCreateAttribute('Text Attribute');

    $attribute->update(['type' => 'text']);

    $value = variantEdgeCreateValue($attribute);

    $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [
                    [
                        'attribute_id' => $attribute->id,
                        'attribute_value_id' => $value->id,
                    ],
                ],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'attribute_values.0.attribute_id',
        ]);

    $this->assertDatabaseCount('product_variants', 0);
});

test('partial update rejects a start date after the stored promotion end date', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.update');

    $product = variantEdgeCreateProduct();

    $variant = ProductVariant::create(array_merge(
        ['product_id' => $product->id],
        variantEdgePayload([
            'promotion_starts_at' => '2026-10-10 10:00:00',
            'promotion_ends_at' => '2026-10-20 10:00:00',
        ])
    ));

    $this->actingAs($user)
        ->putJson(
            "/api/products/{$product->id}/variants/{$variant->id}",
            ['promotion_starts_at' => '2026-10-21 10:00:00']
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['promotion_ends_at']);

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'promotion_starts_at' => '2026-10-10 10:00:00',
    ]);
});

test('setting a new default variant clears the previous default', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');
    variantEdgeGrantPermission($user, 'system.products.view');

    $product = variantEdgeCreateProduct();

    $first = $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => true])
        )
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($user)
        ->postJson(
            "/api/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => true])
        )
        ->assertCreated()
        ->json('data');

    $this->assertDatabaseHas('product_variants', [
        'id' => $first['id'],
        'product_id' => $product->id,
        'is_default' => false,
    ]);

    $this->assertDatabaseHas('product_variants', [
        'id' => $second['id'],
        'product_id' => $product->id,
        'is_default' => true,
    ]);

    $this->assertSame(
        1,
        ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_default', true)
            ->count()
    );
});
