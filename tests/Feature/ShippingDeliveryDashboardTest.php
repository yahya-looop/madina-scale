<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderShipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingDeliveryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $accessLevel = 'manage'): array
    {
        $location = \inventoryTestLocation();
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'shipping'], [
            'name' => 'Shipping & Delivery', 'description' => 'Shipping tests.', 'sort_order' => 80,
            'is_system' => true, 'is_active' => true,
        ]);
        $permissions = collect(['system.shipping.view', 'system.shipping.delivery.manage'])->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], [
            'module_id' => $module->id, 'name' => $slug, 'description' => 'Shipping test permission.',
        ]));
        $role = Role::create(['name' => 'Shipping Test '.Str::random(8), 'slug' => 'shipping-test-'.Str::lower(Str::random(10)), 'description' => null, 'is_system' => false, 'is_active' => true]);
        $role->permissions()->attach($permissions->pluck('id'));
        $user->roles()->attach($role->id);
        $user->locations()->attach($location->id, ['access_level' => $accessLevel]);
        $order = SalesOrder::query()->create([
            'order_number' => 'SHIP-'.strtoupper(Str::random(8)), 'status' => 'confirmed', 'channel' => 'backoffice',
            'location_id' => $location->id, 'customer_name' => 'Shipping Customer', 'customer_phone' => '01711111111',
            'currency' => 'BDT', 'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 100,
            'created_by' => $user->id,
        ]);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id, 'provider' => 'steadfast', 'idempotency_key' => 'ship-'.Str::uuid(),
            'tracking_code' => 'TRACK-'.Str::upper(Str::random(6)), 'status' => 'booked',
            'recipient_name' => 'Shipping Customer', 'recipient_phone' => '01711111111',
            'recipient_address' => 'Dhaka', 'cod_amount' => 100, 'created_by' => $user->id,
        ]);

        return compact('user', 'location', 'order', 'shipment');
    }

    public function test_delivery_dashboard_is_location_scoped_and_returns_summary(): void
    {
        ['user' => $user, 'shipment' => $shipment] = $this->fixture();
        $this->actingAs($user)->getJson('/api/v1/backend/shipping/overview')->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('data.data.0.id', $shipment->id);
    }

    public function test_manual_delivery_status_change_is_transition_checked_and_audited(): void
    {
        ['user' => $user, 'shipment' => $shipment] = $this->fixture();
        $this->actingAs($user)->patchJson('/api/v1/backend/shipping/shipments/'.$shipment->id.'/status', [
            'status' => 'in_transit', 'reason' => 'Courier pickup confirmed by operations.',
        ])->assertOk()->assertJsonPath('data.status', 'in_transit');
        $this->assertDatabaseHas('sales_order_shipment_events', [
            'sales_order_shipment_id' => $shipment->id, 'provider' => 'manual',
            'previous_status' => 'booked', 'received_status' => 'in_transit', 'outcome' => 'applied',
        ]);
    }

    public function test_view_only_location_access_cannot_manually_change_delivery_status(): void
    {
        ['user' => $user, 'shipment' => $shipment] = $this->fixture('view');

        $this->actingAs($user)->patchJson('/api/v1/backend/shipping/shipments/'.$shipment->id.'/status', [
            'status' => 'in_transit',
            'reason' => 'Attempted update from a view-only location assignment.',
        ])->assertForbidden();

        $this->assertDatabaseHas('sales_order_shipments', [
            'id' => $shipment->id,
            'status' => 'booked',
        ]);
        $this->assertDatabaseMissing('sales_order_shipment_events', [
            'sales_order_shipment_id' => $shipment->id,
            'provider' => 'manual',
        ]);
    }

    public function test_invalid_delivery_transition_is_rejected(): void
    {
        ['user' => $user, 'shipment' => $shipment] = $this->fixture();
        $shipment->update(['status' => 'returned']);
        $this->actingAs($user)->patchJson('/api/v1/backend/shipping/shipments/'.$shipment->id.'/status', [
            'status' => 'in_transit', 'reason' => 'Trying to reopen a returned shipment.',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('sales_order_shipment_events', ['sales_order_shipment_id' => $shipment->id, 'provider' => 'manual']);
    }
}
