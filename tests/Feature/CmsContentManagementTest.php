<?php

namespace Tests\Feature;

use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsContentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::factory()->create();
        $m = Module::firstOrCreate(['slug' => 'content'], ['name' => 'Content', 'description' => 'CMS', 'sort_order' => 130, 'is_system' => true, 'is_active' => true]);
        $ids = [];
        foreach (['view', 'create', 'update', 'delete'] as $a) {
            $ids[] = Permission::firstOrCreate(['slug' => "system.content.{$a}"], ['module_id' => $m->id, 'name' => "Content {$a}", 'description' => 'CMS'])->id;
        }
        $r = Role::create(['name' => 'CMS Role', 'slug' => 'cms-'.Str::lower(Str::random(12)), 'description' => null, 'is_system' => false, 'is_active' => true]);
        $r->permissions()->attach($ids);
        $u->roles()->attach($r->id);

        return $u;
    }

    public function test_media_upload_is_registered_and_cannot_be_deleted_when_used(): void
    {
        Storage::fake('public');
        $u = $this->user();
        $this->actingAs($u)->post('/api/cms/media-library', ['file' => UploadedFile::fake()->createWithContent('hero.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nWQAAAAASUVORK5CYII=')), 'alt_text' => 'Hero'])->assertCreated();
        $media = CmsMedia::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $page = CmsPage::create(['title' => 'Landing', 'slug' => 'landing', 'status' => 'draft', 'featured_image' => Storage::disk('public')->url($media->path)]);
        $this->actingAs($u)->deleteJson('/api/cms/media/'.$media->id)->assertUnprocessable();
    }

    public function test_banner_and_reusable_section_crud(): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson('/api/cms/banners', ['title' => 'Homepage hero', 'image_url' => 'https://example.com/hero.jpg', 'placement' => 'homepage', 'status' => 'active', 'sort_order' => 1])->assertCreated();
        $this->assertDatabaseHas('cms_banners', ['title' => 'Homepage hero', 'status' => 'active']);
        $this->actingAs($u)->postJson('/api/cms/sections', ['name' => 'Main hero', 'key' => 'main-hero', 'type' => 'hero', 'content' => ['heading' => 'Welcome'], 'is_active' => true])->assertCreated();
        $this->assertDatabaseHas('cms_content_sections', ['key' => 'main-hero']);
    }

    public function test_page_update_creates_revision_and_revision_can_be_restored(): void
    {
        $u = $this->user();
        $payload = ['title' => 'First title', 'slug' => 'revision-page', 'status' => 'draft', 'robots_index' => true, 'robots_follow' => true];
        $this->actingAs($u)->postJson('/api/cms/pages', $payload)->assertCreated();
        $page = CmsPage::firstOrFail();
        $this->actingAs($u)->putJson('/api/cms/pages/'.$page->id, array_merge($payload, ['title' => 'Second title']))->assertOk();
        $revision = $page->revisions()->firstOrFail();
        $this->actingAs($u)->postJson('/api/cms/pages/'.$page->id.'/revisions/'.$revision->id.'/restore')->assertOk()->assertJsonPath('data.title', 'First title');
    }
}
