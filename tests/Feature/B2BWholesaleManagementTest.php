<?php

namespace Tests\Feature;

use App\Models\B2BAccount;
use App\Models\B2BQuote;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2BWholesaleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function b2bUser(): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'b2b'], [
            'name' => 'B2B & Wholesale', 'description' => 'B2B management.', 'sort_order' => 100,
            'is_system' => true, 'is_active' => true,
        ]);
        $permissionIds = [];
        foreach (['system.b2b.view', 'system.b2b.create', 'system.b2b.update', 'system.b2b.delete'] as $slug) {
            $permissionIds[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'B2B management test permission.',
            ])->id;
        }
        $role = Role::create(['name' => 'B2B Test Role', 'slug' => 'b2b-test-'.Str::lower(Str::random(12)), 'description' => null, 'is_system' => false, 'is_active' => true]);
        $role->permissions()->attach($permissionIds);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function accountPayload(): array
    {
        return [
            'company_name' => 'North Star Wholesale', 'business_type' => 'distributor', 'tier' => 'gold',
            'status' => 'active', 'payment_terms' => 'net_30', 'credit_limit' => 250000,
            'default_discount_percent' => 5, 'contact_name' => 'Amin Rahman', 'email' => 'amin@northstar.example',
        ];
    }

    public function test_business_account_can_be_created_listed_viewed_updated_and_deleted_when_unreferenced(): void
    {
        $user = $this->b2bUser();
        $this->actingAs($user)->postJson('/api/b2b/accounts', $this->accountPayload())
            ->assertCreated()->assertJsonPath('data.company_name', 'North Star Wholesale')->assertJsonPath('data.tier', 'gold');
        $account = B2BAccount::query()->firstOrFail();
        $this->actingAs($user)->getJson('/api/b2b/accounts?search=North%20Star')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/b2b/accounts/'.$account->id)->assertOk()->assertJsonPath('data.account_code', $account->account_code);
        $this->actingAs($user)->putJson('/api/b2b/accounts/'.$account->id, array_replace($this->accountPayload(), ['company_name' => 'North Star Trading']))
            ->assertOk()->assertJsonPath('data.company_name', 'North Star Trading');
        $this->actingAs($user)->deleteJson('/api/b2b/accounts/'.$account->id)->assertOk();
    }

    public function test_quote_totals_are_calculated_server_side_and_quote_history_protects_account(): void
    {
        $user = $this->b2bUser();
        $this->actingAs($user)->postJson('/api/b2b/accounts', $this->accountPayload())->assertCreated();
        $account = B2BAccount::query()->firstOrFail();
        $response = $this->actingAs($user)->postJson('/api/b2b/quotes', [
            'b2b_account_id' => $account->id, 'currency' => 'BDT', 'discount_amount' => 100, 'tax_amount' => 50,
            'items' => [
                ['description' => 'Bulk product A', 'quantity' => 10, 'unit_price' => 100, 'discount_percent' => 10],
                ['description' => 'Bulk product B', 'quantity' => 2, 'unit_price' => 250],
            ],
        ])->assertCreated()->assertJsonPath('data.subtotal', '1400.00')->assertJsonPath('data.total', '1350.00');
        $quote = B2BQuote::query()->firstOrFail();
        $this->actingAs($user)->patchJson('/api/b2b/quotes/'.$quote->id.'/status', ['status' => 'sent'])->assertOk()->assertJsonPath('data.status', 'sent');
        $this->actingAs($user)->deleteJson('/api/b2b/accounts/'.$account->id)->assertUnprocessable()->assertJsonValidationErrors('account');
    }

    public function test_b2b_endpoints_require_the_b2b_permissions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/b2b/accounts')->assertForbidden();
        $this->actingAs($user)->postJson('/api/b2b/accounts', $this->accountPayload())->assertForbidden();
    }
}
