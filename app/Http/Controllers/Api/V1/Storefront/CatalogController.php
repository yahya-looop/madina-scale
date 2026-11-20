<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\PricingEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Public, read-only catalogue contract for the Nuxt storefront. */
class CatalogController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $categories = ProductCategory::query()
            ->whereNull('parent_id')->where('status', 'active')->where('visibility', 'visible')
            ->with(['children' => fn ($query) => $query->where('status', 'active')->where('visibility', 'visible')->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'image_path'])
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id, 'name' => $category->name, 'slug' => $category->slug,
                'image_url' => $category->image_path ? Storage::disk('public')->url($category->image_path) : null,
                'children' => $category->children->map(fn (ProductCategory $child) => [
                    'id' => $child->id, 'name' => $child->name, 'slug' => $child->slug,
                    'image_url' => $child->image_path ? Storage::disk('public')->url($child->image_path) : null,
                    'children' => [],
                ])->values(),
            ])->values();

        return response()->json(['data' => $categories]);
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:160'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $products = $this->publicProductsQuery()
            ->when(filled($validated['q'] ?? null), fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('name', 'ilike', '%'.$validated['q'].'%')->orWhere('short_description', 'ilike', '%'.$validated['q'].'%')))
            ->when(filled($validated['category'] ?? null), fn (Builder $query) => $query->whereHas('category', fn (Builder $q) => $q->where('slug', $validated['category'])))
            ->orderBy('sort_order')->orderBy('name')->paginate(12)->withQueryString();
        $products->through(fn (Product $product) => $this->present($product));
        return response()->json($products);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $product = $this->publicProductsQuery()->with(['media' => fn ($q) => $q->where('collection', 'images')->orderBy('sort_order')->orderBy('id')])->where('slug', $slug)->firstOrFail();
        $data = $this->present($product);
        $data['description'] = $product->description;
        $data['images'] = $product->media->filter(fn ($media) => $media->isImage())->map(fn ($media) => [
            'url' => preg_match('/^https?:\/\//i', $media->path) === 1 ? $media->path : Storage::disk($media->disk ?: 'public')->url($media->path),
            'alt' => $media->alt_text ?: $product->name,
        ])->values();
        $data['variants'] = $product->activeVariants->map(fn ($variant) => [
            'id' => $variant->id,
            'name' => $variant->name ?: 'Standard',
            'sku' => $variant->sku ?: $product->sku,
            'price' => app(PricingEngine::class)->flashSalePriceForVariant($variant->id) ?? $variant->effectiveSellingPrice(),
            'regular_price' => $variant->effectiveSellingPrice(),
            'has_flash_sale' => app(PricingEngine::class)->flashSalePriceForVariant($variant->id) !== null,
            'has_wholesale_pricing' => $variant->wholesale_price !== null,
            'is_default' => $variant->is_default,
            'attributes' => $variant->attributeValues->map(fn ($mapping) => [
                'name' => $mapping->attribute?->name,
                'value' => $mapping->attributeValue?->name ?: $mapping->attributeValue?->value,
                'color_code' => $mapping->attributeValue?->color_code,
            ])->filter(fn ($attribute) => filled($attribute['name']) && filled($attribute['value']))->values(),
        ])->values();
        return response()->json(['data' => $data]);
    }

    private function publicProductsQuery(): Builder
    {
        return Product::query()->where('status', 'active')->where('visibility', 'visible')
            ->whereHas('category', fn (Builder $q) => $q->where('status', 'active')->where('visibility', 'visible'))
            ->whereHas('unit', fn (Builder $q) => $q->where('status', 'active'))
            ->whereHas('activeVariants')
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'unit:id,name,short_name,slug',
                'primaryMedia',
                'activeVariants' => fn ($q) => $q
                    ->select(['id','product_id','name','sku','selling_price','wholesale_price','promotional_price','promotion_starts_at','promotion_ends_at','is_default','sort_order'])
                    ->with([
                        'attributeValues' => fn ($attributes) => $attributes
                            ->whereHas('attribute', fn ($query) => $query->where('status', 'active'))
                            ->whereHas('attributeValue', fn ($query) => $query->where('status', 'active'))
                            ->with([
                                'attribute:id,name,type',
                                'attributeValue:id,name,value,color_code',
                            ]),
                    ])
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order'),
            ]);
    }

    private function present(Product $product): array
    {
        $variant = $product->activeVariants->firstWhere('is_default', true) ?? $product->activeVariants->first();
        $media = $product->primaryMedia;
        $image = $media ? (preg_match('/^https?:\/\//i', $media->path) === 1 ? $media->path : Storage::disk($media->disk ?: 'public')->url($media->path)) : null;
        return [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
            'short_description' => $product->short_description,
            'category' => $product->category?->name,
            'category_slug' => $product->category?->slug,
            'brand' => $product->brand?->name,
            'unit' => $product->unit?->short_name,
            'sku' => $product->sku,
            'model_number' => $product->model_number,
            'barcode' => $product->barcode,
            'product_type' => $product->product_type,
            'image_url' => $image,
            'price' => $variant ? (app(PricingEngine::class)->flashSalePriceForVariant($variant->id) ?? $variant->effectiveSellingPrice()) : null,
            'regular_price' => $variant?->effectiveSellingPrice(),
            'has_flash_sale' => $variant ? app(PricingEngine::class)->flashSalePriceForVariant($variant->id) !== null : false,
            'has_wholesale_pricing' => $product->activeVariants->contains(fn ($item) => $item->wholesale_price !== null),
            'variant_count' => $product->activeVariants->count(),
            'default_variant_id' => $variant?->id,
        ];
    }
}
