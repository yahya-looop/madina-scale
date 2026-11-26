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
            $servesZone = in_array($zone, $provider->service_zones ?? [], true);
            $rule = $servesZone ? $provider->rates->first() : null;
            if (! $servesZone || ! $rule) {
                $quotes[] = [
                    'provider_code' => $provider->code,
                    'provider_name' => $provider->name,
                    'charge' => null,
                    'priority' => (int) $provider->priority,
                    'rate_rule_id' => null,
                    'is_available' => false,
                    'unavailable_reason' => $servesZone
                        ? 'No matching rate configured for this zone and parcel weight.'
                        : 'This courier does not serve the selected delivery zone.',
                    'is_demo' => false,
                    'is_live_quote' => false,
                ];
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
                'is_available' => true,
                'unavailable_reason' => null,
                'is_demo' => false,
                'is_live_quote' => false,
            ];
        }

        $availableQuotes = array_values(array_filter($quotes, fn (array $quote): bool => $quote['is_available']));
        usort($availableQuotes, fn (array $a, array $b): int => ((float) $a['charge'] <=> (float) $b['charge']) ?: ($a['priority'] <=> $b['priority']));
        $recommended = $availableQuotes[0] ?? null;
        $selected = null;

        if ($preferredCode !== null) {
            foreach ($availableQuotes as $quote) {
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

        // Keep active providers visible to the storefront even when their
        // current zone/weight has no configured tariff. Unavailable providers
        // are display-only; checkout must use an available quote.

        return [
            'zone' => $zone,
            'parcel_weight_grams' => max(1, $weightGrams),
            'recommended_provider_code' => $recommended['provider_code'] ?? null,
            'selected_provider' => $selected,
            'charge' => $selected['charge'] ?? $recommended['charge'] ?? '0.00',
            'quotes' => $quotes,
            'available_quotes' => $availableQuotes,
            'is_demo' => false,
            'is_live_quote' => false,
            'pricing_source' => 'admin_configured_rate_rules',
            'calculated_at' => now()->toIso8601String(),
        ];
    }
}
