<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesCrmLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private function crmUser(array $slugs = ['system.crm.leads.view', 'system.crm.leads.create', 'system.crm.leads.update', 'system.crm.leads.delete', 'system.crm.activities.create']): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'crm'], [
            'name' => 'Sales CRM', 'description' => 'Lead management.',
            'sort_order' => 45, 'is_system' => true, 'is_active' => true,
        ]);
        $permissionIds = [];
        foreach ($slugs as $slug) {
            $permissionIds[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'Sales CRM test permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'CRM Test Role', 'slug' => 'crm-test-'.Str::lower(Str::random(12)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($permissionIds);
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_lead_can_be_created_listed_viewed_and_updated(): void
    {
        $user = $this->crmUser();
        $this->actingAs($user)->postJson('/api/leads', [
            'name' => 'Rahim Traders', 'phone' => '01712345678', 'area' => 'Nawabpur',
            'district' => 'Dhaka', 'source' => 'facebook', 'customer_type' => 'b2b',
            'status' => 'new', 'interest_level' => 'warm', 'interested_product' => 'Commercial scale',
        ])->assertCreated()->assertJsonPath('data.name', 'Rahim Traders')->assertJsonPath('data.customer_type', 'b2b');

        $lead = Lead::query()->firstOrFail();
        $this->actingAs($user)->getJson('/api/leads?search=Rahim')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/leads/'.$lead->id)->assertOk()->assertJsonPath('data.phone', '01712345678');
        $this->actingAs($user)->putJson('/api/leads/'.$lead->id, [
            'name' => 'Rahim Traders Ltd', 'phone' => '01712345678', 'source' => 'facebook',
            'customer_type' => 'b2b', 'status' => 'qualified', 'interest_level' => 'hot',
        ])->assertOk()->assertJsonPath('data.name', 'Rahim Traders Ltd')->assertJsonPath('data.status', 'qualified');
    }

    public function test_call_activity_is_kept_in_timeline_and_updates_follow_up(): void
    {
        $user = $this->crmUser();
        $this->actingAs($user)->postJson('/api/leads', [
            'name' => 'Prospect One', 'phone' => '01812345678', 'source' => 'manual',
            'customer_type' => 'retail', 'status' => 'new', 'interest_level' => 'warm',
        ])->assertCreated();
        $lead = Lead::query()->firstOrFail();
        $followUp = now()->addDay()->toIso8601String();
        $this->actingAs($user)->postJson('/api/leads/'.$lead->id.'/activities', [
            'activity_type' => 'call', 'outcome' => 'callback_requested',
            'summary' => 'Customer asked to call tomorrow.', 'occurred_at' => now()->toIso8601String(),
            'next_follow_up_at' => $followUp, 'duration_seconds' => 80,
        ])->assertCreated()->assertJsonPath('data.outcome', 'callback_requested');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacting', 'last_contact_outcome' => 'callback_requested']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'activity_type' => 'call', 'summary' => 'Customer asked to call tomorrow.']);
        $this->actingAs($user)->getJson('/api/leads/'.$lead->id)->assertOk()->assertJsonCount(1, 'data.activities');
    }

    public function test_lead_endpoints_require_explicit_permissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/leads')->assertForbidden();
        $this->actingAs($user)->get('/sales-crm/leads')->assertForbidden();
    }
}
