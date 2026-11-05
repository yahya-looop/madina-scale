<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class AuthorizationService
{
    /**
     * Determine whether a user has a specific permission.
     *
     * Module lifecycle restrictions always apply, including to Developers.
     * Developer access bypasses role assignments, not disabled or locked
     * module states.
     */
    public function can(
        User $user,
        string $permissionSlug,
    ): bool {
        $permissionSlug = trim($permissionSlug);

        if ($permissionSlug === '' || $user->status !== 'active') {
            return false;
        }

        $permission = Permission::query()
            ->with('module:id,state,is_active')
            ->where('slug', $permissionSlug)
            ->first();

        if ($permission?->module !== null) {
            $module = $permission->module;
            $moduleState = ! $module->is_active || $module->state === 'disabled'
                ? 'disabled'
                : ($module->state ?: 'enabled');

            if ($moduleState === 'disabled') {
                return false;
            }

            if ($moduleState === 'locked' && preg_match('/\.(view|read)$/', $permissionSlug) !== 1) {
                return false;
            }
        }

        if ($this->isDeveloper($user)) {
            return true;
        }

        if ($permission === null || $permission->module === null) {
            return false;
        }

        return $permission->roles()
            ->where('roles.is_active', true)
            ->whereHas('users', function ($query) use ($user): void {
                $query->where('users.id', $user->id);
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
