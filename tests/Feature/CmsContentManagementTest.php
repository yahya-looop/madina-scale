<?php

namespace Tests\Feature;

use App\Models\CmsContentSection;
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

function createDimensionTestPng(int $width, int $height): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    };

    $row = "\x00".str_repeat("\x00", $width * 3);
    $imageData = gzcompress(str_repeat($row, $height));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('N2C5', $width, $height, 8, 2, 0, 0, 0))
        .$chunk('IDAT', $imageData)
        .$chunk('IEND', '');
}

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
        $this->actingAs($u)->post('/api/v1/backend/cms/media-library', ['file' => UploadedFile::fake()->createWithContent('hero.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nWQAAAAASUVORK5CYII=')), 'alt_text' => 'Hero'])->assertCreated();
        $media = CmsMedia::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $page = CmsPage::create(['title' => 'Landing', 'slug' => 'landing', 'status' => 'draft', 'featured_image' => Storage::disk('public')->url($media->path)]);
        $this->actingAs($u)->deleteJson('/api/v1/backend/cms/media/'.$media->id)->assertUnprocessable();
    }

    public function test_banner_and_reusable_section_crud(): void
    {
        $u = $this->user();
        $this->actingAs($u)->postJson('/api/v1/backend/cms/banners', ['title' => 'Homepage hero', 'image_url' => 'https://example.com/hero.jpg', 'placement' => 'homepage', 'status' => 'active', 'sort_order' => 1])->assertCreated();
        $this->assertDatabaseHas('cms_banners', ['title' => 'Homepage hero', 'status' => 'active']);
        $this->actingAs($u)->postJson('/api/v1/backend/cms/sections', ['name' => 'Main hero', 'key' => 'main-hero', 'type' => 'hero', 'content' => ['heading' => 'Welcome'], 'is_active' => true])->assertCreated();
        $this->assertDatabaseHas('cms_content_sections', ['key' => 'main-hero']);
    }

    public function test_page_update_creates_revision_and_revision_can_be_restored(): void
    {
        $u = $this->user();
        $payload = ['title' => 'First title', 'slug' => 'revision-page', 'status' => 'draft', 'robots_index' => true, 'robots_follow' => true];
        $this->actingAs($u)->postJson('/api/v1/backend/cms/pages', $payload)->assertCreated();
        $page = CmsPage::firstOrFail();
        $this->actingAs($u)->putJson('/api/v1/backend/cms/pages/'.$page->id, array_merge($payload, ['title' => 'Second title']))->assertOk();
        $revision = $page->revisions()->firstOrFail();
        $this->actingAs($u)->postJson('/api/v1/backend/cms/pages/'.$page->id.'/revisions/'.$revision->id.'/restore')->assertOk()->assertJsonPath('data.title', 'First title');
    }

    public function test_media_upload_rejects_images_with_excessive_dimensions(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $this->actingAs($user)
            ->post('/api/v1/backend/cms/media-library', [
                'file' => UploadedFile::fake()->createWithContent('oversized.png', createDimensionTestPng(5001, 1)),
                'alt_text' => 'Oversized image',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('cms_media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('cms/media'));
    }

    public function test_media_delete_rejects_unsafe_stored_paths_without_touching_files(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $unsafePath = 'cms/media/../../private/secret.png';
        Storage::disk('public')->put('private/secret.png', 'keep-me');
        $media = CmsMedia::create([
            'disk' => 'public',
            'path' => $unsafePath,
            'original_name' => 'secret.png',
            'mime_type' => 'image/png',
            'size' => 7,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/backend/cms/media/'.$media->id)
            ->assertNotFound();

        $this->assertDatabaseHas('cms_media', ['id' => $media->id]);
        Storage::disk('public')->assertExists('private/secret.png');
    }

    public function test_page_image_upload_is_registered_in_the_media_library(): void
    {
        Storage::fake('public');
        $user = $this->user();

        $response = $this->actingAs($user)->post('/api/v1/backend/cms/media', [
            'file' => UploadedFile::fake()->createWithContent(
                'page-hero.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/nWQAAAAASUVORK5CYII='),
            ),
        ])->assertCreated();

        $path = $response->json('data.path');
        $media = CmsMedia::query()->where('path', $path)->firstOrFail();

        $this->assertSame($user->id, $media->uploaded_by);
        $this->assertSame('page-hero.png', $media->original_name);
        Storage::disk('public')->assertExists($path);
    }

    public function test_media_cannot_be_deleted_while_referenced_by_a_page_revision_or_reusable_section(): void
    {
        Storage::fake('public');
        $user = $this->user();
        $path = 'cms/media/revision-hero.png';
        $url = Storage::disk('public')->url($path);
        Storage::disk('public')->put($path, 'image-content');
        $media = CmsMedia::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'revision-hero.png',
            'mime_type' => 'image/png',
            'size' => 13,
            'uploaded_by' => $user->id,
        ]);

        $page = CmsPage::create([
            'title' => 'Revision image',
            'slug' => 'revision-image',
            'status' => 'draft',
            'featured_image' => $url,
        ]);
        $page->revisions()->create([
            'version' => 1,
            'snapshot' => ['featured_image' => $url],
            'created_by' => $user->id,
        ]);
        $page->forceFill(['featured_image' => null])->save();

        CmsContentSection::create([
            'name' => 'Image section',
            'key' => 'image-section',
            'type' => 'image_text',
            'content' => ['image' => ['src' => $path]],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/backend/cms/media/'.$media->id)
            ->assertUnprocessable();

        $this->assertDatabaseHas('cms_media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($path);
    }
}
