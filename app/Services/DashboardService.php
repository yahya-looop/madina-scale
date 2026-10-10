<?php

namespace App\Services;

use App\Models\User;

class DashboardService
{
    public function __construct(
        protected AuthorizationService $authorization,
    ) {}

    /**
     * Build the data required by the ERP dashboard.
     *
     * This service intentionally contains no presentation logic.
     *
     * @return array<string, mixed>
     */
    public function data(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],

            'roles' => $user->roles()
                ->where('roles.is_active', true)
                ->orderBy('roles.name')
                ->get([
                    'roles.id',
                    'roles.name',
                    'roles.slug',
                ]),

            'capabilities' => [
                // Management capabilities require at least one real
                // management permission; view-only access is not enough.
                'canManageUsers' => $this->authorization->canAny($user, [
                    'system.users.create',
                    'system.users.update',
                    'system.users.deactivate',
                    'system.users.manage_roles',
                ]),

                'canManageRoles' => $this->authorization->canAny($user, [
                    'system.roles.create',
                    'system.roles.update',
                    'system.roles.delete',
                    'system.roles.manage_permissions',
                ]),

                'canViewReports' => $this->authorization->can(
                    $user,
                    'system.reports.view',
                ),
            ],
        ];
    }
}
