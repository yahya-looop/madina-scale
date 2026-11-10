<?php

namespace Tests\Feature;

use App\Models\MarketingCampaign;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketingCampaignTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function campaignFixture(bool $grantViewPermission = true, string $status = 'active'): array
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'marketing'], [
            'name' => 'Marketing',
            'description' => 'Marketing test module.',
            'sort_order' => 70,
            'is_system' => true,
            'is_active' => true,
        ]);

        $permission = Permission::firstOrCreate(['slug' => 'system.marketing.view'], [
            'module_id' => $module->id,
            'name' => 'View marketing',
            'description' => 'Allows access to marketing campaign data.',
        ]);

        $role = Role::create([
            'name' => 'Marketing Test '.Str::random(8),
            'slug' => 'marketing-test-'.Str::lower(Str::random(10)),
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        if ($grantViewPermission) {
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        $campaign = MarketingCampaign::query()->create([
            'name' => 'Tracking Test Campaign',
            'channel' => 'onsite',
            'status' => $status,
            'budget' => 100,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'created_by' => $user->id,
        ]);

        return compact('user', 'campaign');
    }

    public function test_campaign_tracking_requires_marketing_view_permission(): void
    {
        ['user' => $user, 'campaign' => $campaign] = $this->campaignFixture(false);

        $this->actingAs($user)->postJson('/api/marketing/campaigns/'.$campaign->id.'/events', [
            'event_type' => 'impression',
        ])->assertForbidden();

        $this->assertDatabaseCount('marketing_campaign_events', 0);
    }

    public function test_active_campaign_accepts_only_supported_client_tracking_events(): void
    {
        ['user' => $user, 'campaign' => $campaign] = $this->campaignFixture();

        $this->actingAs($user)->postJson('/api/marketing/campaigns/'.$campaign->id.'/events', [
            'event_type' => 'impression',
            'source' => 'storefront',
            'visitor_key' => 'visitor-test-123',
            'metadata' => ['placement' => 'homepage-hero'],
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('marketing_campaign_events', [
            'campaign_id' => $campaign->id,
            'event_type' => 'impression',
            'source' => 'storefront',
            'visitor_key' => hash('sha256', 'visitor-test-123'),
        ]);

        $this->actingAs($user)->postJson('/api/marketing/campaigns/'.$campaign->id.'/events', [
            'event_type' => 'conversion',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('marketing_campaign_events', 1);
    }

    public function test_inactive_campaign_does_not_accept_tracking_events(): void
    {
        ['user' => $user, 'campaign' => $campaign] = $this->campaignFixture(true, 'paused');

        $this->actingAs($user)->postJson('/api/marketing/campaigns/'.$campaign->id.'/events', [
            'event_type' => 'click',
        ])->assertNotFound();

        $this->assertDatabaseCount('marketing_campaign_events', 0);
    }
}
