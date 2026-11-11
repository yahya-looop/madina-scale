<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function customerUser(): User
    {
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'customers'], [
            'name' => 'Customers', 'description' => 'Customer management.',
            'sort_order' => 40, 'is_system' => true, 'is_active' => true,
        ]);
        $permissions = [];
        foreach (['system.customers.view', 'system.customers.create', 'system.customers.update', 'system.customers.delete'] as $slug) {
            $permissions[] = Permission::firstOrCreate(['slug' => $slug], [
                'module_id' => $module->id, 'name' => Str::headline(str_replace('.', ' ', $slug)),
                'description' => 'Customer management test permission.',
            ])->id;
        }
        $role = Role::create([
            'name' => 'Customer Test Role', 'slug' => 'customer-test-'.Str::lower(Str::random(12)),
            'description' => null, 'is_system' => false, 'is_active' => true,
        ]);
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_customer_can_be_created_listed_viewed_updated_and_deleted_without_orders(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user)->postJson('/api/v1/backend/customers', [
            'name' => 'Nusrat Jahan', 'phone' => '01712345678', 'email' => 'nusrat@example.com',
            'district' => 'Dhaka', 'division' => 'Dhaka', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.name', 'Nusrat Jahan');

        $id = Customer::query()->firstOrFail()->id;
        $this->actingAs($user)->getJson('/api/v1/backend/customers?search=Nusrat')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/v1/backend/customers/'.$id)->assertOk()->assertJsonPath('data.phone', '01712345678');
        $this->actingAs($user)->putJson('/api/v1/backend/customers/'.$id, [
            'name' => 'Nusrat Jahan Akter', 'phone' => '01712345678', 'email' => 'nusrat@example.com', 'status' => 'active',
        ])->assertOk()->assertJsonPath('data.name', 'Nusrat Jahan Akter');
        $this->actingAs($user)->deleteJson('/api/v1/backend/customers/'.$id)->assertOk();
    }

    public function test_duplicate_customer_phone_is_rejected(): void
    {
        $user = $this->customerUser();
        $payload = ['name' => 'Buyer One', 'phone' => '01812345678', 'status' => 'active'];
        $this->actingAs($user)->postJson('/api/v1/backend/customers', $payload)->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/backend/customers', ['name' => 'Buyer Two', 'phone' => '01812345678', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_customer_duplicate_phone_is_rejected_after_normalization(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user)->postJson('/api/v1/backend/customers', [
            'name' => 'Buyer One', 'phone' => '+880 1712-345678', 'status' => 'active',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/backend/customers', [
            'name' => 'Buyer Two', 'phone' => '8801712345678', 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_customer_duplicate_email_is_rejected_case_insensitively(): void
    {
        $user = $this->customerUser();
        $this->actingAs($user)->postJson('/api/v1/backend/customers', [
            'name' => 'Buyer One', 'email' => 'Buyer@Example.com', 'status' => 'active',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/backend/customers', [
            'name' => 'Buyer Two', 'email' => 'buyer@example.com', 'status' => 'active',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_customer_order_history_and_lifetime_value_are_scoped_to_assigned_locations(): void
    {
        $user = $this->customerUser();
        $visibleLocation = Location::query()->create([
            'name' => 'Visible Customer Branch',
            'code' => 'CUS-VIS-'.Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $hiddenLocation = Location::query()->create([
            'name' => 'Hidden Customer Branch',
            'code' => 'CUS-HID-'.Str::upper(Str::random(5)),
            'type' => 'branch',
            'is_active' => true,
        ]);
        $user->locations()->attach($visibleLocation->id, ['access_level' => 'view']);

        $customer = Customer::query()->create([
            'customer_code' => 'CUS-'.Str::upper(Str::random(10)),
            'name' => 'Scoped Customer',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => 'active',
        ]);

        $visibleOrder = SalesOrder::query()->create([
            'order_number' => 'SO-VIS-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'channel' => 'backoffice',
            'location_id' => $visibleLocation->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'currency' => 'BDT',
            'subtotal' => '125.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '125.00',
        ]);
        $hiddenOrder = SalesOrder::query()->create([
            'order_number' => 'SO-HID-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'channel' => 'backoffice',
            'location_id' => $hiddenLocation->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'currency' => 'BDT',
            'subtotal' => '875.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '875.00',
        ]);

        $showResponse = $this->actingAs($user)
            ->getJson('/api/v1/backend/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('data.orders.0.id', $visibleOrder->id)
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.lifetime_value', '125.00');

        $shownOrderIds = collect($showResponse->json('data.orders'))->pluck('id')->map(fn ($id): int => (int) $id);
        $this->assertNotContains($hiddenOrder->id, $shownOrderIds->all());

        $indexResponse = $this->getJson('/api/v1/backend/customers?search=Scoped%20Customer')->assertOk();
        $listedCustomer = collect($indexResponse->json('data'))->firstWhere('id', $customer->id);

        $this->assertSame(1, $listedCustomer['orders_count']);
    }
}
