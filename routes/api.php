<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLocationSuggestionController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReservationController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\LocationAssignmentController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrderOperationsController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */
    Route::get('/customers/options', [CustomerController::class, 'options'])->middleware('permission:system.orders.create');
    Route::get('/customer-location-suggestions', [CustomerLocationSuggestionController::class, 'index'])->middleware('permission:system.customers.view');
    Route::post('/customer-location-suggestions', [CustomerLocationSuggestionController::class, 'store'])->middleware('permission:system.customers.create');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:system.customers.view');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:system.customers.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:system.customers.create');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:system.customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:system.customers.delete');

    /*
    |--------------------------------------------------------------------------
    | Orders — API-first foundation for web and future mobile clients
    |--------------------------------------------------------------------------
    */
    Route::get('/orders/options', [SalesOrderController::class, 'options'])
        ->middleware('permission:system.orders.create');
    Route::get('/orders/{order}/edit-options', [SalesOrderController::class, 'editOptions'])
        ->middleware('permission:system.orders.update');
    Route::get('/orders', [SalesOrderController::class, 'index'])
        ->middleware('permission:system.orders.view');
    Route::post('/orders', [SalesOrderController::class, 'store'])
        ->middleware('permission:system.orders.create');
    Route::put('/orders/{order}', [SalesOrderController::class, 'update'])
        ->middleware('permission:system.orders.update');
    Route::get('/orders/{order}', [SalesOrderController::class, 'show'])
        ->middleware('permission:system.orders.view');
    Route::post('/orders/{order}/confirm', [SalesOrderController::class, 'confirm'])
        ->middleware('permission:system.orders.confirm');
    Route::post('/orders/{order}/process', [SalesOrderController::class, 'process'])
        ->middleware('permission:system.orders.fulfill');
    Route::post('/orders/{order}/cancel', [SalesOrderController::class, 'cancel'])
        ->middleware('permission:system.orders.cancel');
    Route::post('/orders/{order}/fulfill', [SalesOrderController::class, 'fulfill'])
        ->middleware('permission:system.orders.fulfill');

    Route::post('/orders/{order}/risk-assessment', [OrderOperationsController::class, 'assessRisk'])
        ->middleware('permission:system.orders.risk.manage');
    Route::post('/orders/risk-assessments/{assessment}/review', [OrderOperationsController::class, 'reviewRisk'])
        ->middleware('permission:system.orders.risk.manage');
    Route::post('/orders/{order}/phone-verification/request', [OrderOperationsController::class, 'requestPhoneVerification'])
        ->middleware('permission:system.orders.communication.manage');
    Route::post('/orders/{order}/phone-verification/verify', [OrderOperationsController::class, 'verifyPhone'])
        ->middleware('permission:system.orders.communication.manage');
    Route::post('/orders/{order}/sms', [OrderOperationsController::class, 'sendSms'])
        ->middleware('permission:system.orders.communication.manage');
    Route::get('/orders/{order}/shipments', [OrderOperationsController::class, 'shipments'])
        ->middleware('permission:system.orders.view');
    Route::post('/orders/{order}/shipments', [OrderOperationsController::class, 'bookShipment'])
        ->middleware('permission:system.orders.shipment.manage');
    Route::post('/orders/{order}/shipments/{shipment}/retry', [OrderOperationsController::class, 'retryShipment'])
        ->middleware('permission:system.orders.shipment.manage');

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:system.users.view');

    Route::get('/users/options/locations', [UserController::class, 'locationOptions'])
        ->middleware('permission:system.locations.manage');

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
    Route::get('/locations/{location}/users', [LocationAssignmentController::class, 'index'])
        ->middleware('permission:system.locations.manage');
    Route::put('/locations/{location}/users', [LocationAssignmentController::class, 'sync'])
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

    Route::get('/inventory/locations', [InventoryController::class, 'locations'])
        ->middleware('permission:system.inventory.view');

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

    // Dispatches move stock into transit; destination acceptance creates the incoming ledger entry.
    Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])
        ->middleware('permission:system.inventory.view');
    Route::get('/inventory/transfers/counts', [InventoryTransferController::class, 'counts'])
        ->middleware('permission:system.inventory.view');
    Route::post('/inventory/transfers', [InventoryTransferController::class, 'store'])
        ->middleware('permission:system.inventory.transfer');
    Route::post('/inventory/transfers/{transfer}/accept', [InventoryTransferController::class, 'accept'])
        ->middleware('permission:system.inventory.transfer');
    Route::post('/inventory/transfers/{transfer}/reject', [InventoryTransferController::class, 'reject'])
        ->middleware('permission:system.inventory.transfer');

    Route::post('/inventory/restock', [InventoryController::class, 'restock'])
        ->middleware('permission:system.inventory.transfer');

    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])
        ->middleware('permission:system.inventory.view');

    /*
    |--------------------------------------------------------------------------
    | Inventory Reservations
    |--------------------------------------------------------------------------
    */
    Route::get('/inventory/reservation-purposes', [InventoryReservationController::class, 'purposes'])
        ->middleware('permission:system.inventory.view');
    Route::post('/inventory/reservation-purposes', [InventoryReservationController::class, 'storePurpose'])
        ->middleware('permission:system.inventory.reservations.manage');
    Route::get('/inventory/reservation-purposes/manage', [InventoryReservationController::class, 'managePurposes'])
        ->middleware('permission:system.inventory.reservations.manage');
    Route::put('/inventory/reservation-purposes/{purpose}', [InventoryReservationController::class, 'updatePurpose'])
        ->middleware('permission:system.inventory.reservations.manage');
    Route::delete('/inventory/reservation-purposes/{purpose}', [InventoryReservationController::class, 'destroyPurpose'])
        ->middleware('permission:system.inventory.reservations.manage');

    Route::get('/inventory/reservations', [InventoryReservationController::class, 'index'])
        ->middleware('permission:system.inventory.view');

    Route::post('/inventory/reservations', [InventoryReservationController::class, 'store'])
        ->middleware('permission:system.inventory.reservations.manage');

    Route::post('/inventory/reservations/{reservation}/release', [InventoryReservationController::class, 'release'])
        ->middleware('permission:system.inventory.reservations.manage');

    Route::post('/inventory/reservations/{reservation}/cancel', [InventoryReservationController::class, 'cancel'])
        ->middleware('permission:system.inventory.reservations.manage');

    Route::post('/inventory/reservations/{reservation}/fulfill', [InventoryReservationController::class, 'fulfill'])
        ->middleware('permission:system.inventory.reservations.manage');

});

// Provider callbacks authenticate with a dedicated bearer secret and must not require a staff session.
Route::post('/webhooks/couriers/{provider}', [OrderOperationsController::class, 'courierWebhook']);
