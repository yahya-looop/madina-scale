<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiNamespaceSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_api_is_available_in_versioned_namespace_and_legacy_paths_remain_compatible(): void
    {
        $this->getJson('/api/v1/backend/products')->assertUnauthorized();
        $this->getJson('/api/products')->assertUnauthorized();

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/backend/products')->assertForbidden();
        $this->actingAs($user)->getJson('/api/products')->assertForbidden();
    }

    public function test_storefront_api_uses_versioned_namespace_and_keeps_legacy_alias(): void
    {
        $this->getJson('/api/v1/storefront/flash-sales')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/public/flash-sales')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/storefront/cms/banners')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/public/cms/banners')->assertOk()->assertJsonPath('data', []);
    }

    public function test_webhook_namespace_is_versioned_without_removing_legacy_contract(): void
    {
        $payload = [
            'tracking_code' => 'TRACK-NOT-FOUND',
            'status' => 'in_transit',
        ];

        $this->postJson('/api/v1/webhooks/couriers/steadfast', $payload)->assertUnauthorized();
        $this->postJson('/api/webhooks/couriers/steadfast', $payload)->assertUnauthorized();
    }

    public function test_head_office_integration_namespace_remains_unchanged(): void
    {
        $this->getJson('/api/v1/head-office/health')->assertUnauthorized();
    }
}
