<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function cmsUser(): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'content'], [
            'name' => 'Content', 'description' => 'CMS management.', 'sort_order' => 130,
            'is_system' => true, 'is_active' => true,
        ]);
        $ids = [];
        foreach (['system.content.view', 'system.content.create', 'system.content.update', 'system.content.delete'] as $slug) {
            $ids[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => Str::headline($slug), 'description' => 'CMS permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'CMS Test Role', 'slug' => 'cms-test-'.Str::lower(Str::random(10)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'About Madina Scale', 'slug' => 'about-madina-scale', 'excerpt' => 'About our company.',
            'content' => 'Company information.', 'sections' => [], 'featured_image' => null,
            'status' => 'draft', 'published_at' => null, 'meta_title' => 'About Madina Scale',
            'meta_description' => 'Learn about our company.', 'meta_keywords' => null, 'canonical_url' => null,
            'robots_index' => true, 'robots_follow' => true,
        ], $overrides);
    }

    public function test_cms_page_can_be_created_updated_listed_and_deleted(): void
    {
        $user = $this->cmsUser();
        $this->actingAs($user)->postJson('/api/cms/pages', $this->payload())
            ->assertCreated()->assertJsonPath('data.slug', 'about-madina-scale');
        $page = CmsPage::query()->firstOrFail();
        $this->actingAs($user)->getJson('/api/cms/pages')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->putJson('/api/cms/pages/'.$page->id, $this->payload(['title' => 'About Us']))
            ->assertOk()->assertJsonPath('data.title', 'About Us');
        $this->actingAs($user)->deleteJson('/api/cms/pages/'.$page->id)->assertOk();
        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }

    public function test_published_page_cannot_be_deleted_until_unpublished(): void
    {
        $user = $this->cmsUser();
        $this->actingAs($user)->postJson('/api/cms/pages', $this->payload(['status' => 'published']))->assertCreated();
        $page = CmsPage::query()->firstOrFail();
        $this->actingAs($user)->deleteJson('/api/cms/pages/'.$page->id)->assertUnprocessable();
    }

    public function test_duplicate_slug_is_rejected_on_update(): void
    {
        $user = $this->cmsUser();
        $this->actingAs($user)->postJson('/api/cms/pages', $this->payload())->assertCreated();
        $this->actingAs($user)->postJson('/api/cms/pages', $this->payload(['title' => 'Contact', 'slug' => 'contact']))->assertCreated();
        $second = CmsPage::query()->where('slug', 'contact')->firstOrFail();
        $this->actingAs($user)->putJson('/api/cms/pages/'.$second->id, $this->payload(['title' => 'Contact', 'slug' => 'about-madina-scale']))
            ->assertUnprocessable()->assertJsonValidationErrors('slug');
    }
}
