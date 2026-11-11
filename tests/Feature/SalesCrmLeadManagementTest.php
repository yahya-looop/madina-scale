<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryReservationPurpose;
use App\Models\InventoryStock;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesCrmLeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private function crmUser(
        array $slugs = [
            'system.crm.leads.view',
            'system.crm.leads.create',
            'system.crm.leads.update',
            'system.crm.leads.delete',
            'system.crm.activities.create',
        ]
    ): User {
        $user = User::factory()->create();

        $module = Module::firstOrCreate(
            ['slug' => 'crm'],
            [
                'name' => 'Sales CRM',
                'description' => 'Lead management.',
                'sort_order' => 45,
                'is_system' => true,
                'is_active' => true,
            ]
        );

        $permissionIds = [];

        foreach ($slugs as $slug) {
            $permissionIds[] = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'module_id' => $module->id,
                    'name' => Str::headline(str_replace('.', ' ', $slug)),
                    'description' => 'Sales CRM test permission.',
                ]
            )->id;
        }

        $role = Role::create([
            'name' => 'CRM Test Role',
            'slug' => 'crm-test-'.Str::lower(Str::random(12)),
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $role->permissions()->attach($permissionIds);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * Prepare the minimum inventory fixture required by the
     * convert-with-order endpoint.
     *
     * @return array{0: Location, 1: ProductVariant}
     */
    private function conversionOrderFixture(User $user): array
    {
        $location = Location::query()->create([
            'name' => 'CRM Conversion Test Store',
            'code' => 'CRM-'.Str::upper(Str::random(8)),
            'type' => 'store',
            'is_active' => true,
        ]);

        // Give the test user management access to this location.
        $user->locations()->attach($location->id, [
            'access_level' => 'manage',
        ]);

        // Use the project's existing inventory test helper.
        $product = \inventoryTestProduct('variable');

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'CRM Conversion Test Variant',
            'sku' => 'CRM-'.Str::upper(Str::random(8)),
            'selling_price' => 100,
            'cost_price' => 60,
            'status' => 'active',
            'is_default' => true,
        ]);

        InventoryStock::query()->create([
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        InventoryReservationPurpose::query()->firstOrCreate(
            ['key' => 'customer_order'],
            [
                'label' => 'Customer order',
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        return [$location, $variant];
    }

    public function test_lead_can_be_created_listed_viewed_and_updated(): void
    {
        $user = $this->crmUser();

        $this->actingAs($user)->postJson('/api/v1/backend/leads', [
            'name' => 'Rahim Traders',
            'phone' => '01712345678',
            'area' => 'Nawabpur',
            'district' => 'Dhaka',
            'source' => 'facebook',
            'customer_type' => 'b2b',
            'status' => 'new',
            'interest_level' => 'warm',
            'interested_product' => 'Commercial scale',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Rahim Traders')
            ->assertJsonPath('data.customer_type', 'b2b');

        $lead = Lead::query()->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/v1/backend/leads?search=Rahim')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->actingAs($user)
            ->getJson('/api/v1/backend/leads/'.$lead->id)
            ->assertOk()
            ->assertJsonPath('data.phone', '01712345678');

        $this->actingAs($user)->putJson('/api/v1/backend/leads/'.$lead->id, [
            'name' => 'Rahim Traders Ltd',
            'phone' => '01712345678',
            'source' => 'facebook',
            'customer_type' => 'b2b',
            'status' => 'qualified',
            'interest_level' => 'hot',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rahim Traders Ltd')
            ->assertJsonPath('data.status', 'qualified');
    }

    public function test_call_activity_is_kept_in_timeline_and_updates_follow_up(): void
    {
        $user = $this->crmUser();

        $this->actingAs($user)->postJson('/api/v1/backend/leads', [
            'name' => 'Prospect One',
            'phone' => '01812345678',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'new',
            'interest_level' => 'warm',
        ])->assertCreated();

        $lead = Lead::query()->firstOrFail();
        $followUp = now()->addDay()->toIso8601String();

        $this->actingAs($user)->postJson(
            '/api/v1/backend/leads/'.$lead->id.'/activities',
            [
                'activity_type' => 'call',
                'outcome' => 'callback_requested',
                'summary' => 'Customer asked to call tomorrow.',
                'occurred_at' => now()->toIso8601String(),
                'next_follow_up_at' => $followUp,
                'duration_seconds' => 80,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'callback_requested');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'contacting',
            'last_contact_outcome' => 'callback_requested',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'call',
            'summary' => 'Customer asked to call tomorrow.',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/backend/leads/'.$lead->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.activities');
    }

    public function test_lead_creator_is_initial_owner_and_cannot_reassign_to_themselves(): void
    {
        $user = $this->crmUser();

        $this->actingAs($user)->postJson('/api/v1/backend/leads', [
            'name' => 'Unassigned Prospect',
            'phone' => '01712345678',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'new',
            'interest_level' => 'warm',
        ])->assertCreated();

        $lead = Lead::query()->firstOrFail();

        $this->actingAs($user)->postJson(
            '/api/v1/backend/leads/'.$lead->id.'/assignment',
            ['assigned_to' => $user->id]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('lead_assignment_history', [
            'lead_id' => $lead->id,
            'to_user_id' => $user->id,
            'assigned_by' => $user->id,
        ]);
    }

    public function test_qualified_lead_can_be_converted_and_linked_to_customer_without_duplicates(): void
    {
        $user = $this->crmUser([
            'system.crm.leads.view',
            'system.crm.leads.create',
            'system.crm.leads.update',
            'system.crm.activities.create',
            'system.customers.create',
            'system.orders.create',
        ]);

        [$location, $variant] = $this->conversionOrderFixture($user);

        $this->actingAs($user)->postJson('/api/v1/backend/leads', [
            'name' => 'Conversion Prospect',
            'phone' => '01712345678',
            'email' => 'prospect@example.com',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'qualified',
            'interest_level' => 'hot',
        ])->assertCreated();

        $lead = Lead::query()->firstOrFail();

        $payload = [
            'location_id' => $location->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        // A qualified lead should create an order and link one customer.
        $this->actingAs($user)
            ->postJson('/api/v1/backend/leads/'.$lead->id.'/convert-with-order', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonPath('data.customer.name', 'Conversion Prospect');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'converted',
        ]);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('sales_orders', 1);

        $customer = Customer::query()->firstOrFail();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'customer_id' => $customer->id,
        ]);

        // A converted lead is immutable in the pipeline: it cannot be changed
        // back into an active lead after the first successful purchase.
        $this->actingAs($user)->putJson('/api/v1/backend/leads/'.$lead->id, [
            'name' => 'Conversion Prospect',
            'phone' => '01712345678',
            'email' => 'prospect@example.com',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'qualified',
            'interest_level' => 'hot',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'converted',
            'customer_id' => $customer->id,
        ]);

        // A new request key ensures this checks the converted-lead guard,
        // rather than relying on order idempotency.
        $retryPayload = [
            ...$payload,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($user)
            ->postJson(
                '/api/v1/backend/leads/'.$lead->id.'/convert-with-order',
                $retryPayload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lead');

        // Retrying conversion must not create another customer or order.
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('sales_orders', 1);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'customer_id' => $customer->id,
            'status' => 'converted',
        ]);
    }

    public function test_lead_conversion_requires_order_creation_permission(): void
    {
        $creator = $this->crmUser();

        $this->actingAs($creator)->postJson('/api/v1/backend/leads', [
            'name' => 'Permission Check Prospect',
            'phone' => '01912345678',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'qualified',
            'interest_level' => 'warm',
        ])->assertCreated();

        $lead = Lead::query()->firstOrFail();

        // Customer creation permission alone must not authorize order creation.
        $customerOnlyUser = $this->crmUser(['system.customers.create']);

        $this->actingAs($customerOnlyUser)
            ->postJson('/api/v1/backend/leads/'.$lead->id.'/convert-with-order', [])
            ->assertForbidden();
    }

    public function test_lead_endpoints_require_explicit_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/backend/leads')->assertForbidden();
        $this->actingAs($user)->get('/backend/sales-crm/leads')->assertForbidden();
    }

    private function leadAtLocation(User $creator, Location $location, string $name): Lead
    {
        return Lead::query()->create([
            'lead_code' => 'LEAD-'.Str::upper(Str::random(10)),
            'name' => $name,
            'phone' => '017'.random_int(10000000, 99999999),
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'new',
            'interest_level' => 'warm',
            'location_id' => $location->id,
            'assigned_to' => $creator->id,
            'owner_id' => $creator->id,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);
    }

    private function crmLocation(string $name): Location
    {
        return Location::query()->create([
            'name' => $name,
            'code' => 'CRM-'.Str::upper(Str::random(8)),
            'type' => 'store',
            'is_active' => true,
        ]);
    }

    public function test_lead_list_and_statistics_are_scoped_to_the_users_locations(): void
    {
        $user = $this->crmUser();
        $otherUser = User::factory()->create();
        $locationA = $this->crmLocation('CRM Location A');
        $locationB = $this->crmLocation('CRM Location B');

        $user->locations()->attach($locationA->id, ['access_level' => 'manage']);
        $otherUser->locations()->attach($locationB->id, ['access_level' => 'manage']);

        $visibleLead = $this->leadAtLocation($otherUser, $locationA, 'Visible Branch Lead');
        $hiddenLead = $this->leadAtLocation($user, $locationB, 'Hidden Branch Lead');
        $unlocatedLead = Lead::query()->create([
            'lead_code' => 'LEAD-'.Str::upper(Str::random(10)),
            'name' => 'My Unlocated Lead',
            'phone' => '018'.random_int(10000000, 99999999),
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'new',
            'interest_level' => 'warm',
            'assigned_to' => $user->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/backend/leads')->assertOk();

        $this->assertSame(2, $response->json('total'));
        $this->assertSame(2, $response->json('stats.total'));
        $this->assertSame(
            [$visibleLead->id, $unlocatedLead->id],
            collect($response->json('data'))->pluck('id')->sort()->values()->all(),
        );
        $this->assertNotContains($hiddenLead->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_user_cannot_view_or_mutate_a_lead_from_an_unassigned_location(): void
    {
        $user = $this->crmUser([
            'system.crm.leads.view',
            'system.crm.leads.create',
            'system.crm.leads.update',
            'system.crm.leads.delete',
            'system.crm.activities.create',
            'system.customers.create',
            'system.orders.create',
        ]);
        $otherUser = User::factory()->create();
        $locationA = $this->crmLocation('Assigned CRM Location');
        $locationB = $this->crmLocation('Unassigned CRM Location');
        $user->locations()->attach($locationA->id, ['access_level' => 'manage']);
        $otherUser->locations()->attach($locationB->id, ['access_level' => 'manage']);
        $lead = $this->leadAtLocation($otherUser, $locationB, 'Another Branch Lead');
        [, $variant] = $this->conversionOrderFixture($user);

        $this->actingAs($user)
            ->getJson('/api/v1/backend/leads/'.$lead->id)
            ->assertForbidden();

        $this->actingAs($user)->putJson('/api/v1/backend/leads/'.$lead->id, [
            'name' => 'Attempted Cross-Location Update',
            'phone' => '01712345678',
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'qualified',
            'interest_level' => 'hot',
        ])->assertForbidden();

        $this->actingAs($user)->postJson('/api/v1/backend/leads/'.$lead->id.'/activities', [
            'activity_type' => 'note',
            'summary' => 'Attempted cross-location activity.',
            'occurred_at' => now()->toIso8601String(),
        ])->assertForbidden();

        $this->actingAs($user)
            ->deleteJson('/api/v1/backend/leads/'.$lead->id)
            ->assertForbidden();

        $this->actingAs($user)->postJson('/api/v1/backend/leads/'.$lead->id.'/convert-with-order', [
            'location_id' => $locationA->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertForbidden();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'name' => 'Another Branch Lead',
            'location_id' => $locationB->id,
        ]);
        $this->assertDatabaseMissing('lead_activities', [
            'lead_id' => $lead->id,
            'summary' => 'Attempted cross-location activity.',
        ]);
    }

    public function test_location_manager_can_manage_leads_inside_their_location_but_not_another_location(): void
    {
        $manager = $this->crmUser();
        $manager->forceFill(['designation' => 'Branch Manager'])->save();
        $otherUser = User::factory()->create();
        $locationA = $this->crmLocation('Manager CRM Location');
        $locationB = $this->crmLocation('Other Manager CRM Location');
        $manager->locations()->attach($locationA->id, ['access_level' => 'view']);
        $otherUser->locations()->attach($locationB->id, ['access_level' => 'manage']);
        $inScopeLead = $this->leadAtLocation($otherUser, $locationA, 'Manager Scope Lead');
        $outOfScopeLead = $this->leadAtLocation($otherUser, $locationB, 'Outside Manager Scope Lead');

        $this->actingAs($manager)->putJson('/api/v1/backend/leads/'.$inScopeLead->id, [
            'name' => 'Manager Updated Lead',
            'phone' => $inScopeLead->phone,
            'source' => 'manual',
            'customer_type' => 'retail',
            'status' => 'qualified',
            'interest_level' => 'hot',
        ])->assertOk();

        $this->actingAs($manager)
            ->putJson('/api/v1/backend/leads/'.$outOfScopeLead->id, [
                'name' => 'Should Not Update',
                'phone' => $outOfScopeLead->phone,
                'source' => 'manual',
                'customer_type' => 'retail',
                'status' => 'qualified',
                'interest_level' => 'hot',
            ])->assertForbidden();
    }
}
