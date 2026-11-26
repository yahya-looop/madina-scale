<?php

namespace Tests\Unit;

use App\Models\CourierProvider;
use App\Services\CourierRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourierRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_enabled_admin_rate_rules_and_recommends_the_lowest_charge(): void
    {
        $expensive = $this->provider('steadfast', 'Steadfast', 10, 90);
        $cheap = $this->provider('redx', 'REDX', 20, 65);

        $quote = app(CourierRateService::class)->quote('inside_dhaka', 500);

        $this->assertSame('redx', $quote['recommended_provider_code']);
        $this->assertSame('65.00', $quote['charge']);
        $this->assertCount(2, $quote['quotes']);
        $this->assertFalse($quote['is_demo']);
        $this->assertFalse($quote['is_live_quote']);
    }

    public function test_it_applies_additional_per_kg_and_cod_percentage_from_the_selected_band(): void
    {
        $provider = $this->provider('pathao', 'Pathao Courier', 10, 70, 500, 1000, 20, 1.5);

        $quote = app(CourierRateService::class)->quote('inside_dhaka', 2001, 1000);

        // 70 base + 2 started kg above the 1,000g band ceiling + 1.5% COD.
        $this->assertSame('125.00', $quote['charge']);
    }

    public function test_it_rejects_a_preferred_provider_without_a_matching_enabled_rate(): void
    {
        $this->provider('steadfast', 'Steadfast', 10, 70);

        try {
            app(CourierRateService::class)->quote('inside_dhaka', 500, 0, 'redx');
            $this->fail('Expected a validation exception for the unavailable preferred courier.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_preferred_courier_code', $exception->errors());
        }
    }

    private function provider(
        string $code,
        string $name,
        int $priority,
        float $base,
        int $minWeight = 0,
        ?int $maxWeight = 500,
        float $additionalPerKg = 0,
        float $codPercent = 0,
    ): CourierProvider {
        // The dedicated PostgreSQL test database may already contain provider
        // rows from an earlier local run. Reuse the matching fixture row so the
        // test remains repeatable without touching the application's real DB.
        $provider = CourierProvider::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'is_enabled' => true,
                'priority' => $priority,
                'environment' => 'sandbox',
                'capabilities' => ['booking', 'tracking', 'cod'],
                'service_zones' => ['inside_dhaka', 'outside_dhaka', 'remote_special'],
                'settings' => [],
            ],
        );

        // Remove stale fixture rates so each test starts from deterministic rules.
        $provider->rates()->delete();
        $provider->rates()->create([
            'zone_code' => 'inside_dhaka',
            'min_weight_grams' => $minWeight,
            'max_weight_grams' => $maxWeight,
            'base_charge' => $base,
            'additional_per_kg' => $additionalPerKg,
            'cod_charge_percent' => $codPercent,
            'is_enabled' => true,
            'sort_order' => 0,
        ]);

        return $provider;
    }
}
