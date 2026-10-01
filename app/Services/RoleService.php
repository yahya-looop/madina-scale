<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * Get roles for management.
     *
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return Role::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a role by its primary key.
     */
    public function find(int $id): Role
    {
        return Role::query()->findOrFail($id);
    }

    /**
     * Create a role.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     description?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function create(array $attributes): Role
    {
        return DB::transaction(function () use ($attributes): Role {
            return Role::query()->create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'is_system' => false,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update a non-system role.
     *
     * System roles must never be modified through this service.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     description?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function update(Role $role, array $attributes): Role
    {
        if ($role->is_system) {
            abort(403);
        }

        return DB::transaction(function () use ($role, $attributes): Role {
            $role->update([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? $role->is_active,
            ]);

            return $role->refresh();
        });
    }

    /**
     * Synchronize permissions assigned to a non-system role.
     *
     * System roles must never have their permissions modified
     * through normal role management.
     *
     * @param  array<int, int>  $permissionIds
     * @return Collection<int, Permission>
     */
    public function syncPermissions(
        Role $role,
        array $permissionIds,
    ): Collection {
        if ($role->is_system) {
            abort(403);
        }

        return DB::transaction(function () use (
            $role,
            $permissionIds,
        ): Collection {
            $role->permissions()->sync($permissionIds);

            return $role->permissions()
                ->orderBy('permissions.name')
                ->get();
        });
    }
}
