<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class AuthorizationService
{
    /**
     * Determine whether a user has a specific permission.
     *
     * Authorization requires:
     * - an active user
     * - an active role assigned to that user
     * - the requested permission assigned to that role
     * - an active module owning that permission
     *
     * Permission slugs are expected to be globally unique.
     */
    public function can(
        User $user,
        string $permissionSlug,
    ): bool {
        $permissionSlug = trim($permissionSlug);

        if ($permissionSlug === '') {
            return false;
        }

        if ($user->status !== 'active') {
            return false;
        }

        return Permission::query()
            ->where('permissions.slug', $permissionSlug)
            ->whereHas('module', function ($query): void {
                $query->where('modules.is_active', true);
            })
            ->whereHas('roles', function ($query) use ($user): void {
                $query
                    ->where('roles.is_active', true)
                    ->whereHas('users', function ($userQuery) use ($user): void {
                        $userQuery->where('users.id', $user->id);
                    });
            })
            ->exists();
    }

    /**
     * Determine whether a user has every given permission.
     *
     * Returns true when the permission list is empty.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    public function canAll(
        User $user,
        array $permissionSlugs,
    ): bool {
        foreach ($permissionSlugs as $permissionSlug) {
            if (! $this->can($user, $permissionSlug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether a user has at least one of the given permissions.
     *
     * Returns false when the permission list is empty.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    public function canAny(
        User $user,
        array $permissionSlugs,
    ): bool {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->can($user, $permissionSlug)) {
                return true;
            }
        }

        return false;
    }
}
