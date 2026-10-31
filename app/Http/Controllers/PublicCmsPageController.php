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
                    'canonical' => $page->canonical_url ?: route('storefront.pages.show', ['slug' => $page->slug]),
                    'index' => $page->robots_index,
                    'follow' => $page->robots_follow,
                ],
                'publishedAt' => $page->published_at?->toISOString(),
                'updatedAt' => $page->updated_at?->toISOString(),
            ],
        ]);
    }
}
