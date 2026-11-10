<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class AuthorizationService
{
    /**
     * Determine whether a user has a specific permission.
     *
     * A permission must exist and belong to a module before any role-based
     * or Developer authorization is considered. Module lifecycle restrictions
     * always apply, including to Developers.
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

        // Unknown or structurally invalid permissions must never be granted,
        // including to Developers.
        if ($permission === null || $permission->module === null) {
            return false;
        }

        $module = $permission->module;
        $moduleState = ! $module->is_active || $module->state === 'disabled'
            ? 'disabled'
            : ($module->state ?: 'enabled');

        if ($moduleState === 'disabled') {
            return false;
        }

        // Locked modules are read-only for every user, including Developers.
        if (
            $moduleState === 'locked'
            && preg_match('/\.(view|read)$/', $permissionSlug) !== 1
        ) {
            return false;
        }

        // Developer bypasses role-permission assignments only. It does not
        // bypass user status, permission existence, or module lifecycle rules.
        if ($this->isDeveloper($user)) {
            return true;
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
     * The immutable system role slug is used instead of the display name.
     */
    public function isDeveloper(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

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
