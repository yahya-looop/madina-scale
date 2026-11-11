<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportUserWithPermissions(array $slugs): User
{
    $user = User::factory()->create(['status' => 'active']);
    $module = Module::create([
        'name' => 'Reports Test Module', 'slug' => 'reports-test-'.uniqid(),
        'description' => 'Reports feature test permissions.', 'sort_order' => 999,
        'is_system' => false, 'is_active' => true,
    ]);
    $role = Role::create([
        'name' => 'Reports Test Role', 'slug' => 'reports-role-'.uniqid(),
        'description' => 'Reports feature test role.', 'is_system' => false, 'is_active' => true,
    ]);
    foreach ($slugs as $slug) {
        $permission = Permission::create([
            'module_id' => $module->id, 'name' => 'Reports '.str($slug)->replace('.', ' ')->title(),
            'slug' => $slug, 'description' => 'Reports permission test.',
        ]);
        $role->permissions()->attach($permission->id);
    }
    $user->roles()->attach($role->id);

    return $user;
}

test('reports page requires report view permission', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user)->get('/backend/reports')->assertForbidden();
});

test('authorized user can load reports page and empty scoped report data', function () {
    $user = reportUserWithPermissions(['system.reports.view']);

    $this->actingAs($user)->get('/backend/reports')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Reports/Index')->has('locations')->where('canExport', false));

    $this->actingAs($user)->getJson('/api/v1/backend/reports/data?from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonStructure([
        'meta', 'kpis' => ['orders', 'gross_sales', 'posted_income', 'posted_expense'],
        'daily_sales', 'top_products', 'order_status', 'finance', 'inventory', 'delivery', 'returns', 'customers',
    ]);
});

test('report export requires dedicated export permission', function () {
    $user = reportUserWithPermissions(['system.reports.view']);
    $this->actingAs($user)->get('/api/v1/backend/reports/export?from='.now()->toDateString().'&to='.now()->toDateString().'&dataset=daily_sales')
        ->assertForbidden();
});

test('report export requires both view and export permissions', function () {
    $user = reportUserWithPermissions(['system.reports.export']);
    $this->actingAs($user)->get('/api/v1/backend/reports/export?from='.now()->toDateString().'&to='.now()->toDateString().'&dataset=daily_sales')
        ->assertForbidden();
});

test('authorized report user can export csv', function () {
    $user = reportUserWithPermissions(['system.reports.view', 'system.reports.export']);
    $response = $this->actingAs($user)->get('/api/v1/backend/reports/export?from='.now()->toDateString().'&to='.now()->toDateString().'&dataset=daily_sales');
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
