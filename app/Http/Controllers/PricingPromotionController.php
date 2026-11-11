<?php

namespace App\Http\Controllers;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\PricingPromotion;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PricingPromotionController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'products' => Product::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'sku']),
            'categories' => ProductCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'variants' => ProductVariant::query()->with('product:id,name')->where('status', 'active')->orderBy('product_id')->get(['id', 'product_id', 'name', 'sku', 'selling_price', 'wholesale_price']),
        ]);
    }

    public function promotions(Request $request): JsonResponse
    {
        $query = PricingPromotion::query()->with(['product:id,name', 'variant:id,product_id,name,sku', 'category:id,name']);
        if ($request->filled('search')) {
            $term = trim($request->string('search')->toString());
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$term}%")->orWhere('code', 'ilike', "%{$term}%"));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])]])['status']);
        }
        $rows = $query->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 50))->withQueryString();

        return response()->json(array_merge($rows->toArray(), ['stats' => [
            'total' => PricingPromotion::count(),
            'active' => PricingPromotion::where('status', 'active')->count(),
            'scheduled' => PricingPromotion::where('status', 'active')->where('starts_at', '>', now())->count(),
            'codes' => PricingPromotion::whereNotNull('code')->count(),
        ]]));
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $data = $this->validatePromotion($request);
        $data['code'] = filled($data['code'] ?? null) ? Str::upper(trim($data['code'])) : null;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $promotion = PricingPromotion::create($data);

        return response()->json(['message' => 'Promotion created.', 'data' => $promotion->load(['product:id,name', 'variant:id,product_id,name,sku', 'category:id,name'])], 201);
    }

    public function updatePromotion(Request $request, PricingPromotion $promotion): JsonResponse
    {
        $data = $this->validatePromotion($request, $promotion);
        $data['code'] = filled($data['code'] ?? null) ? Str::upper(trim($data['code'])) : null;
        $data['updated_by'] = $request->user()->id;
        $promotion->update($data);

        return response()->json(['message' => 'Promotion updated.', 'data' => $promotion->fresh(['product:id,name', 'variant:id,product_id,name,sku', 'category:id,name'])]);
    }

    public function deletePromotion(PricingPromotion $promotion): JsonResponse
    {
        if ($promotion->redemptions()->exists()) {
            throw ValidationException::withMessages(['promotion' => 'This promotion has order redemption history and cannot be deleted. Set its status to ended instead.']);
        }
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted.']);
    }

    public function flashSales(Request $request): JsonResponse
    {
        $query = FlashSale::query()->with(['items.variant.product:id,name,slug']);
        if ($request->filled('search')) {
            $term = trim($request->string('search')->toString());
            $query->where(fn ($q) => $q->where('title', 'ilike', "%{$term}%")->orWhere('slug', 'ilike', "%{$term}%"));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])]])['status']);
        }
        $rows = $query->orderBy('priority')->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 50))->withQueryString();

        return response()->json(array_merge($rows->toArray(), ['stats' => [
            'total' => FlashSale::count(),
            'live' => FlashSale::where('status', 'active')->where('show_on_storefront', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count(),
            'scheduled' => FlashSale::where('status', 'active')->where('starts_at', '>', now())->count(),
            'products' => FlashSaleItem::count(),
        ]]));
    }

    public function storeFlashSale(Request $request): JsonResponse
    {
        $data = $this->validateFlashSale($request);
        $items = $data['items'];
        unset($data['items']);
        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $sale = DB::transaction(function () use ($data, $items) {
            $sale = FlashSale::create($data);
            $this->syncItems($sale, $items);

            return $sale->load(['items.variant.product:id,name,slug']);
        }, 3);

        return response()->json(['message' => 'Flash sale created.', 'data' => $sale], 201);
    }

    public function updateFlashSale(Request $request, FlashSale $flashSale): JsonResponse
    {
        $data = $this->validateFlashSale($request, $flashSale);
        $items = $data['items'] ?? null;
        unset($data['items']);
        $data['updated_by'] = $request->user()->id;
        if (filled($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['slug']);
        } else {
            unset($data['slug']);
        }

        $sale = DB::transaction(function () use ($flashSale, $data, $items) {
            $flashSale->update($data);
            if ($items !== null) {
                $this->syncItems($flashSale, $items);
            }

            return $flashSale->fresh(['items.variant.product:id,name,slug']);
        }, 3);

        return response()->json(['message' => 'Flash sale updated.', 'data' => $sale]);
    }

    public function deleteFlashSale(FlashSale $flashSale): JsonResponse
    {
        $hasAllocations = FlashSaleItem::query()->where('flash_sale_id', $flashSale->id)
            ->whereHas('allocations')->exists();
        if ($hasAllocations) {
            throw ValidationException::withMessages(['flash_sale' => 'This flash sale has order allocation history and cannot be deleted. Set its status to ended instead.']);
        }
        $flashSale->delete();

        return response()->json(['message' => 'Flash sale deleted.']);
    }

    public function publicFlashSales(): JsonResponse
    {
        $sales = FlashSale::query()
            ->where('status', 'active')->where('show_on_storefront', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>=', now())
            ->with(['items.variant.product' => fn ($q) => $q->where('status', 'active')->where('visibility', 'visible')])
            ->orderBy('priority')->orderBy('ends_at')->get()
            ->map(fn (FlashSale $sale): array => [
                'id' => $sale->id, 'title' => $sale->title, 'slug' => $sale->slug,
                'tagline' => $sale->tagline, 'description' => $sale->description,
                'banner_image' => $sale->banner_image, 'accent_color' => $sale->accent_color,
                'starts_at' => $sale->starts_at->toIso8601String(), 'ends_at' => $sale->ends_at->toIso8601String(),
                'items' => $sale->items->filter(fn (FlashSaleItem $item) => $item->variant !== null && $item->variant->getRelation('product') !== null && ($item->quantity_limit === null || $item->quantity_sold < $item->quantity_limit))->map(fn (FlashSaleItem $item): array => [
                    'id' => $item->id, 'product_name' => $item->variant->product->name,
                    'product_slug' => $item->variant->product->slug, 'variant_name' => $item->variant->name,
                    'sku' => $item->variant->sku, 'regular_price' => $item->regular_price,
                    'sale_price' => $item->sale_price, 'quantity_remaining' => $item->remainingQuantity(),
                ])->values(),
            ])->values();

        return response()->json(['data' => $sales]);
    }

    /** @return array<string, mixed> */
    private function validatePromotion(Request $request, ?PricingPromotion $promotion = null): array
    {
        // Coupon codes are stored uppercase; normalize before the unique rule so
        // differently-cased submissions cannot bypass the application check.
        if ($request->has('code')) {
            $request->merge(['code' => filled($request->input('code'))
                ? Str::upper(trim((string) $request->input('code')))
                : null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:60', Rule::unique('pricing_promotions', 'code')->ignore($promotion?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'scope' => ['required', Rule::in(['order', 'product', 'variant', 'category'])],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'gt:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'stackable' => ['sometimes', 'boolean'],
            'publicly_visible' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        if ($data['discount_type'] === 'percent' && $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Percentage discounts cannot exceed 100%.']);
        }
        $this->validatePromotionTarget($data);

        foreach ([
            'product' => 'product_id',
            'variant' => 'product_variant_id',
            'category' => 'product_category_id',
        ] as $scope => $field) {
            if ($scope !== $data['scope']) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Ensure a scoped promotion points to one active, compatible catalogue target.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatePromotionTarget(array $data): void
    {
        $scopeFields = [
            'product' => 'product_id',
            'variant' => 'product_variant_id',
            'category' => 'product_category_id',
        ];
        $selectedField = $scopeFields[$data['scope']] ?? null;

        foreach ($scopeFields as $scope => $field) {
            if ($scope !== $data['scope'] && filled($data[$field] ?? null)) {
                throw ValidationException::withMessages([
                    $field => 'The selected target must match the promotion scope.',
                ]);
            }
        }

        if ($selectedField === null) {
            return;
        }

        if (blank($data[$selectedField] ?? null)) {
            throw ValidationException::withMessages([
                $selectedField => 'Select a target for this promotion scope.',
            ]);
        }

        $targetIsActive = match ($data['scope']) {
            'product' => Product::query()
                ->where('status', 'active')
                ->whereKey($data['product_id'])
                ->exists(),
            'variant' => ProductVariant::query()
                ->where('status', 'active')
                ->whereKey($data['product_variant_id'])
                ->whereHas('product', fn ($query) => $query->where('status', 'active'))
                ->exists(),
            'category' => ProductCategory::query()
                ->where('status', 'active')
                ->whereKey($data['product_category_id'])
                ->exists(),
            default => false,
        };

        if (! $targetIsActive) {
            throw ValidationException::withMessages([
                $selectedField => 'The selected promotion target is inactive or unavailable.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function validateFlashSale(Request $request, ?FlashSale $sale = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('flash_sales', 'slug')->ignore($sale?->id)],
            'tagline' => ['nullable', 'string', 'max:240'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['draft', 'active', 'paused', 'ended'])],
            'banner_image' => ['nullable', 'string', 'max:2048'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'show_on_storefront' => ['sometimes', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items' => [$sale ? 'sometimes' : 'required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.regular_price' => ['required', 'numeric', 'gt:0'],
            'items.*.sale_price' => ['required', 'numeric', 'gt:0'],
            'items.*.quantity_limit' => ['nullable', 'integer', 'min:1'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param list<array{
     *     product_variant_id: int|string,
     *     regular_price: int|float|string,
     *     sale_price: int|float|string,
     *     quantity_limit?: int|string|null,
     *     sort_order?: int|string|null
     * }> $items
     */
    private function syncItems(FlashSale $sale, array $items): void
    {
        $ids = collect($items)->pluck('product_variant_id')->all();
        $variants = ProductVariant::query()->whereIn('id', $ids)->where('status', 'active')->get()->keyBy('id');
        if ($variants->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['items' => 'Every flash sale item must reference a unique active product variant.']);
        }
        foreach ($items as $item) {
            $variant = $variants->get((int) $item['product_variant_id']);
            if ((float) $item['sale_price'] >= (float) $item['regular_price']) {
                throw ValidationException::withMessages(['items' => 'Flash sale price must be lower than the regular price.']);
            }
            /** @var numeric-string $salePrice */
            $salePrice = (string) $item['sale_price'];
            /** @var numeric-string $effectiveSellingPrice */
            $effectiveSellingPrice = $variant?->effectiveSellingPrice() ?? '0.00';

            if ($variant && bccomp($salePrice, $effectiveSellingPrice, 2) >= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Flash sale price must be lower than the variant current effective selling price.',
                ]);
            }
        }
        $existing = $sale->items()->get()->keyBy('product_variant_id');
        if ($sale->items()->whereHas('allocations')->exists()) {
            throw ValidationException::withMessages(['items' => 'Flash sale items with order allocation history cannot be replaced. End the sale and create a new flash sale for changed items.']);
        }
        $sale->items()->delete();
        foreach ($items as $item) {
            $previous = $existing->get((int) $item['product_variant_id']);
            $sale->items()->create([
                'product_variant_id' => $item['product_variant_id'],
                'regular_price' => $item['regular_price'],
                'sale_price' => $item['sale_price'],
                'quantity_limit' => $item['quantity_limit'] ?? null,
                'quantity_sold' => $previous->quantity_sold ?? 0,
                'sort_order' => $item['sort_order'] ?? 0,
            ]);
        }
    }
}
