<?php

namespace App\Services;

use App\Models\User;

class AuthorizationService
{
    /**
     * Determine whether a user has a specific permission.
     *
     * Permission slugs are expected to be globally unique.
     */
    public function can(
        User $user,
        string $permissionSlug,
    ): bool {
        return $user->roles()
            ->where('roles.is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionSlug): void {
                $query
                    ->where('permissions.slug', $permissionSlug)
                    ->whereHas('module', function ($moduleQuery): void {
                        $moduleQuery->where('modules.is_active', true);
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
