<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\SalesOrderShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function orderFixture(): array
    {
        $location = Location::query()->create([
            'name' => 'Public Tracking Test Store',
            'code' => 'TRK-'.Str::upper(Str::random(6)),
            'type' => 'store',
            'is_active' => true,
        ]);

        $order = SalesOrder::query()->create([
            'order_number' => 'MS-TRK-'.Str::upper(Str::random(8)),
            'status' => 'processing',
            'channel' => 'storefront',
            'location_id' => $location->id,
            'customer_name' => 'Tracking Customer',
            'customer_phone' => '01712345678',
            'currency' => 'BDT',
            'subtotal' => 1200,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 1200,
        ]);

        return [$order, $location];
    }

    public function test_public_order_tracking_requires_matching_order_reference_and_phone(): void
    {
        [$order] = $this->orderFixture();

        $response = $this->postJson('/api/v1/storefront/orders/track', [
            'reference' => $order->order_number,
            'phone' => '+880 1712-345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonStructure(['data' => ['items', 'timeline', 'shipments']]);

        expect($response->headers->get('Cache-Control'))->toContain('no-store');

        $this->postJson('/api/v1/storefront/orders/track', [
            'reference' => $order->order_number,
            'phone' => '01812345678',
        ])->assertNotFound()
            ->assertJsonMissingPath('data.customer_phone');
    }

    public function test_public_order_tracking_accepts_courier_tracking_number_without_exposing_customer_details(): void
    {
        [$order] = $this->orderFixture();
        SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id,
            'provider' => 'steadfast',
            'idempotency_key' => (string) Str::uuid(),
            'provider_shipment_id' => 'PROV-'.Str::upper(Str::random(6)),
            'tracking_code' => 'STF-'.Str::upper(Str::random(8)),
            'status' => 'in_transit',
            'recipient_name' => 'Tracking Customer',
            'recipient_phone' => '01712345678',
            'recipient_address' => 'Test address',
            'cod_amount' => 1200,
        ]);
        $shipment = $order->shipments()->firstOrFail();

        $this->postJson('/api/v1/storefront/orders/track', [
            'reference' => $shipment->tracking_code,
            'phone' => '01712345678',
        ])->assertOk()
            ->assertJsonPath('data.shipments.0.tracking_code', $shipment->tracking_code)
            ->assertJsonMissingPath('data.customer_phone')
            ->assertJsonMissingPath('data.customer_name');
    }
}
