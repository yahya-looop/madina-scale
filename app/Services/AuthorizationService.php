<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class AuthorizationService
{
    /**
     * Determine whether a user has a specific permission.
     *
     * Authorization rules:
     * - the user must exist and be active
     * - an active Developer has supreme access
     * - otherwise, the permission must exist
     * - the permission's module must be active
     * - the permission must belong to an active role assigned to the user
     *
     * Developer access is intentionally evaluated before the permission
     * lookup so newly-created permissions automatically become available
     * to the Developer role without requiring a separate pivot record.
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

        /*
         * Developer is the supreme application role.
         *
         * This intentionally happens before checking whether the
         * requested permission currently exists. That means future
         * permissions automatically become available to Developer.
         */
        if ($this->isDeveloper($user)) {
            return true;
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
     * Determine whether the user is an active Developer.
     *
     * Developer is identified by its immutable system slug rather than
     * by the display name so the role can be renamed visually without
     * affecting authorization logic.
     */
    public function isDeveloper(User $user): bool
    {
        return $user->roles()
            ->where('roles.slug', 'developer')
            ->where('roles.is_system', true)
            ->where('roles.is_active', true)
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
