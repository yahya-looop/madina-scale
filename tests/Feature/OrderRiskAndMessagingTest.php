<?php

namespace Tests\Feature;

use App\Models\SalesOrder;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrderShipment;
use App\Models\User;
use App\Services\OrderRiskService;
use App\Services\OrderSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderRiskAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $phone, string $status = 'pending'): SalesOrder
    {
        $location = \inventoryTestLocation();

        return SalesOrder::query()->create([
            'order_number' => 'RISK-'.strtoupper(bin2hex(random_bytes(5))),
            'status' => $status, 'channel' => 'backoffice', 'location_id' => $location->id,
            'customer_name' => 'Risk Test Customer', 'customer_phone' => $phone, 'currency' => 'BDT',
            'subtotal' => 100, 'discount_total' => 0, 'tax_total' => 0, 'grand_total' => 100,
            'created_by' => User::factory()->create()->id,
        ]);
    }

    public function test_risk_engine_flags_repeated_cancelled_orders_for_manual_review(): void
    {
        $this->makeOrder('01711111111', 'cancelled');
        $this->makeOrder('01711111111', 'cancelled');
        $this->makeOrder('01711111111', 'cancelled');
        $order = $this->makeOrder('01711111111');

        $assessment = app(OrderRiskService::class)->assess($order);

        $this->assertGreaterThanOrEqual(65, $assessment->score);
        $this->assertSame('high', $assessment->level);
        $this->assertSame('manual_review', $assessment->decision);
        $this->assertNotEmpty($assessment->signals);
    }

    public function test_sms_is_logged_as_skipped_when_gateway_is_not_configured(): void
    {
        config(['order_integrations.sms.enabled' => false, 'order_integrations.sms.endpoint' => null, 'order_integrations.sms.api_key' => null]);
        $order = $this->makeOrder('01711111111');

        $log = app(OrderSmsService::class)->sendOrderEvent($order, 'confirmed');

        $this->assertSame('skipped', $log->status);
        $this->assertDatabaseHas('sales_order_sms_logs', ['id' => $log->id, 'sales_order_id' => $order->id, 'status' => 'skipped']);
    }

    public function test_bangladesh_phone_numbers_are_normalized_for_sms(): void
    {
        $service = app(OrderSmsService::class);
        $this->assertSame('+8801711111111', $service->normalizePhone('01711111111'));
        $this->assertSame('+8801711111111', $service->normalizePhone('8801711111111'));
    }

    private function shipmentManager(SalesOrder $order): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'orders'], [
            'name' => 'Orders', 'description' => 'Order management.',
            'sort_order' => 30, 'is_system' => true, 'is_active' => true,
        ]);
        $permission = Permission::firstOrCreate(['slug' => 'system.orders.shipment.manage'], [
            'module_id' => $module->id, 'name' => 'Manage order shipments',
            'description' => 'Allows courier shipment management.',
        ]);
        $role = Role::create([
            'name' => 'Shipment Test Role', 'slug' => 'shipment-test-'.Str::lower(Str::random(12)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);
        $user->locations()->attach($order->location_id, ['access_level' => 'manage']);

        return $user;
    }

    public function test_failed_shipment_replay_is_not_reported_as_success_and_does_not_send_booking_sms(): void
    {
        $order = $this->makeOrder('01711111111', 'confirmed');
        $user = $this->shipmentManager($order);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id,
            'provider' => 'steadfast',
            'idempotency_key' => 'failed-replay-'.$order->id,
            'status' => 'failed',
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh',
            'cod_amount' => 100,
            'last_error' => 'Provider timeout; booking outcome requires review.',
        ]);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/shipments', [
            'provider' => 'steadfast',
            'idempotency_key' => $shipment->idempotency_key,
            'recipient_address' => 'Dhaka, Bangladesh',
        ])->assertStatus(422)
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('sales_order_shipments', ['id' => $shipment->id, 'status' => 'failed']);
        $this->assertDatabaseMissing('sales_order_sms_logs', [
            'sales_order_id' => $order->id,
            'event' => 'shipment_booked',
        ]);
    }

    public function test_successful_shipment_replay_does_not_send_duplicate_booking_sms(): void
    {
        $order = $this->makeOrder('01711111111', 'confirmed');
        $user = $this->shipmentManager($order);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id,
            'provider' => 'steadfast',
            'idempotency_key' => 'booked-replay-'.$order->id,
            'provider_shipment_id' => 'CONS-REPLAY-'.$order->id,
            'tracking_code' => 'TRACK-REPLAY-'.$order->id,
            'status' => 'booked',
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh',
            'cod_amount' => 100,
            'booked_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/shipments', [
            'provider' => 'steadfast',
            'idempotency_key' => $shipment->idempotency_key,
            'recipient_address' => 'Dhaka, Bangladesh',
        ])->assertOk()
            ->assertJsonPath('data.id', $shipment->id)
            ->assertJsonPath('data.status', 'booked');

        $this->assertDatabaseMissing('sales_order_sms_logs', [
            'sales_order_id' => $order->id,
            'event' => 'shipment_booked',
        ]);
    }

    public function test_failed_shipment_can_be_retried_after_explicit_reconciliation_and_is_audited_without_sms(): void
    {
        config(['order_integrations.couriers.steadfast' => [
            'enabled' => true, 'base_url' => 'https://courier.test/api', 'api_key' => 'test-key',
            'secret_key' => 'test-secret', 'timeout' => 5,
        ]]);
        Http::fake(['courier.test/*' => Http::response([
            'status' => 'success',
            'consignment' => ['tracking_code' => 'TRACK-RETRY-1', 'consignment_id' => 'CONS-RETRY-1'],
        ], 200)]);

        $order = $this->makeOrder('01711111111', 'confirmed');
        $user = $this->shipmentManager($order);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id, 'provider' => 'steadfast',
            'idempotency_key' => 'retry-'.$order->id, 'status' => 'failed',
            'recipient_name' => $order->customer_name, 'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh', 'cod_amount' => 100,
            'last_error' => 'Previous timeout; portal checked by operator.',
        ]);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/shipments/'.$shipment->id.'/retry', [
            'confirm_no_existing_shipment' => true,
            'retry_reason' => 'Checked courier merchant portal; no parcel exists',
        ])->assertOk()->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.tracking_code', 'TRACK-RETRY-1');

        $shipment->refresh();
        $this->assertSame('booked', $shipment->status);
        $this->assertSame('booked', data_get($shipment->metadata, 'retry_attempts.0.outcome'));
        $this->assertSame((int) $user->id, data_get($shipment->metadata, 'retry_attempts.0.requested_by'));
        $this->assertDatabaseMissing('sales_order_sms_logs', ['sales_order_id' => $order->id, 'event' => 'shipment_booked']);
        Http::assertSentCount(1);
    }

    public function test_retry_validation_failure_restores_failed_status_and_audits_attempt(): void
    {
        config(['order_integrations.couriers.steadfast' => ['enabled' => false]]);
        $order = $this->makeOrder('01711111111', 'confirmed');
        $user = $this->shipmentManager($order);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id, 'provider' => 'steadfast',
            'idempotency_key' => 'retry-disabled-'.$order->id, 'status' => 'failed',
            'recipient_name' => $order->customer_name, 'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh', 'cod_amount' => 100,
        ]);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/shipments/'.$shipment->id.'/retry', [
            'confirm_no_existing_shipment' => true,
            'retry_reason' => 'Checked courier merchant portal; no parcel exists',
        ])->assertStatus(422);

        $shipment->refresh();
        $this->assertSame('failed', $shipment->status);
        $this->assertSame('failed', data_get($shipment->metadata, 'retry_attempts.0.outcome'));
        $this->assertNotEmpty(data_get($shipment->metadata, 'retry_attempts.0.error'));
    }

    public function test_retry_rejects_non_failed_shipments_without_calling_courier(): void
    {
        $order = $this->makeOrder('01711111111', 'confirmed');
        $user = $this->shipmentManager($order);
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id, 'provider' => 'steadfast',
            'idempotency_key' => 'retry-booking-'.$order->id, 'status' => 'booking',
            'recipient_name' => $order->customer_name, 'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh', 'cod_amount' => 100,
        ]);

        $this->actingAs($user)->postJson('/api/orders/'.$order->id.'/shipments/'.$shipment->id.'/retry', [
            'confirm_no_existing_shipment' => true,
            'retry_reason' => 'Checked courier merchant portal; no parcel exists',
        ])->assertStatus(202)->assertJsonPath('data.status', 'booking');

        $this->assertSame('booking', $shipment->fresh()->status);
    }

    public function test_courier_webhook_records_events_and_prevents_status_regression(): void
    {
        config(['order_integrations.webhook_secret' => 'test-webhook-secret']);
        $order = $this->makeOrder('01711111111', 'confirmed');
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id,
            'provider' => 'steadfast',
            'idempotency_key' => 'shipment-'.$order->id,
            'provider_shipment_id' => 'CONS-'.$order->id,
            'tracking_code' => 'TRACK-'.$order->id,
            'status' => 'booked',
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => 'Dhaka, Bangladesh',
            'cod_amount' => 100,
        ]);

        $payload = ['tracking_code' => $shipment->tracking_code, 'status' => 'in_transit', 'event_id' => 'evt-100'];
        $this->withToken('test-webhook-secret')->postJson('/api/webhooks/couriers/steadfast', $payload)
            ->assertOk()->assertJsonPath('data.status', 'in_transit');
        $this->withToken('test-webhook-secret')->postJson('/api/webhooks/couriers/steadfast', $payload)
            ->assertOk()->assertJsonPath('data.duplicate', true);

        $this->withToken('test-webhook-secret')->postJson('/api/webhooks/couriers/steadfast', [
            'tracking_code' => $shipment->tracking_code, 'status' => 'booked', 'event_id' => 'evt-old',
        ])->assertAccepted()->assertJsonPath('data.status', 'in_transit');

        $this->assertDatabaseHas('sales_order_shipments', ['id' => $shipment->id, 'status' => 'in_transit']);
        $this->assertDatabaseHas('sales_order_shipment_events', ['provider' => 'steadfast', 'event_id' => 'evt-100', 'outcome' => 'applied']);
        $this->assertDatabaseHas('sales_order_shipment_events', ['provider' => 'steadfast', 'event_id' => 'evt-old', 'outcome' => 'ignored_transition']);
    }
}
