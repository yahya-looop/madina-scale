<?php

use App\Models\CmsBanner;
use App\Models\CmsPage;
use Inertia\Testing\AssertableInertia as Assert;

it('renders only active homepage banners on the public storefront', function (): void {
    CmsBanner::create([
        'title' => 'Homepage promotion',
        'image_url' => 'https://example.com/promo.webp',
        'alt_text' => 'Promotion',
        'placement' => 'homepage',
        'status' => 'active',
        'sort_order' => 1,
    ]);

    CmsBanner::create([
        'title' => 'Draft banner',
        'image_url' => 'https://example.com/draft.webp',
        'placement' => 'homepage',
        'status' => 'draft',
        'sort_order' => 2,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Index')
            ->has('banners', 1)
            ->where('banners.0.title', 'Homepage promotion')
        );
});

it('publishes only live CMS pages in the XML sitemap', function (): void {
    CmsPage::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()->subDay()]);
    CmsPage::create(['title' => 'Draft', 'slug' => 'draft-page', 'status' => 'draft']);
    CmsPage::create(['title' => 'Future', 'slug' => 'future-page', 'status' => 'scheduled', 'published_at' => now()->addDay()]);
    CmsPage::create(['title' => 'Scheduled live', 'slug' => 'scheduled-live', 'status' => 'scheduled', 'published_at' => now()->subMinute()]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/about', false)
        ->assertSee('/scheduled-live', false)
        ->assertDontSee('/draft-page', false)
        ->assertDontSee('/future-page', false);
});

it('exposes a robots file that points to the sitemap', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('/sitemap.xml');
});

it('does not expose a scheduled CMS page before its publication time', function (): void {
    CmsPage::create(['title' => 'Future announcement', 'slug' => 'future-announcement', 'status' => 'scheduled', 'published_at' => now()->addDay()]);
    $this->get('/pages/future-announcement')->assertNotFound();
});

it('renders an already due scheduled CMS page publicly', function (): void {
    CmsPage::create([
        'title' => 'Published announcement',
        'slug' => 'published-announcement',
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'robots_index' => false,
        'robots_follow' => false,
    ]);

    $this->get('/pages/published-announcement')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/CmsPage')
            ->where('page.title', 'Published announcement')
            ->where('page.seo.index', false)
            ->where('page.seo.follow', false)
        );
});
