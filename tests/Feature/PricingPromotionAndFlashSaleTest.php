<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Permission;
use App\Models\PricingPromotion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PricingPromotionAndFlashSaleTest extends TestCase
{
    use RefreshDatabase;

    private function pricingUser(): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'pricing'], [
            'name' => 'Pricing & Promotions', 'description' => 'Pricing rules and promotions.',
            'sort_order' => 110, 'is_system' => true, 'is_active' => true,
        ]);
        $ids = [];
        foreach (['system.pricing.view', 'system.pricing.create', 'system.pricing.update', 'system.pricing.delete'] as $slug) {
            $ids[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id,
                'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'Pricing test permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'Pricing Test Role',
            'slug' => 'pricing-test-'.Str::lower(Str::random(10)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_promotion_rules_can_be_created_listed_updated_and_deleted(): void
    {
        $user = $this->pricingUser();
        $payload = [
            'name' => 'October Wholesale Offer', 'code' => 'wholesale10',
            'description' => 'A limited commercial offer.', 'discount_type' => 'percent',
            'discount_value' => 10, 'scope' => 'order', 'minimum_order_amount' => 5000,
            'minimum_quantity' => 1, 'usage_limit' => 100, 'per_customer_limit' => 2,
            'stackable' => false, 'publicly_visible' => true, 'status' => 'active',
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDays(5)->toIso8601String(),
        ];

        $this->actingAs($user)->postJson('/api/v1/backend/pricing/promotions', $payload)
            ->assertCreated()->assertJsonPath('data.name', 'October Wholesale Offer')
            ->assertJsonPath('data.code', 'WHOLESALE10');

        $promotion = PricingPromotion::firstOrFail();
        $this->actingAs($user)->getJson('/api/v1/backend/pricing/promotions?search=wholesale')
            ->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->putJson('/api/v1/backend/pricing/promotions/'.$promotion->id, array_replace($payload, [
            'name' => 'Updated Wholesale Offer', 'code' => 'wholesale15', 'discount_value' => 15,
        ]))->assertOk()->assertJsonPath('data.discount_value', '15.00');

        $this->actingAs($user)->deleteJson('/api/v1/backend/pricing/promotions/'.$promotion->id)->assertOk();
        $this->assertDatabaseMissing('pricing_promotions', ['id' => $promotion->id]);
    }

    public function test_percentage_discount_cannot_exceed_one_hundred(): void
    {
        $user = $this->pricingUser();
        $this->actingAs($user)->postJson('/api/v1/backend/pricing/promotions', [
            'name' => 'Invalid Discount', 'discount_type' => 'percent', 'discount_value' => 101,
            'scope' => 'order', 'status' => 'draft',
        ])->assertUnprocessable()->assertJsonValidationErrors('discount_value');
    }

    public function test_pricing_endpoints_require_permissions_and_public_flash_sales_are_read_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/backend/pricing/promotions')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/backend/pricing/promotions', [])->assertForbidden();

        $this->getJson('/api/public/flash-sales')->assertOk()->assertJsonPath('data', []);
    }

    public function test_coupon_codes_are_unique_without_case_sensitivity(): void
    {
        $user = $this->pricingUser();
        $payload = [
            'name' => 'First coupon', 'code' => 'case10', 'discount_type' => 'percent',
            'discount_value' => 10, 'scope' => 'order', 'status' => 'active',
        ];
        $this->actingAs($user)->postJson('/api/v1/backend/pricing/promotions', $payload)->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/backend/pricing/promotions', array_replace($payload, [
            'name' => 'Duplicate coupon', 'code' => 'CASE10',
        ]))->assertUnprocessable()->assertJsonValidationErrors('code');
    }
}
