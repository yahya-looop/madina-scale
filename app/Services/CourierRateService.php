<?php

namespace App\Services;

use App\Models\CourierProvider;
use Illuminate\Validation\ValidationException;

/** Calculates storefront delivery prices from the rates configured in Laravel Admin. */
class CourierRateService
{
    /** @return array<string, mixed> */
    public function quote(string $zone, int $weightGrams, float $codAmount = 0, ?string $preferredCode = null): array
    {
        $providers = CourierProvider::query()
            ->with(['rates' => fn ($query) => $query
                ->where('zone_code', $zone)
                ->where('is_enabled', true)
                ->where('min_weight_grams', '<=', max(1, $weightGrams))
                ->orderByDesc('min_weight_grams')
                ->orderBy('sort_order')
                ->orderBy('id')])
            ->where('is_enabled', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $quotes = [];
        foreach ($providers as $provider) {
            if (! in_array($zone, $provider->service_zones ?? [], true)) {
                continue;
            }

            $rule = $provider->rates->first();
            if (! $rule) {
                continue;
            }

            // Select the highest applicable weight band. Its base charge covers
            // the band through max_weight_grams; heavier parcels use the configured
            // additional-per-kg amount for each started kg above that ceiling.
            $extraKg = $rule->max_weight_grams === null
                ? 0
                : (int) ceil(max(0, $weightGrams - (int) $rule->max_weight_grams) / 1000);
            $base = (float) $rule->base_charge;
            $weightSurcharge = $extraKg * (float) $rule->additional_per_kg;
            $codSurcharge = max(0, $codAmount) * ((float) $rule->cod_charge_percent / 100);
            $charge = round($base + $weightSurcharge + $codSurcharge, 2);

            $quotes[] = [
                'provider_code' => $provider->code,
                'provider_name' => $provider->name,
                'charge' => number_format($charge, 2, '.', ''),
                'priority' => (int) $provider->priority,
                'rate_rule_id' => $rule->id,
                'is_demo' => false,
                'is_live_quote' => false,
            ];
        }

        if ($quotes === []) {
            throw ValidationException::withMessages([
                'delivery' => 'No active courier rate is configured for this delivery zone and parcel weight. Please contact the store or try again later.',
            ]);
        }

        usort($quotes, fn (array $a, array $b): int => ((float) $a['charge'] <=> (float) $b['charge']) ?: ($a['priority'] <=> $b['priority']));
        $recommended = $quotes[0];
        $selected = null;

        if ($preferredCode !== null) {
            foreach ($quotes as $quote) {
                if ($quote['provider_code'] === $preferredCode) {
                    $selected = $quote;
                    break;
                }
            }
            if ($selected === null) {
                throw ValidationException::withMessages([
                    'customer_preferred_courier_code' => 'The selected courier is not enabled or has no matching rate for this destination and parcel weight.',
                ]);
            }
        }

        return [
            'zone' => $zone,
            'parcel_weight_grams' => max(1, $weightGrams),
            'recommended_provider_code' => $recommended['provider_code'],
            'selected_provider' => $selected,
            'charge' => $selected['charge'] ?? $recommended['charge'],
            'quotes' => $quotes,
            'is_demo' => false,
            'is_live_quote' => false,
            'pricing_source' => 'admin_configured_rate_rules',
            'calculated_at' => now()->toIso8601String(),
        ];
    }
}
