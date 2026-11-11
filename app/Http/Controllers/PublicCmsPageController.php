<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use Inertia\Inertia;
use Inertia\Response;

class PublicCmsPageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where(function ($query): void {
                $query->where(function ($published): void {
                    $published->where('status', 'published')
                        ->where(fn ($date) => $date->whereNull('published_at')->orWhere('published_at', '<=', now()));
                })->orWhere(function ($scheduled): void {
                    $scheduled->where('status', 'scheduled')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                });
            })
            ->firstOrFail();

        return Inertia::render('Storefront/CmsPage', [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'excerpt' => $page->excerpt,
                'content' => $page->content,
                'sections' => $page->sections ?? [],
                'featuredImage' => $page->featured_image,
                'seo' => [
                    'title' => $page->meta_title ?: $page->title,
                    'description' => $page->meta_description ?: $page->excerpt,
                    'canonical' => $page->canonical_url ?: $this->canonicalUrl($page->slug),
                    'index' => $page->robots_index,
                    'follow' => $page->robots_follow,
                ],
                'publishedAt' => $page->published_at?->toISOString(),
                'updatedAt' => $page->updated_at?->toISOString(),
            ],
        ]);
    }

    private function canonicalUrl(string $slug): string
    {
        $reservedSlugs = [
            'backend', 'api', 'up', 'images', 'storage', 'build', 'assets', 'fonts', 'products', 'cart', 'checkout', 'account',
            'shop', 'pages', 'order-success', 'flash-sales', 'login', 'logout',
            'dashboard', 'content', 'seo', 'marketing', 'analytics', 'reports',
            'product-categories', 'brands', 'units', 'attributes', 'sales-crm',
            'b2b', 'pricing', 'customers', 'orders', 'sales-returns',
            'shipping-delivery', 'warranty-service', 'payments', 'procurement',
            'finance', 'inventory', 'locations', 'developer', 'settings',
            'users',
        ];

        return in_array(strtolower($slug), $reservedSlugs, true)
            ? route('storefront.pages.legacy', ['slug' => $slug])
            : route('storefront.pages.show', ['slug' => $slug]);
    }
}
