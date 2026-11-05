<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

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
     * }  $attributes
     */
    public function create(array $attributes, User $actor): Module
    {
        return DB::transaction(function () use ($attributes, $actor): Module {
            $module = Module::query()->create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'icon' => $attributes['icon'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? 0,
                'is_system' => false,
                'is_active' => true,
                'state' => 'enabled',
            ]);

            $this->activityLog->recordForUser(
                user: $actor,
                action: 'module.created',
                module: 'system',
                description: "Module {$module->slug} created.",
                subject: $module,
                newValues: $module->only(['name', 'slug', 'description', 'icon', 'sort_order', 'state', 'is_system']),
            );

            return $module;
        });
    }

    /**
     * Update a non-system module's descriptive configuration.
     *
     * Lifecycle state is managed separately through changeState().
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     description?: string|null,
     *     icon?: string|null,
     *     sort_order?: int,
     * }  $attributes
     */
    public function update(Module $module, array $attributes, User $actor): Module
    {
        if ($module->is_system) {
            abort(403);
        }

        return DB::transaction(function () use (
            $module,
            $attributes,
            $actor,
        ): Module {
            $oldValues = $module->only(['name', 'slug', 'description', 'icon', 'sort_order', 'state']);
            $module->update([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'description' => $attributes['description'] ?? null,
                'icon' => $attributes['icon'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? $module->sort_order,
            ]);

            $module->refresh();
            $this->activityLog->recordForUser(
                user: $actor,
                action: 'module.updated',
                module: 'system',
                description: "Module {$module->slug} configuration updated.",
                subject: $module,
                oldValues: $oldValues,
                newValues: $module->only(array_keys($oldValues)),
            );

            return $module;
        });
    }

    /**
     * Change the lifecycle state of a module and record the change.
     */
    public function changeState(Module $module, string $state, User $actor): Module
    {
        if (in_array($module->slug, ['system', 'modules', 'settings', 'dashboard'], true)) {
            abort(403, 'Core system modules are protected from state changes.');
        }

        if (! in_array($state, ['enabled', 'disabled', 'locked', 'hidden'], true)) {
            throw new \InvalidArgumentException('Unsupported module state.');
        }

        return DB::transaction(function () use ($module, $state, $actor): Module {
            $module->refresh();
            $oldState = $module->state;
            $expectedActive = $state !== 'disabled';

            if ($oldState === $state && $module->is_active === $expectedActive) {
                return $module;
            }

            $module->forceFill([
                'state' => $state,
                // Keep the legacy active flag aligned with the canonical lifecycle state.
                'is_active' => $expectedActive,
            ])->save();

            $this->activityLog->recordForUser(
                user: $actor,
                action: 'module.state_changed',
                module: 'system',
                description: "Module {$module->slug} state changed from {$oldState} to {$state}.",
                subject: $module,
                oldValues: ['state' => $oldState],
                newValues: ['state' => $state],
            );

            return $module->refresh();
        });
    }
}
