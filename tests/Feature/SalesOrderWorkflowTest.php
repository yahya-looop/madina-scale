<?php

namespace Tests\Feature;

use App\Models\InventoryReservationPurpose;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
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
        $slugs = ['system.orders.view', 'system.orders.create', 'system.orders.update', 'system.orders.confirm', 'system.orders.cancel', 'system.orders.fulfill'];
        $permissions = [];
        foreach ($slugs as $slug) {
            $permissions[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'Orders test permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'Orders Test Role', 'slug' => 'orders-test-'.Str::lower(Str::random(12)),
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
            'name' => 'Orders Test Store', 'code' => 'ORD-'.Str::upper(Str::random(5)),
            'type' => 'store', 'is_active' => true,
        ]);
        // These fixture helpers are already used by the inventory feature test suite.
        $product = \inventoryTestProduct('variable');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'name' => 'Order Test Variant',
            'sku' => 'ORD-'.Str::upper(Str::random(8)), 'selling_price' => 100,
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

    public function test_order_options_return_all_inventory_variants_at_selected_location_including_zero_available(): void
    {
        [$location, $stockedVariant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $outOfStockVariant = ProductVariant::query()->create([
            'product_id' => $stockedVariant->product_id,
            'name' => 'Out of stock variant',
            'sku' => 'ORD-EMPTY-'.Str::upper(Str::random(5)),
            'selling_price' => 100,
            'cost_price' => 60,
            'status' => 'active',
            'is_default' => false,
        ]);
        InventoryStock::query()->create([
            'product_variant_id' => $outOfStockVariant->id,
            'location_id' => $location->id,
            'quantity' => 3,
            'reserved_quantity' => 3,
        ]);

        $otherLocation = Location::query()->create([
            'name' => 'Other Orders Store',
            'code' => 'ORD-'.Str::upper(Str::random(5)),
            'type' => 'store',
            'is_active' => true,
        ]);
        $otherLocationOnlyVariant = ProductVariant::query()->create([
            'product_id' => $stockedVariant->product_id,
            'name' => 'Other location only',
            'sku' => 'ORD-OTHER-'.Str::upper(Str::random(5)),
            'selling_price' => 100,
            'cost_price' => 60,
            'status' => 'active',
            'is_default' => false,
        ]);
        InventoryStock::query()->create([
            'product_variant_id' => $otherLocationOnlyVariant->id,
            'location_id' => $otherLocation->id,
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/orders/options?location_id='.$location->id);

        $response->assertOk()
            ->assertJsonCount(2, 'variants')
            ->assertJsonPath('variants.0.id', $stockedVariant->id)
            ->assertJsonPath('variants.0.available', '10.0000')
            ->assertJsonPath('variants.1.id', $outOfStockVariant->id)
            ->assertJsonPath('variants.1.available', '0.0000');
    }

    public function test_order_options_include_simple_product_inventory_and_image_field(): void
    {
        $location = Location::query()->create([
            'name' => 'Simple Product Store', 'code' => 'SMP-'.Str::upper(Str::random(5)),
            'type' => 'store', 'is_active' => true,
        ]);
        $product = \inventoryTestProduct('simple');
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'name' => 'Standard',
            // Simple-product identifiers belong to the product record, not its price variant.
            'selling_price' => 150,
            'cost_price' => 80, 'status' => 'active', 'is_default' => true,
        ]);
        InventoryStock::query()->create([
            'product_id' => $product->id, 'location_id' => $location->id,
            'quantity' => 4, 'reserved_quantity' => 1,
        ]);
        InventoryReservationPurpose::query()->firstOrCreate(
            ['key' => 'customer_order'],
            ['label' => 'Customer order', 'is_active' => true, 'sort_order' => 10]
        );
        $user = $this->orderUser($location);

        $this->actingAs($user)->getJson('/api/orders/options?location_id='.$location->id)
            ->assertOk()
            ->assertJsonCount(1, 'variants')
            ->assertJsonPath('variants.0.id', $variant->id)
            ->assertJsonPath('variants.0.available', '3.0000')
            ->assertJsonStructure(['variants' => [['image_url', 'image_alt', 'inventory_owner']]])
            ->assertJsonPath('variants.0.inventory_owner', 'product');

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Simple Product Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_stocks', [
            'product_id' => $product->id, 'location_id' => $location->id,
            'reserved_quantity' => '3.0000',
        ]);
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

    public function test_pending_order_can_be_edited_and_reservations_are_reconciled_atomically(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Original Buyer',
            'customer_phone' => '01711111111',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated();

        $order = SalesOrder::query()->firstOrFail();
        $item = $order->items()->firstOrFail();
        $reservationId = $item->reservation_id;

        $this->actingAs($user)->putJson("/api/orders/{$order->id}", [
            'customer_name' => 'Updated Buyer',
            'customer_phone' => '01822222222',
            'customer_email' => 'buyer@example.com',
            'notes' => 'Call before delivery',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 4]],
        ])->assertOk()
            ->assertJsonPath('data.customer_name', 'Updated Buyer')
            ->assertJsonPath('data.grand_total', '400.00')
            ->assertJsonPath('data.items.0.quantity', '4.0000');

        $this->assertDatabaseHas('inventory_reservations', [
            'id' => $reservationId,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'reserved_quantity' => '4.0000',
        ]);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_phone' => '01822222222',
            'notes' => 'Call before delivery',
        ]);
        $this->assertDatabaseHas('sales_order_status_histories', [
            'sales_order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'pending',
        ]);
    }

    public function test_failed_pending_order_edit_rolls_back_old_reservation_and_order_details(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Original Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated();

        $order = SalesOrder::query()->firstOrFail();

        $this->actingAs($user)->putJson("/api/orders/{$order->id}", [
            'customer_name' => 'Should Not Persist',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 50]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_name' => 'Original Buyer',
            'grand_total' => '200.00',
        ]);
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'reserved_quantity' => '2.0000',
        ]);
        $this->assertDatabaseCount('sales_order_items', 1);
    }

    public function test_confirmed_orders_cannot_be_edited(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Confirmed Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = SalesOrder::query()->firstOrFail();
        $this->actingAs($user)->postJson("/api/orders/{$order->id}/confirm", [])->assertOk();

        $this->putJson("/api/orders/{$order->id}", [
            'customer_name' => 'Changed After Confirmation',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_name' => 'Confirmed Buyer',
            'status' => 'confirmed',
        ]);
    }

    public function test_order_discount_is_validated_and_applied_to_grand_total(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Discount Buyer',
            'discount_total' => 25,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.discount_total', '25.00')
            ->assertJsonPath('data.grand_total', '175.00');

        $this->assertDatabaseHas('sales_orders', [
            'customer_name' => 'Discount Buyer',
            'subtotal' => '200.00',
            'discount_total' => '25.00',
            'grand_total' => '175.00',
        ]);
    }

    public function test_discount_cannot_exceed_order_subtotal(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Invalid Discount Buyer',
            'discount_total' => 201,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'reserved_quantity' => '0.0000',
        ]);
    }

    public function test_confirmed_order_can_move_to_processing_before_fulfillment(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Processing Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = SalesOrder::query()->firstOrFail();
        $this->actingAs($user)->postJson("/api/orders/{$order->id}/confirm", [])->assertOk();
        $this->postJson("/api/orders/{$order->id}/process", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('sales_order_status_histories', [
            'sales_order_id' => $order->id,
            'from_status' => 'confirmed',
            'to_status' => 'processing',
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
            'name' => 'Unassigned Store', 'code' => 'UN-'.Str::upper(Str::random(5)),
            'type' => 'store', 'is_active' => true,
        ]);
        $otherOrder = SalesOrder::query()->create([
            'order_number' => 'MS-UN-'.Str::upper(Str::random(8)), 'status' => 'pending',
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
