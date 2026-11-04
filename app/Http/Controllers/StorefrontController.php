<?php

namespace App\Http\Controllers;

use App\Models\CmsBanner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\PricingEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));

        $products = $this->publicProductsQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $productQuery) use ($search): void {
                    $productQuery
                        ->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('short_description', 'ilike', '%'.$search.'%');
                });
            })
            ->when($categorySlug !== '', function (Builder $query) use ($categorySlug): void {
                $query->whereHas('category', function (Builder $categoryQuery) use ($categorySlug): void {
                    $categoryQuery->where('slug', $categorySlug);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $products->through(fn (Product $product): array => $this->presentProduct($product));

        $categories = ProductCategory::query()
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $now = now();
        $banners = CmsBanner::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where('placement', 'homepage')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'image_url', 'alt_text', 'link_url']);

        return Inertia::render('Storefront/Index', [
            'products' => $products,
            'categories' => $categories,
            'banners' => $banners,
            'filters' => [
                'q' => $search,
                'category' => $categorySlug,
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $product = $this->publicProductsQuery()
            ->with(['media' => function ($query): void {
                $query->where('collection', 'images')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where('slug', $slug)
            ->firstOrFail();

        $data = $this->presentProduct($product);
        $data['description'] = $product->description;
        $data['images'] = $product->media
            ->filter(fn ($media): bool => $media->isImage())
            ->map(fn ($media): array => [
                'url' => $this->mediaUrl($media->disk, $media->path),
                'alt' => $media->alt_text ?: $product->name,
            ])
            ->values();
        $data['variants'] = $product->activeVariants
            ->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name ?: 'Standard',
                'price' => app(PricingEngine::class)->flashSalePriceForVariant($variant->id) ?? $variant->effectiveSellingPrice(),
                'has_flash_sale' => app(PricingEngine::class)->flashSalePriceForVariant($variant->id) !== null,
                'regular_price' => $variant->effectiveSellingPrice(),
                'has_wholesale_pricing' => $variant->wholesale_price !== null,
                'is_default' => $variant->is_default,
            ])
            ->values();

        return Inertia::render('Storefront/Show', [
            'product' => $data,
        ]);
    }

    /** @return Builder<Product> */
    private function publicProductsQuery(): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->whereHas('category', function (Builder $query): void {
                $query->where('status', 'active')
                    ->where('visibility', 'visible');
            })
            ->whereHas('unit', function (Builder $query): void {
                $query->where('status', 'active');
            })
            ->whereHas('activeVariants')
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'unit:id,name,short_name,slug',
                'primaryMedia',
                'activeVariants' => function ($query): void {
                    $query->select([
                        'id',
                        'product_id',
                        'name',
                        'selling_price',
                        'wholesale_price',
                        'promotional_price',
                        'promotion_starts_at',
                        'promotion_ends_at',
                        'is_default',
                        'sort_order',
                    ])->orderByDesc('is_default')->orderBy('sort_order');
                },
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProduct(Product $product): array
    {
        $variants = $product->activeVariants;
        $defaultVariant = $variants->firstWhere('is_default', true) ?? $variants->first();
        $primaryMedia = $product->primaryMedia;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
            'unit' => $product->unit?->short_name,
            'image_url' => $primaryMedia
                ? $this->mediaUrl($primaryMedia->disk, $primaryMedia->path)
                : null,
            'price' => $defaultVariant
                ? (app(PricingEngine::class)->flashSalePriceForVariant($defaultVariant->id) ?? $defaultVariant->effectiveSellingPrice())
                : null,
            'has_flash_sale' => $defaultVariant ? app(PricingEngine::class)->flashSalePriceForVariant($defaultVariant->id) !== null : false,
            'regular_price' => $defaultVariant?->effectiveSellingPrice(),
            'has_wholesale_pricing' => $variants->contains(
                fn ($variant): bool => $variant->wholesale_price !== null
            ),
            'variant_count' => $variants->count(),
            'default_variant_id' => $defaultVariant?->id,
        ];
    }

    private function mediaUrl(?string $disk, string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return Storage::disk($disk ?: 'public')->url($path);
    }
}
