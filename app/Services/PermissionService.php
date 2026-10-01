<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Get permissions for management.
     *
     * @return Collection<int, Permission>
     */
    public function list(): Collection
    {
        return Permission::query()
            ->with('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a permission by its primary key.
     */
    public function find(int $id): Permission
    {
        return Permission::query()
            ->with('module')
            ->findOrFail($id);
    }

    /**
     * Create a permission.
     *
     * @param  array{
     *     module_id: int,
     *     name: string,
     *     slug: string,
     *     description?: string|null
     * }  $attributes
     */
    public function create(array $attributes): Permission
    {
        return DB::transaction(function () use ($attributes): Permission {
            return Permission::query()->create([
                'module_id' => $attributes['module_id'],
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
            ]);
        });
    }

    /**
     * Update a permission.
     *
     * @param  array{
     *     module_id: int,
     *     name: string,
     *     slug: string,
     *     description?: string|null
     * }  $attributes
     */
    public function update(
        Permission $permission,
        array $attributes,
    ): Permission {
        return DB::transaction(function () use (
            $permission,
            $attributes,
        ): Permission {
            $permission->update([
                'module_id' => $attributes['module_id'],
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
            ]);

            return $permission->refresh();
        });
    }
}
