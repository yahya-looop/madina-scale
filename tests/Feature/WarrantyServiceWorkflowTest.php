<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarrantyServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'open', array $grantedPermissions = [
        'system.warranty.view',
        'system.warranty.create',
        'system.warranty.update',
        'system.warranty.process',
    ]): array
    {
        $location = \inventoryTestLocation('warranty-'.strtolower(Str::random(8)));
        $user = User::factory()->create();
        $module = Module::firstOrCreate(['slug' => 'warranty'], [
            'name' => 'Warranty & Service',
            'description' => 'Warranty workflow tests.',
            'sort_order' => 90,
            'is_system' => true,
            'is_active' => true,
        ]);
        $permissions = collect([
            'system.warranty.view',
            'system.warranty.create',
            'system.warranty.update',
            'system.warranty.process',
        ])->map(fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], [
            'module_id' => $module->id,
            'name' => $slug,
            'description' => 'Warranty workflow test permission.',
        ]));
        $role = Role::create([
            'name' => 'Warranty Test '.Str::random(8),
            'slug' => 'warranty-test-'.Str::lower(Str::random(10)),
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->attach($permissions->whereIn('slug', $grantedPermissions)->pluck('id'));
        $user->roles()->attach($role->id);
        $user->locations()->attach($location->id, ['access_level' => 'manage']);
        $customer = Customer::query()->create([
            'customer_code' => 'WARR-'.Str::upper(Str::random(8)),
            'name' => 'Warranty Test Customer',
            'status' => 'active',
        ]);
        $ticketId = DB::table('service_tickets')->insertGetId([
            'ticket_number' => 'SRV-'.Str::upper(Str::random(10)),
            'warranty_id' => null,
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'assigned_to' => null,
            'status' => $status,
            'priority' => 'normal',
            'issue' => 'Device does not power on.',
            'diagnosis' => null,
            'resolution' => $status === 'resolved' ? 'Replaced faulty power module.' : null,
            'received_at' => now(),
            'resolved_at' => $status === 'resolved' ? now() : null,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'user' => $user,
            'location' => $location,
            'customer' => $customer,
            'ticket_id' => $ticketId,
        ];
    }

    public function test_ticket_can_move_through_a_valid_repair_lifecycle(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture();

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'diagnosing',
            'diagnosis' => 'Power input circuit requires inspection.',
        ])->assertOk()->assertJsonPath('data.status', 'diagnosing');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'in_repair',
            'diagnosis' => 'Faulty power module confirmed.',
        ])->assertOk()->assertJsonPath('data.status', 'in_repair');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'resolved',
            'resolution' => 'Replaced faulty power module and tested device.',
        ])->assertOk()->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('service_tickets', [
            'id' => $ticketId,
            'status' => 'resolved',
        ]);
        $this->assertNotNull(DB::table('service_tickets')->where('id', $ticketId)->value('resolved_at'));
    }

    public function test_ticket_cannot_skip_open_directly_to_closed(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture();

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'closed',
            'resolution' => 'Attempted direct closure.',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('service_tickets', ['id' => $ticketId, 'status' => 'open']);
    }

    public function test_resolved_ticket_can_be_closed_but_cannot_be_reopened(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('resolved');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'closed',
            'resolution' => 'Customer collected the repaired device.',
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'in_repair',
            'resolution' => 'Trying to reopen a closed ticket.',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('service_tickets', ['id' => $ticketId, 'status' => 'closed']);
    }

    public function test_resolving_or_closing_requires_a_resolution_note(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('in_repair');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'resolved',
        ])->assertUnprocessable()->assertJsonValidationErrors('resolution');

        $this->assertDatabaseHas('service_tickets', ['id' => $ticketId, 'status' => 'in_repair']);
    }

    public function test_rejected_ticket_is_terminal(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('rejected');

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'diagnosing',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('service_tickets', ['id' => $ticketId, 'status' => 'rejected']);
    }

    public function test_update_permission_alone_can_update_a_ticket(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('open', ['system.warranty.update']);

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'diagnosing',
            'diagnosis' => 'Update-only role started diagnosis.',
        ])->assertOk()->assertJsonPath('data.status', 'diagnosing');
    }

    public function test_process_permission_alone_can_update_a_ticket(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('open', ['system.warranty.process']);

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'diagnosing',
            'diagnosis' => 'Process-only role started diagnosis.',
        ])->assertOk()->assertJsonPath('data.status', 'diagnosing');
    }

    public function test_ticket_update_is_forbidden_without_update_or_process_permission(): void
    {
        ['user' => $user, 'ticket_id' => $ticketId] = $this->fixture('open', []);

        $this->actingAs($user)->putJson('/api/warranty-service/tickets/'.$ticketId, [
            'status' => 'diagnosing',
            'diagnosis' => 'Unauthorized update attempt.',
        ])->assertForbidden();

        $this->assertDatabaseHas('service_tickets', [
            'id' => $ticketId,
            'status' => 'open',
        ]);
    }
}
