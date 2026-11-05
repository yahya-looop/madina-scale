<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Services\AuthorizationService;
use App\Services\MenuRegistryService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $roles = [];
        $permissions = [];
        $isDeveloper = false;
        $navigation = [];
        $restrictedPermissions = [];

        if ($user) {
            $roles = $user->roles()
                ->where('roles.is_active', true)
                ->get([
                    'roles.id',
                    'roles.name',
                    'roles.slug',
                    'roles.is_system',
                ])
                ->map(fn ($role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_system' => $role->is_system,
                ])
                ->values()
                ->all();

            $isDeveloper = app(AuthorizationService::class)
                ->isDeveloper($user);

            if ($isDeveloper) {
                $restrictedPermissions = Permission::query()
                    ->with('module:id,state,is_active')
                    ->get(['id', 'module_id', 'slug'])
                    ->filter(function (Permission $permission): bool {
                        if ($permission->module === null) {
                            return false;
                        }

                        $state = ! $permission->module->is_active || $permission->module->state === 'disabled'
                            ? 'disabled'
                            : ($permission->module->state ?: 'enabled');

                        return $state === 'disabled'
                            || ($state === 'locked' && preg_match('/\.(view|read)$/', $permission->slug) !== 1);
                    })
                    ->pluck('slug')
                    ->values()
                    ->all();
            }

            $permissions = $user->roles()
                ->where('roles.is_active', true)
                ->with([
                    'permissions.module:id,state,is_active',
                ])
                ->get()
                ->flatMap(fn ($role) => $role->permissions)
                ->filter(function ($permission): bool {
                    if ($permission->module === null) {
                        return false;
                    }

                    $state = ! $permission->module->is_active || $permission->module->state === 'disabled'
                            ? 'disabled'
                            : ($permission->module->state ?: 'enabled');

                    return $state !== 'disabled'
                        && ($state !== 'locked' || preg_match('/\.(view|read)$/', $permission->slug) === 1);
                })
                ->pluck('slug')
                ->unique()
                ->values()
                ->all();

            $navigation = app(MenuRegistryService::class)->navigationFor($user, $permissions);
        }

        return [
            ...parent::share($request),

            'name' => app(SettingsService::class)->get('general.site_name', config('app.name')),

            'navigation' => $navigation,

            'auth' => [
                'user' => $user,
                'is_developer' => $isDeveloper,
                'roles' => $roles,
                'permissions' => $permissions,
                'restricted_permissions' => $restrictedPermissions,
            ],
        ];
    }
}
