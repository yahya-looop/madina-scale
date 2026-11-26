<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\PricingEngine;
use App\Services\SalesOrderService;
use App\Services\CourierRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorefrontCartController extends Controller
{
    public function __construct(private readonly FeatureFlagService $featureFlags) {}

    private function ensureStorefrontEnabled(Request $request): void
    {
        $user = $request->user();
        $context = $request->hasSession() ? $request->session()->getId() : null;

        abort_unless(
            $this->featureFlags->enabled(
                'storefront.checkout.enabled',
                $user instanceof User ? $user : null,
                $context,
            ),
            404,
        );
    }

    public function deliveryQuotes(Request $request, CourierRateService $courierRates): JsonResponse
    {
        $this->ensureStorefrontEnabled($request);
        $data = $request->validate([
            'division' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'in:cash_on_delivery,bank_transfer'],
        ]);
        $cart = $this->resolvedCart($request);
        if ($cart === []) {
            return response()->json(['data' => ['zone' => null, 'parcel_weight_grams' => 0, 'quotes' => [], 'recommended_provider_code' => null, 'charge' => '0.00']]);
        }

        $weight = (int) collect($cart)->sum(fn (array $item): int => (int) $item['shipping_weight_grams'] * (int) $item['quantity']);
        $codAmount = ($data['payment_method'] ?? 'cash_on_delivery') === 'cash_on_delivery'
            ? (float) collect($cart)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity'])
            : 0.0;
        $zone = $this->resolveDeliveryZone((string) ($data['district'] ?? ''), (string) ($data['area'] ?? ''));

        return response()->json(['data' => $courierRates->quote($zone, $weight, $codAmount)]);
    }

    public function checkout(Request $request, SalesOrderService $orders, CourierRateService $courierRates): JsonResponse
    {
        $this->ensureStorefrontEnabled($request);
        $phoneInput = (string) $request->input('customer_phone', '');
        $phoneInput = strtr($phoneInput, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
        $phoneInput = preg_replace('/[\s-]+/', '', $phoneInput) ?? $phoneInput;
        $request->merge(['customer_phone' => $phoneInput]);

        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'division' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', 'in:cash_on_delivery,bank_transfer'],
            'customer_preferred_courier_code' => ['nullable', 'string', 'in:steadfast,carrybee,bangladesh_post,pathao,redx'],
            'delivery_selection_mode' => ['required', 'string', 'in:customer_choice,no_preference'],
            'bank_sender_account' => ['required_if:payment_method,bank_transfer', 'nullable', 'string', 'max:80'],
            'bank_transaction_reference' => ['required_if:payment_method,bank_transfer', 'nullable', 'string', 'max:120'],
            'bank_transfer_amount' => ['required_if:payment_method,bank_transfer', 'nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $cart = $this->resolvedCart($request);
        if ($cart === []) {
            $lastOrder = $request->session()->get('storefront_last_order');
            if (is_array($lastOrder) && ($lastOrder['idempotency_key'] ?? null) === $data['idempotency_key']) {
                return response()->json(['message' => 'Order already placed.', 'data' => $lastOrder]);
            }
            throw ValidationException::withMessages(['cart' => 'আপনার কার্টে কোনো পণ্য নেই।']);
        }
        $parcelWeightGrams = (int) collect($cart)->sum(fn (array $item): int => (int) $item['shipping_weight_grams'] * (int) $item['quantity']);
        $deliveryZone = $this->resolveDeliveryZone((string) ($data['district'] ?? ''), (string) ($data['area'] ?? ''));
        $courierCode = $data['delivery_selection_mode'] === 'customer_choice' ? ($data['customer_preferred_courier_code'] ?? null) : null;
        if ($data['delivery_selection_mode'] === 'customer_choice' && ! $courierCode) {
            throw ValidationException::withMessages(['customer_preferred_courier_code' => 'Please select an available delivery partner.']);
        }
        $codAmount = $data['payment_method'] === 'cash_on_delivery'
            ? (float) collect($cart)->sum(fn (array $item): float => (float) $item['price'] * (int) $item['quantity'])
            : 0.0;
        $deliveryQuote = $courierRates->quote($deliveryZone, $parcelWeightGrams, $codAmount, $courierCode);
        $assignedCourierCode = $courierCode ?: $deliveryQuote['recommended_provider_code'];
        $location = Location::query()->active()->orderByDesc('is_primary')->orderBy('id')->first();
        if (! $location) {
            throw ValidationException::withMessages(['cart' => 'এই মুহূর্তে অর্ডার নেওয়া সম্ভব হচ্ছে না। অনুগ্রহ করে পরে চেষ্টা করুন।']);
        }

        $phone = preg_replace('/\D+/', '', $data['customer_phone']) ?? '';
        if (strlen($phone) === 13 && str_starts_with($phone, '880')) {
            $phone = '0'.substr($phone, 3);
        } elseif (strlen($phone) === 14 && str_starts_with($phone, '880')) {
            $phone = '0'.substr($phone, 3);
        }
        $providedCustomerName = trim((string) ($data['customer_name'] ?? ''));
        $customerName = $providedCustomerName ?: 'Online Customer';
        $addressLine = trim((string) ($data['address_line'] ?? ''));
        $district = trim((string) ($data['district'] ?? ''));
        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer) {
            $customer = Customer::query()->create([
                'customer_code' => 'CUS-'.now()->format('ym').'-'.Str::upper(Str::random(6)),
                'name' => $customerName,
                'phone' => $phone,
                'address_line' => $addressLine ?: null,
                'area' => $data['area'] ?? null,
                'district' => $district ?: null,
                'division' => $data['division'] ?? null,
                'status' => 'active',
            ]);
        } else {
            $customer->fill([
                'name' => $providedCustomerName ?: $customer->name,
                'address_line' => $addressLine ?: $customer->address_line,
                'area' => filled($data['area'] ?? null) ? $data['area'] : $customer->area,
                'district' => $district ?: $customer->district,
                'division' => filled($data['division'] ?? null) ? $data['division'] : $customer->division,
            ])->save();
        }

        $paymentDetails = $data['payment_method'] === 'bank_transfer' ? [
            'sender_account' => trim((string) $data['bank_sender_account']),
            'transaction_reference' => trim((string) $data['bank_transaction_reference']),
            'declared_amount' => number_format((float) $data['bank_transfer_amount'], 2, '.', ''),
            'submitted_at' => now()->toIso8601String(),
        ] : null;

        $order = $orders->create([
            'location_id' => $location->id,
            'customer_id' => $customer->id,
            'customer_name' => $customerName,
            'customer_phone' => $phone,
            'notes' => mb_substr(trim(implode("\n", array_filter([$data['notes'] ?? null, $addressLine !== '' ? 'Delivery address: '.implode(', ', array_filter([$addressLine, $data['area'] ?? null, $district, $data['division'] ?? null])) : null]))), 0, 1000) ?: null,
            'channel' => 'storefront',
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_method'] === 'bank_transfer' ? 'awaiting_verification' : 'unpaid',
            'payment_details' => $paymentDetails,
            'idempotency_key' => $data['idempotency_key'],
            'items' => collect($cart)->map(fn (array $item): array => [
                'product_variant_id' => $item['variant_id'],
                'quantity' => $item['quantity'],
            ])->values()->all(),
        ], null, true);

        // Delivery amounts are recalculated by Laravel from the active Admin rate rules; client prices are never trusted.
        $order->forceFill([
            'delivery_charge' => $deliveryQuote['charge'],
            'parcel_weight_grams' => $parcelWeightGrams,
            'delivery_zone' => $deliveryZone,
            'delivery_selection_mode' => $data['delivery_selection_mode'],
            'customer_preferred_courier_code' => $courierCode,
            'system_recommended_courier_code' => $deliveryQuote['recommended_provider_code'],
            'assigned_courier_code' => $assignedCourierCode,
            'delivery_quote_snapshot' => $deliveryQuote,
            'grand_total' => bcadd((string) $order->grand_total, (string) $deliveryQuote['charge'], 2),
        ])->save();

        $request->session()->forget('storefront_cart');
        $request->session()->put('storefront_last_order', [
            'order_number' => $order->order_number,
            'customer_phone' => $phone,
            'grand_total' => $order->grand_total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'idempotency_key' => $data['idempotency_key'],
        ]);

        return response()->json(['message' => 'Your order has been placed.', 'data' => [
            'order_number' => $order->order_number,
            'customer_phone' => $phone,
            'grand_total' => $order->grand_total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'delivery_charge' => $order->delivery_charge,
            'assigned_courier_code' => $order->assigned_courier_code,
            'parcel_weight_grams' => $order->parcel_weight_grams,
        ]], 201);
    }

    private function resolveDeliveryZone(string $district, string $area): string
    {
        $normalized = mb_strtolower(trim($district.' '.$area));
        if (str_contains($normalized, 'dhaka') || str_contains($normalized, 'ঢাকা')) {
            return 'inside_dhaka';
        }
        $remoteDistricts = ['bandarban', 'khagrachhari', 'rangamati', 'bhola', 'patuakhali', 'barguna', 'sunamganj', 'কক্সবাজার'];
        foreach ($remoteDistricts as $name) {
            if (str_contains($normalized, $name)) return 'remote_special';
        }
        return 'outside_dhaka';
    }

    /** @return array<int, array<string, mixed>> */
    private function resolvedCart(Request $request): array
    {
        $cart = $request->session()->get('storefront_cart', []);
        if (! is_array($cart) || $cart === []) {
            return [];
        }

        // Bound storefront query size even for legacy sessions created before
        // the cart-line cap was introduced.
        $cart = array_slice($cart, 0, 100, true);
        $variants = ProductVariant::query()->with(['product.primaryMedia'])
            ->whereIn('id', array_keys($cart))->where('status', 'active')->get()->keyBy('id');
        $items = [];
        foreach ($cart as $id => $quantity) {
            $variant = $variants[(int) $id] ?? null;
            if (! $variant || $variant->product->status !== 'active' || $variant->product->visibility !== 'visible') {
                continue;
            }
            $items[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name ?: 'Standard',
                'sku' => $variant->sku ?: $variant->product->sku,
                'price' => app(PricingEngine::class)->flashSalePriceForVariant($variant->id) ?? $variant->effectiveSellingPrice(),
                'shipping_weight_grams' => (int) ($variant->shipping_weight_override_grams ?? $variant->product->shipping_weight_grams ?? 500),
                'quantity' => min(99, max(1, (int) $quantity)),
            ];
        }

        return $items;
    }
}
