<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    /**
     * Get modules for management.
     *
     * Modules are ordered by their configured sort order,
     * followed by name for deterministic output.
     *
     * @return Collection<int, Module>
     */
    public function list(): Collection
    {
        return Module::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a module by its primary key.
     */
    public function find(int $id): Module
    {
        return Module::query()->findOrFail($id);
    }

    /**
     * Create a new non-system module.
     *
     * System modules are reserved for application-managed
     * infrastructure and must never be created through
     * normal module management.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     description?: string|null,
     *     icon?: string|null,
     *     sort_order?: int,
     *     is_active?: bool
     * }  $attributes
     */
    public function create(array $attributes): Module
    {
        return DB::transaction(function () use ($attributes): Module {
            return Module::query()->create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'icon' => $attributes['icon'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? 0,
                'is_system' => false,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Update a non-system module.
     *
     * System modules must never be modified through this service.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     description?: string|null,
     *     icon?: string|null,
     *     sort_order?: int,
     *     is_active?: bool
     * }  $attributes
     */
    public function update(Module $module, array $attributes): Module
    {
        if ($module->is_system) {
            abort(403);
        }

        return DB::transaction(function () use (
            $module,
            $attributes,
        ): Module {
            $module->update([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'icon' => $attributes['icon'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? $module->sort_order,
                'is_active' => $attributes['is_active'] ?? $module->is_active,
            ]);

            return $module->refresh();
        });
    }
}
