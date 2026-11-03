<?php

use App\Models\Brand;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

if (! function_exists('inventoryTestProduct')) {
    function inventoryTestProduct(string $type = 'simple'): Product
    {
        $suffix = strtolower(uniqid());

        $category = ProductCategory::query()->create([
            'name' => 'Inventory Category '.$suffix,
            'slug' => 'inventory-category-'.$suffix,
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 0,
        ]);

        $brand = Brand::query()->create([
            'name' => 'Inventory Brand '.$suffix,
            'slug' => 'inventory-brand-'.$suffix,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $unit = Unit::query()->create([
            'name' => 'Inventory Unit '.$suffix,
            'short_name' => 'IU',
            'slug' => 'inventory-unit-'.$suffix,
            'status' => 'active',
            'sort_order' => 0,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'name' => 'Inventory Product '.$suffix,
            'slug' => 'inventory-product-'.$suffix,
            'sku' => $type === 'simple'
                ? 'INV-P-'.strtoupper($suffix)
                : null,
            'product_type' => $type,
            'status' => 'active',
            'visibility' => 'visible',
            'sort_order' => 0,
        ]);
    }
}

if (! function_exists('inventoryTestLocation')) {
    function inventoryTestLocation(string $suffix = ''): Location
    {
        $suffix = $suffix ?: strtolower(uniqid());

        return Location::query()->create([
            'name' => 'Inventory Location '.$suffix,
            'code' => 'INV-'.strtoupper($suffix),
            'type' => 'warehouse',
            'is_active' => true,
            'is_primary' => false,
            'country_code' => 'BD',
        ]);
    }
}

if (! function_exists('inventoryTestVariant')) {
    function inventoryTestVariant(Product $product): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Inventory Test Variant',
            'sku' => 'INV-'.strtoupper(uniqid()),
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
}

if (! function_exists('createTransferWorkflowUser')) {
    /**
     * Create a user with the requested inventory permissions for transfer tests.
     *
     * @param  list<string>  $permissionSlugs
     */
    function createTransferWorkflowUser(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'inventory'], [
            'name' => 'Inventory', 'description' => 'Inventory management.',
            'sort_order' => 236, 'is_system' => true, 'is_active' => true,
        ]);
        $role = Role::create([
            'name' => 'Transfer Workflow Test Role', 'slug' => 'transfer-workflow-'.uniqid(),
            'description' => 'Temporary transfer workflow test role.', 'is_system' => false, 'is_active' => true,
        ]);
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => $slug, 'description' => 'Transfer workflow test permission.',
            ]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $user;
    }
}
