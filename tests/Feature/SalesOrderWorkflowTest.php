<?php

namespace Tests\Feature;

use App\Models\B2BAccount;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\PricingPromotion;
use App\Models\PricingPromotionRedemption;
use App\Models\SalesOrderFlashSaleAllocation;
use App\Models\InventoryReservationPurpose;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\CommercialPricingService;
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

    public function test_coupon_redemption_is_counted_and_released_when_order_is_cancelled(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $promotion = PricingPromotion::query()->create([
            'name' => 'Order coupon', 'code' => 'SAVE10', 'discount_type' => 'percent',
            'discount_value' => 10, 'scope' => 'order', 'minimum_order_amount' => 0,
            'minimum_quantity' => 1, 'usage_limit' => 1, 'per_customer_limit' => null,
            'usage_count' => 0, 'stackable' => false, 'publicly_visible' => false,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Coupon Buyer',
            'promotion_code' => 'save10',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.discount_total', '20.00')
            ->assertJsonPath('data.grand_total', '180.00');

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('pricing_promotions', ['id' => $promotion->id, 'usage_count' => 1]);
        $this->assertDatabaseHas('pricing_promotion_redemptions', [
            'pricing_promotion_id' => $promotion->id, 'sales_order_id' => $orderId, 'status' => 'applied',
        ]);

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel", [
            'reason' => 'Coupon lifecycle test cancellation',
        ])->assertOk();

        $this->assertDatabaseHas('pricing_promotions', ['id' => $promotion->id, 'usage_count' => 0]);
        $this->assertDatabaseHas('pricing_promotion_redemptions', [
            'pricing_promotion_id' => $promotion->id, 'sales_order_id' => $orderId, 'status' => 'released',
        ]);
    }

    public function test_flash_sale_quota_is_reserved_and_released_on_order_cancellation(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $sale = FlashSale::query()->create([
            'title' => 'Order flash sale', 'slug' => 'order-flash-'.Str::lower(Str::random(8)),
            'status' => 'active', 'accent_color' => '#059669',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
            'show_on_storefront' => true, 'priority' => 1,
        ]);
        $saleItem = FlashSaleItem::query()->create([
            'flash_sale_id' => $sale->id, 'product_variant_id' => $variant->id,
            'regular_price' => 100, 'sale_price' => 70, 'quantity_limit' => 3, 'quantity_sold' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Flash Sale Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.grand_total', '140.00');

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('flash_sale_items', ['id' => $saleItem->id, 'quantity_sold' => 2]);
        $this->assertDatabaseHas('sales_order_flash_sale_allocations', [
            'flash_sale_item_id' => $saleItem->id, 'sales_order_id' => $orderId, 'status' => 'reserved',
        ]);

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel", [
            'reason' => 'Flash sale lifecycle test cancellation',
        ])->assertOk();

        $this->assertDatabaseHas('flash_sale_items', ['id' => $saleItem->id, 'quantity_sold' => 0]);
        $this->assertDatabaseHas('sales_order_flash_sale_allocations', [
            'flash_sale_item_id' => $saleItem->id, 'sales_order_id' => $orderId, 'status' => 'released',
        ]);
    }


    public function test_promotion_priority_selects_the_highest_priority_non_stackable_rule_and_snapshots_it(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);

        $lowerPriority = PricingPromotion::query()->create([
            'name' => 'Lower priority offer', 'discount_type' => 'percent', 'discount_value' => 20,
            'scope' => 'order', 'minimum_order_amount' => 0, 'minimum_quantity' => 1,
            'usage_count' => 0, 'priority' => 200, 'stackable' => false, 'publicly_visible' => true,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
        ]);
        $higherPriority = PricingPromotion::query()->create([
            'name' => 'Highest priority offer', 'discount_type' => 'percent', 'discount_value' => 10,
            'scope' => 'order', 'minimum_order_amount' => 0, 'minimum_quantity' => 1,
            'usage_count' => 0, 'priority' => 10, 'stackable' => false, 'publicly_visible' => true,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Priority Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.discount_total', '20.00')
            ->assertJsonPath('data.grand_total', '180.00');

        $order = SalesOrder::query()->findOrFail($response->json('data.id'));
        $this->assertSame($higherPriority->id, $order->promotion_id);
        $this->assertSame('Highest priority offer', $order->pricing_snapshot['applied_promotions'][0]['name']);
        $this->assertDatabaseHas('pricing_promotions', ['id' => $higherPriority->id, 'usage_count' => 1]);
        $this->assertDatabaseHas('pricing_promotions', ['id' => $lowerPriority->id, 'usage_count' => 0]);
    }

    public function test_product_scoped_promotion_is_applied_to_matching_order_lines(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $promotion = PricingPromotion::query()->create([
            'name' => 'Product line offer', 'discount_type' => 'percent', 'discount_value' => 10,
            'scope' => 'product', 'product_id' => $variant->product_id,
            'minimum_order_amount' => 0, 'minimum_quantity' => 1, 'usage_count' => 0,
            'priority' => 50, 'stackable' => false, 'publicly_visible' => true,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Product Promotion Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.discount_total', '20.00')
            ->assertJsonPath('data.grand_total', '180.00');

        $orderId = $response->json('data.id');
        $item = SalesOrder::query()->findOrFail($orderId)->items()->firstOrFail();
        $this->assertSame('20.00', $item->pricing_snapshot['line_discount']);
        $this->assertSame($promotion->id, $item->pricing_snapshot['line_promotions'][0]['promotion_id']);
    }

    public function test_per_customer_limited_coupon_requires_a_linked_customer(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $promotion = PricingPromotion::query()->create([
            'name' => 'Customer-bound coupon', 'code' => 'CUSTOMER10',
            'discount_type' => 'percent', 'discount_value' => 10, 'scope' => 'order',
            'minimum_order_amount' => 0, 'minimum_quantity' => 1,
            'usage_limit' => 10, 'per_customer_limit' => 1, 'usage_count' => 0,
            'priority' => 100, 'stackable' => false, 'publicly_visible' => false,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Unlinked Coupon Buyer',
            'promotion_code' => 'customer10',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('promotion_code');

        $this->assertDatabaseHas('pricing_promotions', ['id' => $promotion->id, 'usage_count' => 0]);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_active_b2b_account_uses_variant_wholesale_price_before_account_discount_fallback(): void
    {
        [, $variant] = $this->orderFixture();
        $variant->update(['selling_price' => 100, 'wholesale_price' => 82]);
        $account = B2BAccount::query()->create([
            'account_code' => 'B2B-'.Str::upper(Str::random(8)),
            'company_name' => 'Wholesale Price Test',
            'business_type' => 'retailer', 'tier' => 'gold', 'status' => 'active',
            'payment_terms' => 'prepaid', 'credit_limit' => 0, 'default_discount_percent' => 15,
        ]);

        $resolved = app(CommercialPricingService::class)->priceLine($variant->fresh(), '2.0000', $account);

        $this->assertSame('82.00', $resolved['unit_price']);
        $this->assertSame('wholesale', $resolved['price_source']);
    }

    public function test_pending_order_edit_releases_old_flash_sale_allocation_and_reserves_replacement(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $sale = FlashSale::query()->create([
            'title' => 'Editable flash sale', 'slug' => 'editable-flash-'.Str::lower(Str::random(8)),
            'status' => 'active', 'accent_color' => '#059669',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
            'show_on_storefront' => true, 'priority' => 1,
        ]);
        $saleItem = FlashSaleItem::query()->create([
            'flash_sale_id' => $sale->id, 'product_variant_id' => $variant->id,
            'regular_price' => 100, 'sale_price' => 70, 'quantity_limit' => 5, 'quantity_sold' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Editable Flash Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated();
        $orderId = $response->json('data.id');
        $oldAllocation = SalesOrderFlashSaleAllocation::query()->where('sales_order_id', $orderId)->firstOrFail();

        $this->actingAs($user)->putJson("/api/orders/{$orderId}", [
            'customer_name' => 'Editable Flash Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertOk();

        $this->assertDatabaseHas('sales_order_flash_sale_allocations', [
            'id' => $oldAllocation->id, 'status' => 'released', 'sales_order_item_id' => null,
        ]);
        $this->assertDatabaseHas('sales_order_flash_sale_allocations', [
            'sales_order_id' => $orderId, 'flash_sale_item_id' => $saleItem->id, 'status' => 'reserved', 'quantity' => 1,
        ]);
        $this->assertDatabaseHas('flash_sale_items', ['id' => $saleItem->id, 'quantity_sold' => 1]);
    }


    public function test_stackable_order_promotions_apply_in_priority_order_without_exceeding_subtotal(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        foreach ([
            ['name' => 'First stack', 'value' => 10, 'priority' => 10],
            ['name' => 'Second stack', 'value' => 20, 'priority' => 20],
        ] as $rule) {
            PricingPromotion::query()->create([
                'name' => $rule['name'], 'discount_type' => 'percent', 'discount_value' => $rule['value'],
                'scope' => 'order', 'minimum_order_amount' => 0, 'minimum_quantity' => 1,
                'usage_count' => 0, 'priority' => $rule['priority'], 'stackable' => true,
                'publicly_visible' => true, 'status' => 'active',
                'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
            ]);
        }

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id, 'customer_name' => 'Stacking Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.discount_total', '56.00')
            ->assertJsonPath('data.grand_total', '144.00');

        $order = SalesOrder::query()->findOrFail($response->json('data.id'));
        $this->assertCount(2, $order->pricing_snapshot['applied_promotions']);
        $this->assertSame('First stack', $order->pricing_snapshot['applied_promotions'][0]['name']);
        $this->assertSame('Second stack', $order->pricing_snapshot['applied_promotions'][1]['name']);
    }


    public function test_b2b_order_is_linked_to_account_and_credit_exposure_is_enforced_with_due_date_snapshot(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $customer = Customer::query()->create([
            'customer_code' => 'CUS-'.Str::upper(Str::random(8)),
            'name' => 'Credit Test Customer',
            'status' => 'active',
        ]);
        $account = B2BAccount::query()->create([
            'account_code' => 'B2B-'.Str::upper(Str::random(8)),
            'company_name' => 'Credit Test Wholesale',
            'business_type' => 'retailer',
            'tier' => 'gold',
            'status' => 'active',
            'customer_id' => $customer->id,
            'payment_terms' => 'net_30',
            'credit_limit' => 150,
            'default_discount_percent' => 0,
        ]);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = SalesOrder::query()->firstOrFail();
        $this->assertSame($account->id, (int) $order->b2b_account_id);
        $this->assertSame('net_30', $order->credit_snapshot['payment_terms']);
        $this->assertSame('100.00', $order->credit_snapshot['order_amount']);
        $this->assertNotNull($order->payment_due_at);
        // Delivery does not automatically settle an invoice, so fulfilled credit orders remain exposed.
        $order->update(['status' => 'fulfilled']);

        $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('credit_limit');

        $this->assertDatabaseCount('sales_orders', 1);
    }

    public function test_order_confirmation_rejects_financial_snapshot_mismatch(): void
    {
        [$location, $variant] = $this->orderFixture();
        $user = $this->orderUser($location);
        $response = $this->actingAs($user)->postJson('/api/orders', [
            'location_id' => $location->id,
            'customer_name' => 'Snapshot Integrity Buyer',
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertCreated();

        $order = SalesOrder::query()->findOrFail($response->json('data.id'));
        $order->update(['grand_total' => '1.00']);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pricing');

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'pending']);
    }

}
