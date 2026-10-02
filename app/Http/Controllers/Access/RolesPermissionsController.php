<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Role;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RolesPermissionsController extends Controller
{
    public function __construct(
        protected AuthorizationService $authorization,
    ) {
    }

    /**
     * Display the role and permission management workspace.
     */
    public function __invoke(Request $request): InertiaResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $isDeveloper = $user->roles()
            ->where('roles.slug', 'developer')
            ->where('roles.is_active', true)
            ->exists();

        abort_unless(
            $isDeveloper || $this->authorization->can($user, 'system.roles.view'),
            403,
        );

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        $requestedRoleId = $request->integer('role');
        $selectedRole = $roles->firstWhere('id', $requestedRoleId)
            ?? $roles->first();

        $selectedPermissionIds = $selectedRole?->permissions()
            ->pluck('permissions.id')
            ->map(static fn ($id): int => (int) $id)
            ->all() ?? [];

        $selectedPermissionLookup = array_fill_keys($selectedPermissionIds, true);

        $modules = Module::query()
            ->with([
                'permissions' => static function ($query): void {
                    $query->orderBy('name');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function (Module $module) use ($selectedPermissionLookup): array {
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'slug' => $module->slug,
                    'icon' => $module->icon,
                    'isActive' => $module->is_active,
                    'isSystem' => $module->is_system,
                    'permissions' => $module->permissions->map(static function ($permission) use ($selectedPermissionLookup): array {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'description' => $permission->description,
                            'enabled' => isset($selectedPermissionLookup[$permission->id]),
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

        return Inertia::render('Access/RolesPermissions', [
            'roles' => $roles->map(static fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'isSystem' => $role->is_system,
                'isActive' => $role->is_active,
                'userCount' => (int) $role->users_count,
                'permissionCount' => (int) $role->permissions_count,
            ])->values()->all(),
            'modules' => $modules,
            'selectedRoleId' => $selectedRole?->id,
            'capabilities' => [
                'canCreateRole' => $isDeveloper || $this->authorization->can($user, 'system.roles.create'),
                'canUpdateRole' => $isDeveloper || $this->authorization->can($user, 'system.roles.update'),
                'canManagePermissions' => $isDeveloper || $this->authorization->can($user, 'system.permissions.manage'),
            ],
        ]);
    }
}
