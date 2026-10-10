<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserPageController extends Controller
{
    /**
     * Display the users index page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->can('system.users.view'), 403);

        return Inertia::render('Users/Index', [
            'users' => $this->users(),
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($user),
            'userName' => $user?->name ?? '',
            'userEmail' => $user?->email ?? '',
        ]);
    }

    /**
     * Display the user creation page.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user?->can('system.users.create'), 403);

        return Inertia::render('Users/Create', [
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($user),
            'userName' => $user?->name ?? '',
            'userEmail' => $user?->email ?? '',
        ]);
    }

    /**
     * Display a single user's details page.
     */
    public function show(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        abort_unless($currentUser?->can('system.users.view'), 403);

        return Inertia::render('Users/Show', [
            'user' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
            'capabilities' => $this->capabilities($currentUser),
            'userName' => $currentUser?->name ?? '',
            'userEmail' => $currentUser?->email ?? '',
        ]);
    }

    /**
     * Display the user edit page.
     *
     * The edit UI will be implemented in the next step.
     */
    public function edit(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        abort_unless($currentUser?->can('system.users.update'), 403);

        return Inertia::render('Users/Edit', [
            'user' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($currentUser),
            'userName' => $currentUser?->name ?? '',
            'userEmail' => $currentUser?->email ?? '',
        ]);
    }

    /**
     * Return active, non-system roles available to normal user management.
     */
    private function roles()
    {
        return Role::query()
            ->where('is_system', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'is_system',
                'is_active',
            ]);
    }

    /**
     * Return users required by the users index page.
     */
    private function users()
    {
        return User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'status',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Build the frontend capability contract.
     */
    private function capabilities(?User $user): array
    {
        if (! $user) {
            return [
                'canView' => false,
                'canCreate' => false,
                'canUpdate' => false,
                'isDeveloper' => false,
            ];
        }

        return [
            'canView' => $this->hasPermission($user, 'system.users.view'),
            'canCreate' => $this->hasPermission($user, 'system.users.create'),
            'canUpdate' => $this->hasPermission($user, 'system.users.update'),
            'isDeveloper' => $this->isDeveloper($user),
        ];
    }

    /**
     * Check whether a user has a permission.
     */
    private function hasPermission(User $user, string $permission): bool
    {
        if ($this->isDeveloper($user)) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        return false;
    }

    /**
     * Determine whether the user is the Developer/system administrator.
     */
    private function isDeveloper(User $user): bool
    {
        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole('developer');
        }

        return $user->roles()
            ->where(function ($query) {
                $query
                    ->where('slug', 'developer')
                    ->orWhere('name', 'Developer');
            })
            ->exists();
    }
}
