<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
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

            $permissions = $user->roles()
                ->where('roles.is_active', true)
                ->with([
                    'permissions' => fn ($query) => $query
                        ->whereHas('module', function ($moduleQuery): void {
                            $moduleQuery->where('modules.is_active', true);
                        })
                        ->select([
                            'permissions.id',
                            'permissions.module_id',
                            'permissions.name',
                            'permissions.slug',
                        ]),
                ])
                ->get()
                ->flatMap(
                    fn ($role) => $role->permissions
                )
                ->pluck('slug')
                ->unique()
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'auth' => [
                'user' => $user,
                'is_developer' => $isDeveloper,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ];
    }
}
