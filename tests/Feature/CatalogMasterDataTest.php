<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function catalogMasterDataGrantPermission(User $user, string $permissionSlug): void
{
    $suffix = strtolower((string) Str::uuid());

    $module = Module::create([
        'name' => 'Catalog Test Module',
        'slug' => 'catalog-test-module-'.$suffix,
        'description' => 'Module for catalog master data tests.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);

    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'Catalog Test Permission',
        'slug' => $permissionSlug,
        'description' => 'Permission for catalog master data tests.',
    ]);

    $role = Role::create([
        'name' => 'Catalog Test Role',
        'slug' => 'catalog-test-role-'.$suffix,
        'description' => 'Role for catalog master data tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);
}

function catalogMasterDataCreateProduct(): Product
{
    $suffix = strtolower((string) Str::uuid());
    $category = ProductCategory::create([
        'name' => 'Catalog Product Category '.$suffix,
        'slug' => 'catalog-product-category-'.$suffix,
        'status' => 'active',
        'visibility' => 'visible',
    ]);
    $brand = Brand::create([
        'name' => 'Catalog Product Brand '.$suffix,
        'slug' => 'catalog-product-brand-'.$suffix,
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'name' => 'Catalog Product Unit '.$suffix,
        'short_name' => 'CU',
        'slug' => 'catalog-product-unit-'.$suffix,
        'status' => 'active',
    ]);

    return Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'name' => 'Catalog Product '.$suffix,
        'slug' => 'catalog-product-'.$suffix,
        'sku' => 'CATALOG-SKU-'.$suffix,
        'product_type' => 'simple',
        'status' => 'active',
        'visibility' => 'visible',
    ]);
}

test('catalog master data endpoints require their dedicated permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/backend/product-categories')->assertForbidden();
    $this->getJson('/api/v1/backend/brands')->assertForbidden();
    $this->getJson('/api/v1/backend/units')->assertForbidden();
    $this->getJson('/api/v1/backend/attributes')->assertForbidden();
    $this->getJson('/api/v1/backend/attribute-values')->assertForbidden();
});

test('brands units attributes and attribute values can be created and updated', function () {
    $user = User::factory()->create();
    foreach ([
        'system.products.brands.create',
        'system.products.brands.update',
        'system.products.units.create',
        'system.products.units.update',
        'system.products.attributes.create',
        'system.products.attributes.update',
        'system.products.attributes.values.create',
        'system.products.attributes.values.update',
    ] as $permission) {
        catalogMasterDataGrantPermission($user, $permission);
    }

    $brand = $this->actingAs($user)->postJson('/api/v1/backend/brands', [
        'name' => 'Catalog Brand '.uniqid(),
        'description' => 'Initial brand description',
        'status' => 'active',
    ])->assertCreated()->json('data');
    $this->putJson('/api/v1/backend/brands/'.$brand['id'], [
        'name' => 'Updated Catalog Brand',
        'description' => 'Updated brand description',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Catalog Brand');

    $unit = $this->postJson('/api/v1/backend/units', [
        'name' => 'Catalog Piece '.uniqid(),
        'short_name' => 'PC',
        'status' => 'active',
    ])->assertCreated()->json('data');
    $this->putJson('/api/v1/backend/units/'.$unit['id'], [
        'name' => 'Updated Catalog Piece',
        'short_name' => 'PCS',
    ])->assertOk()->assertJsonPath('data.short_name', 'PCS');

    $attribute = $this->postJson('/api/v1/backend/attributes', [
        'name' => 'Catalog Material '.uniqid(),
        'type' => 'color',
        'status' => 'active',
    ])->assertCreated()->json('data');
    $this->putJson('/api/v1/backend/attributes/'.$attribute['id'], [
        'name' => 'Updated Catalog Material',
        'type' => 'color',
    ])->assertOk()->assertJsonPath('data.name', 'Updated Catalog Material');

    $value = $this->postJson('/api/v1/backend/attribute-values', [
        'attribute_id' => $attribute['id'],
        'name' => 'Ocean Blue',
        'value' => '#2563eb',
        'color_code' => '#2563eb',
        'status' => 'active',
    ])->assertCreated()->json('data');
    $this->putJson('/api/v1/backend/attribute-values/'.$value['id'], [
        'attribute_id' => $attribute['id'],
        'name' => 'Deep Ocean Blue',
        'value' => '#1d4ed8',
        'color_code' => '#1d4ed8',
    ])->assertOk()->assertJsonPath('data.name', 'Deep Ocean Blue');
});

test('category hierarchy rejects self-parenting and moving a parent below its descendant', function () {
    $user = User::factory()->create();
    catalogMasterDataGrantPermission($user, 'system.products.categories.create');
    catalogMasterDataGrantPermission($user, 'system.products.categories.update');

    $rootResponse = $this->actingAs($user)->postJson('/api/v1/backend/product-categories', [
        'name' => 'Catalog Root '.uniqid(),
        'status' => 'active',
        'visibility' => 'visible',
    ])->assertCreated();
    $rootId = (int) $rootResponse->json('data.id');

    $childResponse = $this->postJson('/api/v1/backend/product-categories', [
        'parent_id' => $rootId,
        'name' => 'Catalog Child '.uniqid(),
        'status' => 'active',
        'visibility' => 'visible',
    ])->assertCreated();
    $childId = (int) $childResponse->json('data.id');

    $grandchildResponse = $this->postJson('/api/v1/backend/product-categories', [
        'parent_id' => $childId,
        'name' => 'Catalog Grandchild '.uniqid(),
        'status' => 'active',
        'visibility' => 'visible',
    ])->assertCreated();
    $grandchildId = (int) $grandchildResponse->json('data.id');

    $this->putJson("/api/v1/backend/product-categories/{$rootId}", [
        'parent_id' => $rootId,
        'name' => 'Catalog Root Renamed',
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);

    $this->putJson("/api/v1/backend/product-categories/{$rootId}", [
        'parent_id' => $grandchildId,
        'name' => 'Catalog Root Renamed',
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
});

test('categories brands and units cannot be deleted while products reference them', function () {
    $user = User::factory()->create();
    catalogMasterDataGrantPermission($user, 'system.products.categories.delete');
    catalogMasterDataGrantPermission($user, 'system.products.brands.delete');
    catalogMasterDataGrantPermission($user, 'system.products.units.delete');

    $product = catalogMasterDataCreateProduct();

    $this->actingAs($user)
        ->deleteJson('/api/v1/backend/product-categories/'.$product->category_id)
        ->assertUnprocessable();

    $this->deleteJson('/api/v1/backend/brands/'.$product->brand_id)
        ->assertUnprocessable();

    $this->deleteJson('/api/v1/backend/units/'.$product->unit_id)
        ->assertUnprocessable();

    $this->assertModelExists($product->category()->firstOrFail());
    $this->assertModelExists($product->brand()->firstOrFail());
    $this->assertModelExists($product->unit()->firstOrFail());

    $product->delete();

    $this->deleteJson('/api/v1/backend/product-categories/'.$product->category_id)->assertUnprocessable();
    $this->deleteJson('/api/v1/backend/brands/'.$product->brand_id)->assertUnprocessable();
    $this->deleteJson('/api/v1/backend/units/'.$product->unit_id)->assertUnprocessable();
});

test('attributes and attribute values cannot be deleted while assigned to variants', function () {
    $user = User::factory()->create();
    catalogMasterDataGrantPermission($user, 'system.products.attributes.update');
    catalogMasterDataGrantPermission($user, 'system.products.attributes.delete');
    catalogMasterDataGrantPermission($user, 'system.products.attributes.values.update');
    catalogMasterDataGrantPermission($user, 'system.products.attributes.values.delete');

    $product = catalogMasterDataCreateProduct();
    $product->update(['product_type' => 'variable', 'sku' => null]);
    $attribute = Attribute::create([
        'name' => 'Catalog Color '.uniqid(),
        'slug' => 'catalog-color-'.uniqid(),
        'type' => 'select',
        'status' => 'active',
    ]);
    $value = AttributeValue::create([
        'attribute_id' => $attribute->id,
        'name' => 'Blue',
        'slug' => 'blue-'.uniqid(),
        'value' => 'blue',
        'status' => 'active',
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Blue Variant',
        'sku' => 'CATALOG-VARIANT-'.strtoupper(uniqid()),
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => true,
        'status' => 'active',
    ]);
    ProductVariantAttributeValue::create([
        'product_variant_id' => $variant->id,
        'attribute_id' => $attribute->id,
        'attribute_value_id' => $value->id,
    ]);

    $otherAttribute = Attribute::create([
        'name' => 'Catalog Alternate Attribute '.uniqid(),
        'slug' => 'catalog-alternate-attribute-'.uniqid(),
        'type' => 'select',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->putJson('/api/v1/backend/attributes/'.$attribute->id, [
            'name' => $attribute->name,
            'type' => 'text',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);

    $this->putJson('/api/v1/backend/attribute-values/'.$value->id, [
        'attribute_id' => $otherAttribute->id,
        'name' => $value->name,
        'value' => $value->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['attribute_id']);

    $this->deleteJson('/api/v1/backend/attribute-values/'.$value->id)
        ->assertUnprocessable();

    $this->deleteJson('/api/v1/backend/attributes/'.$attribute->id)
        ->assertUnprocessable();

    $this->assertModelExists($attribute);
    $this->assertModelExists($value);
});
