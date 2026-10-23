<?php

namespace Tests\Feature;

use App\Models\InventoryReservationPurpose;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function orderUser(Location $location): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'orders'], [
            'name' => 'Orders', 'description' => 'Order management.',
            'sort_order' => 30, 'is_system' => true, 'is_active' => true,
        ]);
        $slugs = ['system.orders.view', 'system.orders.create', 'system.orders.confirm', 'system.orders.cancel', 'system.orders.fulfill'];
        $permissions = [];
        foreach ($slugs as $slug) {
            $permissions[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'Orders test permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'Orders Test Role', 'slug' => 'orders-test-' . Str::lower(Str::random(12)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role->id);
        $user->locations()->attach($location->id, ['access_level' => 'manage']);
        return $user;
    }

    private function orderFixture(): array
    {
        $location = Location::query()->create([
            'name' => 'Orders Test Store', 'code' => 'ORD-' . Str::upper(Str::random(5)),
            'type' => 'store', 'is_active' => true,
        ]);
        // These fixture helpers are already used by the inventory feature test suite.
        $product = \inventoryTestProduct('variable');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'name' => 'Order Test Variant',
            'sku' => 'ORD-' . Str::upper(Str::random(8)), 'selling_price' => 100,
            'cost_price' => 60, 'status' => 'active', 'is_default' => true,
        ]);
        InventoryStock::query()->create([
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'quantity' => 10, 'reserved_quantity' => 0,
        ]);
        InventoryReservationPurpose::query()->firstOrCreate(
            ['key' => 'customer_order'],
            ['label' => 'Customer order', 'is_active' => true, 'sort_order' => 10]
        );
        return [$location, $variant];
    }

    public function test_order_creation_reserves_stock_and_cancellation_releases_it(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Test Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.grand_total', '200.00');

        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id, 'reserved_quantity' => '2.0000',
        ]);
        $order = SalesOrder::query()->firstOrFail();

        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'reason' => 'Customer changed their mind',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'reserved_quantity' => '0.0000',
        ]);
    }

    public function test_order_creation_rejects_quantity_above_available_stock_without_partial_writes(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Test Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 50]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'reserved_quantity' => '0.0000',
        ]);
    }


    public function test_confirmed_order_supports_partial_then_complete_fulfillment_without_double_deduction(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Partial Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated();
        $order = SalesOrder::query()->firstOrFail();
        $this->postJson("/api/orders/{$order->id}/confirm", [])->assertOk();
        $item = $order->items()->firstOrFail();

        $this->postJson("/api/orders/{$order->id}/fulfill", [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['sales_order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('data.status', 'partially_fulfilled');
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'quantity' => '9.0000', 'reserved_quantity' => '1.0000',
        ]);

        $this->postJson("/api/orders/{$order->id}/fulfill", [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['sales_order_item_id' => $item->id, 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('data.status', 'fulfilled');
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'quantity' => '8.0000', 'reserved_quantity' => '0.0000',
        ]);
        $this->assertDatabaseCount('sales_order_fulfillments', 2);
    }


    public function test_order_list_and_detail_are_scoped_to_assigned_locations(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Scoped Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();
        $order = SalesOrder::query()->firstOrFail();

        $otherLocation = Location::query()->create([
            'name' => 'Unassigned Store', 'code' => 'UN-' . Str::upper(Str::random(5)),
            'type' => 'store', 'is_active' => true,
        ]);
        $otherOrder = SalesOrder::query()->create([
            'order_number' => 'MS-UN-' . Str::upper(Str::random(8)), 'status' => 'pending',
            'channel' => 'backoffice', 'location_id' => $otherLocation->id,
            'customer_name' => 'Private Buyer', 'currency' => 'BDT', 'subtotal' => 0,
            'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 0, 'created_by' => $user->id,
        ]);

        $this->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/orders/{$order->id}")->assertOk();
        $this->getJson("/api/orders/{$otherOrder->id}")->assertForbidden();
    }


    public function test_order_idempotency_key_cannot_be_replayed_by_another_user(): void
    {
        [$location, $variant] = $this->orderFixture();
        $owner = $this->orderUser($location);
        $key = (string) Str::uuid();

        $this->actingAs($owner)->postJson('/api/orders', [
            'idempotency_key' => $key,
            'location_id' => $location->id,
            'customer_name' => 'Original Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();

        $otherUser = $this->orderUser($location);
        $this->actingAs($otherUser)->postJson('/api/orders', [
            'idempotency_key' => $key,
            'location_id' => $location->id,
            'customer_name' => 'Different Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('sales_orders', 1);
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id, 'location_id' => $location->id,
            'reserved_quantity' => '1.0000',
        ]);
    }

    public function test_user_without_order_permission_cannot_list_orders(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/orders')->assertForbidden();
    }
}
