<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\PricingEngine;
use App\Services\SalesOrderService;
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

    public function checkout(Request $request, SalesOrderService $orders): JsonResponse
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
        ]], 201);
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
                'quantity' => min(99, max(1, (int) $quantity)),
            ];
        }

        return $items;
    }
}
