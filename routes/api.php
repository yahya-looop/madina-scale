<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:system.users.view');

    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:system.users.view');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:system.users.create');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:system.users.update');


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:system.roles.view');

    Route::get('/roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:system.roles.view');

    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:system.roles.create');

    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:system.roles.update');

    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
        ->middleware('permission:system.roles.manage_permissions');


    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:system.permissions.view');

    Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
        ->middleware('permission:system.permissions.view');

    Route::post('/permissions', [PermissionController::class, 'store'])
        ->middleware('permission:system.permissions.create');

    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
        ->middleware('permission:system.permissions.update');


    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:system.activity_logs.view');

    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])
        ->middleware('permission:system.activity_logs.view');


    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('permission:system.settings.view');

    Route::get('/settings/system', [SettingsController::class, 'system'])
        ->middleware('permission:system.settings.view');

    Route::get('/settings/{group}', [SettingsController::class, 'group'])
        ->middleware('permission:system.settings.view');

    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:system.settings.update');



    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    Route::get('/modules', [ModuleController::class, 'index'])
        ->middleware('permission:system.modules.view');

    Route::get('/modules/{module}', [ModuleController::class, 'show'])
        ->middleware('permission:system.modules.view');

    Route::post('/modules', [ModuleController::class, 'store'])
        ->middleware('permission:system.modules.create');

    Route::put('/modules/{module}', [ModuleController::class, 'update'])
        ->middleware('permission:system.modules.update');
    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    */

    Route::get('/locations', [LocationController::class, 'index'])
        ->middleware('permission:system.locations.view');

    Route::get('/locations/{location}', [LocationController::class, 'show'])
        ->middleware('permission:system.locations.view');

    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('permission:system.locations.create');

    Route::put('/locations/{location}', [LocationController::class, 'update'])
        ->middleware('permission:system.locations.update');

    Route::patch('/locations/{location}/activate', [LocationController::class, 'activate'])
        ->middleware('permission:system.locations.manage');

    Route::patch('/locations/{location}/deactivate', [LocationController::class, 'deactivate'])
        ->middleware('permission:system.locations.manage');


    /*
    |--------------------------------------------------------------------------
    | Product Categories
    |--------------------------------------------------------------------------
    */
    Route::get('/product-categories', [ProductCategoryController::class, 'index'])
        ->middleware('permission:system.products.categories.view');

    Route::get('/product-categories/{productCategory}', [ProductCategoryController::class, 'show'])
        ->middleware('permission:system.products.categories.view');

    Route::post('/product-categories', [ProductCategoryController::class, 'store'])
        ->middleware('permission:system.products.categories.create');

    Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])
        ->middleware('permission:system.products.categories.update');

    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])
        ->middleware('permission:system.products.categories.delete');



     /*
    |--------------------------------------------------------------------------
    | Brand Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/brands', [BrandController::class, 'index'])
        ->middleware('permission:system.products.brands.view');

    Route::get('/brands/{brand}', [BrandController::class, 'show'])
        ->middleware('permission:system.products.brands.view');

    Route::post('/brands', [BrandController::class, 'store'])
        ->middleware('permission:system.products.brands.create');

    Route::put('/brands/{brand}', [BrandController::class, 'update'])
        ->middleware('permission:system.products.brands.update');

    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])
        ->middleware('permission:system.products.brands.delete');


    /*
    |--------------------------------------------------------------------------
    | Unit Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/units', [UnitController::class, 'index'])
    ->middleware('permission:system.products.units.view');

    Route::get('/units/{unit}', [UnitController::class, 'show'])
        ->middleware('permission:system.products.units.view');

    Route::post('/units', [UnitController::class, 'store'])
        ->middleware('permission:system.products.units.create');

    Route::put('/units/{unit}', [UnitController::class, 'update'])
        ->middleware('permission:system.products.units.update');

    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('permission:system.products.units.delete');

    /*
    |--------------------------------------------------------------------------
    | Attribute Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/attributes', [AttributeController::class, 'index'])
        ->middleware('permission:system.products.attributes.view');

    Route::get('/attributes/{attribute}', [AttributeController::class, 'show'])
        ->middleware('permission:system.products.attributes.view');

    Route::post('/attributes', [AttributeController::class, 'store'])
        ->middleware('permission:system.products.attributes.create');

    Route::put('/attributes/{attribute}', [AttributeController::class, 'update'])
        ->middleware('permission:system.products.attributes.update');

    Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])
        ->middleware('permission:system.products.attributes.delete');

    /*
    |--------------------------------------------------------------------------
    | Attribute Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/attribute-values', [AttributeValueController::class, 'index'])
        ->middleware('permission:system.products.attributes.values.view');

    Route::get('/attribute-values/{attributeValue}', [AttributeValueController::class, 'show'])
        ->middleware('permission:system.products.attributes.values.view');

    Route::post('/attribute-values', [AttributeValueController::class, 'store'])
        ->middleware('permission:system.products.attributes.values.create');

    Route::put('/attribute-values/{attributeValue}', [AttributeValueController::class, 'update'])
        ->middleware('permission:system.products.attributes.values.update');

    Route::delete('/attribute-values/{attributeValue}', [AttributeValueController::class, 'destroy'])
        ->middleware('permission:system.products.attributes.values.delete');


    /*
    |--------------------------------------------------------------------------
    | Product Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:system.products.view');

    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:system.products.view');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:system.products.create');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:system.products.update');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:system.products.delete');

    /*
    |--------------------------------------------------------------------------
    | Product Variant Routes
    |--------------------------------------------------------------------------
    |
    | These routes follow the existing authentication and permission pattern.
    |
    */

    Route::get('/products/{product}/variants', [ProductVariantController::class, 'index'])
        ->middleware('permission:system.products.view');

    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])
        ->middleware('permission:system.products.create');

    Route::get('/products/{product}/variants/{variant}', [ProductVariantController::class, 'show'])
        ->middleware('permission:system.products.view');

    Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
        ->middleware('permission:system.products.update');

    Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
        ->middleware('permission:system.products.delete');


    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    // Threshold of inventory stock
    Route::patch(
        '/inventory/stocks/{stock}/threshold',
        [InventoryController::class, 'updateThreshold']
    )->middleware('permission:system.inventory.update');


    Route::get('/inventory/stocks', [InventoryController::class, 'stocks'])
        ->middleware('permission:system.inventory.view');

    Route::get('/inventory/movements', [InventoryController::class, 'movements'])
        ->middleware('permission:system.inventory.view');

    Route::post('/inventory/receive', [InventoryController::class, 'receive'])
        ->middleware('permission:system.inventory.receive');

    Route::post('/inventory/issue', [InventoryController::class, 'issue'])
        ->middleware('permission:system.inventory.issue');

    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:system.inventory.adjust');

    Route::post('/inventory/transfer', [InventoryController::class, 'transfer'])
        ->middleware('permission:system.inventory.transfer');

    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])
        ->middleware('permission:system.inventory.view');


});
