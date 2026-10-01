<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SystemAuthorizationSeeder extends Seeder
{
    /**
     * Seed the application's core authorization structure.
     *
     * The seeder is intentionally idempotent so it can be executed
     * repeatedly without creating duplicate system records.
     */
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'description' => 'Core application dashboard capabilities.',
                'icon' => 'dashboard',
                'sort_order' => 0,
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'description' => 'Role management and role authorization capabilities.',
                'icon' => 'shield',
                'sort_order' => 10,
            ],
            [
                'name' => 'Permissions',
                'slug' => 'permissions',
                'description' => 'Permission management and authorization configuration.',
                'icon' => 'key',
                'sort_order' => 20,
            ],
            [
                'name' => 'Modules',
                'slug' => 'modules',
                'description' => 'Application module management and configuration.',
                'icon' => 'blocks',
                'sort_order' => 30,
            ],
        ];

        $moduleModels = [];

        foreach ($modules as $module) {
            $moduleModels[$module['slug']] = Module::updateOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }

        $permissions = [
            [
                'module' => 'dashboard',
                'name' => 'View Dashboard',
                'slug' => 'system.dashboard.view',
                'description' => 'View the application dashboard.',
            ],

            [
                'module' => 'roles',
                'name' => 'View Roles',
                'slug' => 'system.roles.view',
                'description' => 'View system roles.',
            ],
            [
                'module' => 'roles',
                'name' => 'Create Roles',
                'slug' => 'system.roles.create',
                'description' => 'Create system roles.',
            ],
            [
                'module' => 'roles',
                'name' => 'Update Roles',
                'slug' => 'system.roles.update',
                'description' => 'Update non-system roles.',
            ],
            [
                'module' => 'roles',
                'name' => 'Delete Roles',
                'slug' => 'system.roles.delete',
                'description' => 'Delete eligible non-system roles.',
            ],
            [
                'module' => 'roles',
                'name' => 'Manage Role Permissions',
                'slug' => 'system.roles.manage_permissions',
                'description' => 'Assign and revoke permissions from roles.',
            ],

            [
                'module' => 'permissions',
                'name' => 'View Permissions',
                'slug' => 'system.permissions.view',
                'description' => 'View system permissions.',
            ],
            [
                'module' => 'permissions',
                'name' => 'Create Permissions',
                'slug' => 'system.permissions.create',
                'description' => 'Create system permissions.',
            ],
            [
                'module' => 'permissions',
                'name' => 'Update Permissions',
                'slug' => 'system.permissions.update',
                'description' => 'Update eligible permissions.',
            ],
            [
                'module' => 'permissions',
                'name' => 'Delete Permissions',
                'slug' => 'system.permissions.delete',
                'description' => 'Delete eligible permissions.',
            ],

            [
                'module' => 'modules',
                'name' => 'View Modules',
                'slug' => 'system.modules.view',
                'description' => 'View application modules.',
            ],
            [
                'module' => 'modules',
                'name' => 'Create Modules',
                'slug' => 'system.modules.create',
                'description' => 'Create application modules.',
            ],
            [
                'module' => 'modules',
                'name' => 'Update Modules',
                'slug' => 'system.modules.update',
                'description' => 'Update eligible non-system modules.',
            ],
            [
                'module' => 'modules',
                'name' => 'Delete Modules',
                'slug' => 'system.modules.delete',
                'description' => 'Delete eligible non-system modules.',
            ],
        ];

        $permissionModels = [];

        foreach ($permissions as $permission) {
            $permissionModels[$permission['slug']] = Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'module_id' => $moduleModels[$permission['module']]->id,
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                ],
            );
        }

        $developerRole = Role::updateOrCreate(
            ['slug' => 'developer'],
            [
                'name' => 'Developer',
                'description' => 'System-level developer role with highest administrative trust.',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $developerRole->permissions()->sync(
            collect($permissionModels)
                ->pluck('id')
                ->values()
                ->all(),
        );
    }
}
