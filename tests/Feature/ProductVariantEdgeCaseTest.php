<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\PricingEngine;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function variantEdgeGrantPermission(User $user, string $slug): void
{
    $suffix = uniqid();

    $module = Module::create([
        'name' => 'Variant Edge Module',
        'slug' => 'variant-edge-module-'.$suffix,
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
        'slug' => 'variant-edge-role-'.$suffix,
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
        'name' => 'Edge Category '.$suffix,
        'slug' => 'edge-category-'.$suffix,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
    ]);

    $brand = Brand::create([
        'name' => 'Edge Brand '.$suffix,
        'slug' => 'edge-brand-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    $unit = Unit::create([
        'name' => 'Edge Unit '.$suffix,
        'short_name' => 'EU',
        'slug' => 'edge-unit-'.$suffix,
        'status' => 'active',
        'sort_order' => 0,
    ]);

    return Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Edge Product '.$suffix,
        'slug' => 'edge-product-'.$suffix,
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
        'name' => 'Edge Variant '.uniqid(),
        'sku' => 'EDGE-SKU-'.strtoupper(uniqid()),
        'barcode' => 'EDGE-BAR-'.strtoupper(uniqid()),
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
        'name' => $name.' '.$suffix,
        'slug' => strtolower(str_replace(' ', '-', $name)).'-'.$suffix,
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
        'name' => 'Edge Value '.$suffix,
        'slug' => 'edge-value-'.$suffix,
        'value' => 'edge-'.$suffix,
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
            "/api/v1/backend/products/{$product->id}/variants",
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
            "/api/v1/backend/products/{$product->id}/variants",
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

test('variant creation accepts color and boolean attribute types exposed by the variant editor', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();
    $color = variantEdgeCreateAttribute('Selectable Color');
    $color->update(['type' => 'color']);
    $colorValue = AttributeValue::create([
        'attribute_id' => $color->id,
        'name' => 'Midnight Blue',
        'slug' => 'midnight-blue-'.uniqid(),
        'value' => '#1e3a8a',
        'color_code' => '#1e3a8a',
        'status' => 'active',
    ]);

    $boolean = variantEdgeCreateAttribute('Selectable Boolean');
    $boolean->update(['type' => 'boolean']);
    $booleanValue = AttributeValue::create([
        'attribute_id' => $boolean->id,
        'name' => 'True',
        'slug' => 'true-'.uniqid(),
        'value' => 'true',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [
                    [
                        'attribute_id' => $color->id,
                        'attribute_value_id' => $colorValue->id,
                    ],
                    [
                        'attribute_id' => $boolean->id,
                        'attribute_value_id' => $booleanValue->id,
                    ],
                ],
            ])
        )
        ->assertCreated();

    $this->assertDatabaseCount('product_variant_attribute_values', 2);
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
            "/api/v1/backend/products/{$product->id}/variants",
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
            "/api/v1/backend/products/{$product->id}/variants/{$variant->id}",
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
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => true])
        )
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
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

test('variant creation rejects an identical attribute combination for the same product', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();
    $color = variantEdgeCreateAttribute('Combination Color');
    $yellow = variantEdgeCreateValue($color);
    $size = variantEdgeCreateAttribute('Combination Size');
    $big = variantEdgeCreateValue($size);

    $selection = [
        ['attribute_id' => $color->id, 'attribute_value_id' => $yellow->id],
        ['attribute_id' => $size->id, 'attribute_value_id' => $big->id],
    ];

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['attribute_values' => $selection])
        )
        ->assertCreated();

    // Reverse request order to prove the signature is canonical.
    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['attribute_values' => array_reverse($selection)])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attribute_values']);

    $this->assertSame(
        1,
        ProductVariant::query()->where('product_id', $product->id)->count()
    );
});

test('the same attribute combination is allowed for a different product', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $productA = variantEdgeCreateProduct();
    $productB = variantEdgeCreateProduct();
    $color = variantEdgeCreateAttribute('Shared Color');
    $yellow = variantEdgeCreateValue($color);
    $selection = [[
        'attribute_id' => $color->id,
        'attribute_value_id' => $yellow->id,
    ]];

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$productA->id}/variants",
            variantEdgePayload(['attribute_values' => $selection])
        )
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$productB->id}/variants",
            variantEdgePayload(['attribute_values' => $selection])
        )
        ->assertCreated();
});

test('first active variant becomes default and deleting it promotes the next active variant', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');
    variantEdgeGrantPermission($user, 'system.products.delete');

    $product = variantEdgeCreateProduct();

    $first = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => false])
        )
        ->assertCreated()
        ->assertJsonPath('data.is_default', true)
        ->json('data');

    $second = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => false])
        )
        ->assertCreated()
        ->assertJsonPath('data.is_default', false)
        ->json('data');

    $this->actingAs($user)
        ->deleteJson("/api/v1/backend/products/{$product->id}/variants/{$first['id']}")
        ->assertOk();

    $this->assertSoftDeleted('product_variants', ['id' => $first['id']]);
    $this->assertDatabaseHas('product_variants', [
        'id' => $second['id'],
        'is_default' => true,
        'status' => 'active',
    ]);
});

test('inactivating the default variant promotes another active variant', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');
    variantEdgeGrantPermission($user, 'system.products.update');

    $product = variantEdgeCreateProduct();

    $first = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => true])
        )
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload(['is_default' => false])
        )
        ->assertCreated()
        ->json('data');

    $this->actingAs($user)
        ->putJson(
            "/api/v1/backend/products/{$product->id}/variants/{$first['id']}",
            ['status' => 'inactive']
        )
        ->assertOk()
        ->assertJsonPath('data.is_default', false);

    $this->assertDatabaseHas('product_variants', [
        'id' => $second['id'],
        'is_default' => true,
        'status' => 'active',
    ]);
});

test('an inactive variant cannot be selected as the default', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');

    $product = variantEdgeCreateProduct();

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload([
                'status' => 'inactive',
                'is_default' => true,
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_default']);

    $this->assertDatabaseCount('product_variants', 0);
});

test('variant update rejects changing to another variant attribute combination', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');
    variantEdgeGrantPermission($user, 'system.products.update');

    $product = variantEdgeCreateProduct();
    $color = variantEdgeCreateAttribute('Update Color');
    $yellow = variantEdgeCreateValue($color);
    $black = variantEdgeCreateValue($color);

    $first = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [[
                    'attribute_id' => $color->id,
                    'attribute_value_id' => $yellow->id,
                ]],
            ])
        )
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload([
                'attribute_values' => [[
                    'attribute_id' => $color->id,
                    'attribute_value_id' => $black->id,
                ]],
            ])
        )
        ->assertCreated()
        ->json('data');

    $this->actingAs($user)
        ->putJson(
            "/api/v1/backend/products/{$product->id}/variants/{$second['id']}",
            ['attribute_values' => [[
                'attribute_id' => $color->id,
                'attribute_value_id' => $yellow->id,
            ]]]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['attribute_values']);

    $this->assertDatabaseHas('product_variant_attribute_values', [
        'product_variant_id' => $second['id'],
        'attribute_id' => $color->id,
        'attribute_value_id' => $black->id,
    ]);
    $this->assertDatabaseHas('product_variant_attribute_values', [
        'product_variant_id' => $first['id'],
        'attribute_id' => $color->id,
        'attribute_value_id' => $yellow->id,
    ]);
});

test('variant creation rejects a promotional price that is not below selling price', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.create');
    $product = variantEdgeCreateProduct();

    $this->actingAs($user)
        ->postJson(
            "/api/v1/backend/products/{$product->id}/variants",
            variantEdgePayload([
                'selling_price' => 100,
                'promotional_price' => 100,
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promotional_price');

    $this->assertDatabaseCount('product_variants', 0);
});

test('variant partial update rejects a selling price below its stored promotional price', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.products.update');
    $product = variantEdgeCreateProduct();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Price invariant variant',
        'sku' => 'PRICE-'.strtoupper(uniqid()),
        'cost_price' => 20,
        'selling_price' => 100,
        'promotional_price' => 80,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->putJson(
            "/api/v1/backend/products/{$product->id}/variants/{$variant->id}",
            ['selling_price' => 70]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('promotional_price');

    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'selling_price' => '100.00',
        'promotional_price' => '80.00',
    ]);
});

test('effective selling price ignores a promotional price at or above the regular price', function () {
    $product = variantEdgeCreateProduct();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Defensive price variant',
        'sku' => 'DEFENSIVE-'.strtoupper(uniqid()),
        'cost_price' => 20,
        'selling_price' => 100,
        'promotional_price' => 110,
        'promotion_starts_at' => now()->subDay(),
        'promotion_ends_at' => now()->addDay(),
        'is_default' => true,
        'status' => 'active',
    ]);

    expect($variant->effectiveSellingPrice())->toBe('100.00');
});

test('storefront flash sale price must be below the current effective variant price', function () {
    $product = variantEdgeCreateProduct();
    $product->update(['status' => 'active', 'visibility' => 'visible']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Flash price variant',
        'sku' => 'FLASH-'.strtoupper(uniqid()),
        'cost_price' => 20,
        'selling_price' => 100,
        'is_default' => true,
        'status' => 'active',
    ]);
    $sale = FlashSale::create([
        'title' => 'Price integrity sale',
        'slug' => 'price-integrity-'.strtolower(uniqid()),
        'status' => 'active',
        'accent_color' => '#059669',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'show_on_storefront' => true,
    ]);
    $item = FlashSaleItem::create([
        'flash_sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'regular_price' => 100,
        'sale_price' => 90,
    ]);

    $engine = app(PricingEngine::class);
    expect($engine->flashSalePriceForVariant($variant->id))->toBe('90.00');

    $variant->update(['selling_price' => 80]);
    expect($engine->flashSalePriceForVariant($variant->id))->toBeNull();

    $item->update(['sale_price' => 70]);
    expect($engine->flashSalePriceForVariant($variant->id))->toBe('70.00');
});

test('flash sale creation rejects a sale price above the current effective variant price', function () {
    $user = User::factory()->create();
    variantEdgeGrantPermission($user, 'system.pricing.create');
    $product = variantEdgeCreateProduct();
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Flash sale validation variant',
        'sku' => 'FLASH-VALIDATION-'.strtoupper(uniqid()),
        'cost_price' => 20,
        'selling_price' => 100,
        'is_default' => true,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->postJson('/api/v1/backend/pricing/flash-sales', [
            'title' => 'Invalid sale price',
            'slug' => 'invalid-sale-'.strtolower(uniqid()),
            'status' => 'draft',
            'accent_color' => '#059669',
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
            'show_on_storefront' => true,
            'items' => [[
                'product_variant_id' => $variant->id,
                'regular_price' => 150,
                'sale_price' => 120,
                'quantity_limit' => null,
                'sort_order' => 0,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    $this->assertDatabaseCount('flash_sales', 0);
    $this->assertDatabaseCount('flash_sale_items', 0);
});
