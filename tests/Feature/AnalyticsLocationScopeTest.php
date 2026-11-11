<?php

use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function analyticsScopedUser(): User
{
    $user = User::factory()->create(['status' => 'active']);
    $module = Module::create([
        'name' => 'Analytics Scope Test',
        'slug' => 'analytics-scope-'.uniqid(),
        'description' => 'Analytics scope regression test.',
        'sort_order' => 999,
        'is_system' => false,
        'is_active' => true,
    ]);
    $role = Role::create([
        'name' => 'Analytics Scope Role',
        'slug' => 'analytics-scope-role-'.uniqid(),
        'description' => 'Analytics scope regression role.',
        'is_system' => false,
        'is_active' => true,
    ]);
    $permission = Permission::create([
        'module_id' => $module->id,
        'name' => 'View Analytics',
        'slug' => 'system.analytics.view',
        'description' => 'Allows analytics access.',
    ]);
    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);

    return $user;
}

function analyticsTestLocation(string $name, string $code): Location
{
    return Location::create([
        'name' => $name,
        'code' => $code,
        'type' => 'branch',
        'is_active' => true,
        'is_primary' => false,
        'country_code' => 'BD',
    ]);
}

function analyticsTestOrder(Location $location, int $customerId, string $orderNumber, float $total): int
{
    return (int) DB::table('sales_orders')->insertGetId([
        'order_number' => $orderNumber,
        'status' => 'confirmed',
        'channel' => 'backoffice',
        'location_id' => $location->id,
        'customer_id' => $customerId,
        'customer_name' => 'Analytics Customer '.$customerId,
        'currency' => 'BDT',
        'subtotal' => $total,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $total,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('location restricted analytics only counts customers and conversions from viewable locations', function () {
    $user = analyticsScopedUser();
    $visibleLocation = analyticsTestLocation('Visible Branch', 'AN-VIS-'.uniqid());
    $hiddenLocation = analyticsTestLocation('Hidden Branch', 'AN-HID-'.uniqid());
    $user->locations()->attach($visibleLocation->id, ['access_level' => 'view']);

    $visibleCustomerId = (int) DB::table('customers')->insertGetId([
        'customer_code' => 'AN-CUS-'.uniqid(),
        'name' => 'Visible Customer',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $hiddenCustomerId = (int) DB::table('customers')->insertGetId([
        'customer_code' => 'AN-CUS-'.uniqid(),
        'name' => 'Hidden Customer',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $visibleOrderId = analyticsTestOrder($visibleLocation, $visibleCustomerId, 'AN-ORD-'.uniqid(), 1250);
    $hiddenOrderId = analyticsTestOrder($hiddenLocation, $hiddenCustomerId, 'AN-ORD-'.uniqid(), 9900);

    $campaignId = (int) DB::table('marketing_campaigns')->insertGetId([
        'name' => 'Analytics Scope Campaign',
        'channel' => 'onsite',
        'status' => 'active',
        'budget' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('marketing_campaign_events')->insert([
        [
            'campaign_id' => $campaignId,
            'event_type' => 'impression',
            'order_id' => null,
            'customer_id' => null,
            'value' => 0,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'campaign_id' => $campaignId,
            'event_type' => 'conversion',
            'order_id' => $visibleOrderId,
            'customer_id' => $visibleCustomerId,
            'value' => 1250,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'campaign_id' => $campaignId,
            'event_type' => 'conversion',
            'order_id' => $hiddenOrderId,
            'customer_id' => $hiddenCustomerId,
            'value' => 9900,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/backend/analytics/overview?from='.now()->toDateString().'&to='.now()->toDateString());

    $response->assertOk()
        ->assertJsonPath('scope.type', 'assigned_locations')
        ->assertJsonPath('scope.campaign_impressions_clicks_available', false)
        ->assertJsonPath('summary.customers', 1)
        ->assertJsonPath('summary.orders', 1)
        ->assertJsonPath('summary.revenue', 1250)
        ->assertJsonPath('summary.campaigns', 1)
        ->assertJsonPath('summary.campaign_conversions', 1)
        ->assertJsonPath('summary.tracked_campaign_conversion_value', 1250)
        ->assertJsonPath('summary.campaign_impressions', null)
        ->assertJsonPath('summary.campaign_clicks', null);
});

test('user cannot request analytics for a location they cannot view', function () {
    $user = analyticsScopedUser();
    $hiddenLocation = analyticsTestLocation('Unassigned Branch', 'AN-UNASSIGNED-'.uniqid());

    $this->actingAs($user)
        ->getJson('/api/v1/backend/analytics/overview?location_id='.$hiddenLocation->id)
        ->assertForbidden();
});
