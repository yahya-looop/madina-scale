<?php

use App\Models\CmsBanner;
use App\Models\CmsPage;

it('serves only active homepage banners through the storefront API', function (): void {
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

    $this->getJson('/api/v1/storefront/cms/banners?placement=homepage')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Homepage promotion');
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
    $this->getJson('/api/v1/storefront/cms/pages/future-announcement')->assertNotFound();
});

it('serves an already due scheduled CMS page through the Nuxt API', function (): void {
    CmsPage::create([
        'title' => 'Published announcement',
        'slug' => 'published-announcement',
        'status' => 'scheduled',
        'published_at' => now()->subMinute(),
        'robots_index' => false,
        'robots_follow' => false,
    ]);

    $this->getJson('/api/v1/storefront/cms/pages/published-announcement')
        ->assertOk()
        ->assertJsonPath('data.page.title', 'Published announcement')
        ->assertJsonPath('data.page.seo.canonical', url('/published-announcement'))
        ->assertJsonPath('data.page.seo.index', false)
        ->assertJsonPath('data.page.seo.follow', false);
});
