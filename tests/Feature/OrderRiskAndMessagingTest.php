<?php

namespace Tests\Feature;

use App\Models\SalesOrder;
use App\Models\SalesOrderShipment;
use App\Models\User;
use App\Services\OrderRiskService;
use App\Services\OrderSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
