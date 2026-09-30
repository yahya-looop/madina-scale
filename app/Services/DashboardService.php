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
                'canManageUsers' => $this->authorization->can(
                    $user,
                    'users.manage',
                ),

                'canManageRoles' => $this->authorization->can(
                    $user,
                    'roles.manage',
                ),

                'canViewReports' => $this->authorization->can(
                    $user,
                    'reports.view',
                ),
            ],
        ];
    }
}
