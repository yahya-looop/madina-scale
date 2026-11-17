<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Http\Response;

class PublicSeoController extends Controller
{
    private const RESERVED_CMS_SLUGS = [
        'backend', 'api', 'up', 'images', 'storage', 'build', 'assets', 'fonts',
        'products', 'cart', 'checkout', 'account', 'shop', 'pages', 'order-success',
        'flash-sales', 'login', 'logout', 'register', 'customer', 'dashboard',
        'content', 'seo', 'marketing', 'analytics', 'reports', 'product-categories',
        'brands', 'units', 'attributes', 'sales-crm', 'b2b', 'pricing', 'customers',
        'orders', 'sales-returns', 'shipping-delivery', 'warranty-service', 'payments',
        'procurement', 'finance', 'inventory', 'locations', 'developer', 'settings',
        'users',
    ];

    public function robots(): Response
    {
        $sitemap = route('seo.sitemap');

        return response(
            "User-agent: *\nAllow: /\nDisallow: /backend/\nDisallow: /api/\n\nSitemap: {$sitemap}\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/products'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.9'],
        ];

        CmsPage::query()
            ->where('robots_index', true)
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
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (CmsPage $page) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->cmsPageUrl($page->slug),
                    'lastmod' => ($page->updated_at ?? now())->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            });

        Product::query()
            ->where('status', 'active')
            ->where('visibility', 'visible')
            ->whereHas('category', fn ($query) => $query->where('status', 'active')->where('visibility', 'visible'))
            ->whereHas('unit', fn ($query) => $query->where('status', 'active'))
            ->whereHas('activeVariants')
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls): void {
                $urls[] = [
                    'loc' => url('/products/'.$product->slug),
                    'lastmod' => ($product->updated_at ?? now())->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>'.e($url['loc']).'</loc><lastmod>'.e($url['lastmod']).'</lastmod>';
            $xml .= '<changefreq>'.e($url['changefreq']).'</changefreq><priority>'.e($url['priority']).'</priority></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function cmsPageUrl(string $slug): string
    {
        return in_array(strtolower($slug), self::RESERVED_CMS_SLUGS, true)
            ? url('/pages/'.$slug)
            : url('/'.$slug);
    }
}
