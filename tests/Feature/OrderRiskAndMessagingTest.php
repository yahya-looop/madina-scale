<?php

namespace Tests\Feature;

use App\Models\SalesOrder;
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
            'order_number' => 'RISK-' . strtoupper(bin2hex(random_bytes(5))),
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
}
