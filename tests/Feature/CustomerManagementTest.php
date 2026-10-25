<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
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
        $this->actingAs($user)->postJson('/api/customers', [
            'name' => 'Nusrat Jahan', 'phone' => '01712345678', 'email' => 'nusrat@example.com',
            'district' => 'Dhaka', 'division' => 'Dhaka', 'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.name', 'Nusrat Jahan');

        $id = Customer::query()->firstOrFail()->id;
        $this->actingAs($user)->getJson('/api/customers?search=Nusrat')->assertOk()->assertJsonPath('total', 1);
        $this->actingAs($user)->getJson('/api/customers/'.$id)->assertOk()->assertJsonPath('data.phone', '01712345678');
        $this->actingAs($user)->putJson('/api/customers/'.$id, [
            'name' => 'Nusrat Jahan Akter', 'phone' => '01712345678', 'email' => 'nusrat@example.com', 'status' => 'active',
        ])->assertOk()->assertJsonPath('data.name', 'Nusrat Jahan Akter');
        $this->actingAs($user)->deleteJson('/api/customers/'.$id)->assertOk();
    }

    public function test_duplicate_customer_phone_is_rejected(): void
    {
        $user = $this->customerUser();
        $payload = ['name' => 'Buyer One', 'phone' => '01812345678', 'status' => 'active'];
        $this->actingAs($user)->postJson('/api/customers', $payload)->assertCreated();
        $this->actingAs($user)->postJson('/api/customers', ['name' => 'Buyer Two', 'phone' => '01812345678', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }
}
