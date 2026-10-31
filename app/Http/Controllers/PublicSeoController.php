<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Product;
use Illuminate\Http\Response;

class PublicSeoController extends Controller
{
    public function robots(): Response
    {
        $sitemap = route('seo.sitemap');

        return response(
            "User-agent: *\nAllow: /\nDisallow: /api/\nDisallow: /login\nDisallow: /dashboard\nDisallow: /content/\n\nSitemap: {$sitemap}\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('storefront.home'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('storefront.index'), 'lastmod' => now()->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.9'],
        ];

        CmsPage::query()
            ->whereIn('status', ['published', 'scheduled'])
            ->where('robots_index', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderBy('id')
            ->get(['slug', 'updated_at'])
            ->each(function (CmsPage $page) use (&$urls): void {
                $urls[] = [
                    'loc' => route('storefront.pages.show', ['slug' => $page->slug]),
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
                    'loc' => route('storefront.show', ['slug' => $product->slug]),
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
}
