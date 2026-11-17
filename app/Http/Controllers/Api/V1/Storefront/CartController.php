<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class CartController extends Controller
{
    public function __construct(private readonly FeatureFlagService $featureFlags) {}

    public function show(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);
        return response()->json(['data' => $this->resolvedCart($request)]);
    }

    public function add(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        $variant = ProductVariant::query()
            ->where('status', 'active')
            ->whereKey((int) $data['product_variant_id'])
            ->whereHas('product', fn ($q) => $q->where('status', 'active')->where('visibility', 'visible'))
            ->first();
        abort_unless($variant, 404);

        $cart = $request->session()->get('storefront_cart', []);
        $cart = is_array($cart) ? $cart : [];
        $key = (string) $variant->id;
        if (! array_key_exists($key, $cart) && count($cart) >= 100) {
            throw ValidationException::withMessages(['cart' => 'Your cart can contain up to 100 different items.']);
        }
        $cart[$key] = min(99, (int) ($cart[$key] ?? 0) + (int) ($data['quantity'] ?? 1));
        $request->session()->put('storefront_cart', $cart);

        return response()->json(['message' => 'Product added to cart.', 'data' => $this->resolvedCart($request)], 201);
    }

    public function update(Request $request, int $variant): JsonResponse
    {
        $this->ensureEnabled($request);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = $request->session()->get('storefront_cart', []);
        abort_unless(is_array($cart) && array_key_exists((string) $variant, $cart), 404);
        $cart[(string) $variant] = (int) $data['quantity'];
        $request->session()->put('storefront_cart', $cart);
        return response()->json(['message' => 'Cart updated.', 'data' => $this->resolvedCart($request)]);
    }

    public function remove(Request $request, int $variant): JsonResponse
    {
        $this->ensureEnabled($request);
        $cart = $request->session()->get('storefront_cart', []);
        if (is_array($cart)) unset($cart[(string) $variant]);
        $request->session()->put('storefront_cart', is_array($cart) ? $cart : []);
        return response()->json(['message' => 'Cart item removed.', 'data' => $this->resolvedCart($request)]);
    }

    private function ensureEnabled(Request $request): void
    {
        $user = $request->user();
        $context = $request->hasSession() ? $request->session()->getId() : null;
        abort_unless($this->featureFlags->enabled('storefront.checkout.enabled', $user instanceof User ? $user : null, $context), 404);
    }

    /** @return array{items: array<int, array<string, mixed>>, subtotal: float, item_count: int} */
    private function resolvedCart(Request $request): array
    {
        $cart = $request->session()->get('storefront_cart', []);
        if (! is_array($cart) || $cart === []) return ['items' => [], 'subtotal' => 0.0, 'item_count' => 0];
        $cart = array_slice($cart, 0, 100, true);
        $variants = ProductVariant::query()->with(['product.primaryMedia'])
            ->whereIn('id', array_keys($cart))->where('status', 'active')->get()->keyBy('id');
        $items = [];
        foreach ($cart as $id => $quantity) {
            $variant = $variants[(int) $id] ?? null;
            if (! $variant || $variant->product->status !== 'active' || $variant->product->visibility !== 'visible') continue;
            $price = (float) (app(PricingEngine::class)->flashSalePriceForVariant($variant->id) ?? $variant->effectiveSellingPrice());
            $qty = min(99, max(1, (int) $quantity));
            $media = $variant->product->primaryMedia;
            $items[] = [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'product_slug' => $variant->product->slug,
                'variant_name' => $variant->name ?: 'Standard',
                'sku' => $variant->sku ?: $variant->product->sku,
                'image_url' => $media ? Storage::disk($media->disk ?: 'public')->url($media->path) : null,
                'price' => $price,
                'quantity' => $qty,
                'line_total' => round($price * $qty, 2),
            ];
        }
        return [
            'items' => $items,
            'subtotal' => round(array_sum(array_column($items, 'line_total')), 2),
            'item_count' => array_sum(array_column($items, 'quantity')),
        ];
    }
}
