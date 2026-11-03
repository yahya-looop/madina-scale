<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/** Enforces the per-location inventory permissions configured in location_user. */
class LocationInventoryAccessService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function canView(User $user, int $locationId): bool
    {
        return $this->canAccess($user, $locationId, ['view', 'receive', 'manage']);
    }

    public function canReceive(User $user, int $locationId): bool
    {
        return $this->canAccess($user, $locationId, ['receive', 'manage']);
    }

    public function canManage(User $user, int $locationId): bool
    {
        return $this->canAccess($user, $locationId, ['manage']);
    }

    /**
     * Return only active locations visible to the user, including the effective
     * location access level used by inventory operation forms.
     *
     * Global location managers can see all active locations. Other users can
     * only see locations explicitly assigned to them.
     *
     * @return Collection<int, array{id: int, name: string, code: string, type: string, is_active: bool, access_level: string}>
     */
    public function viewableLocations(User $user): Collection
    {
        if ($this->isGlobalManager($user)) {
            return Location::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type', 'is_active'])
                ->map(fn (Location $location): array => [
                    'id' => (int) $location->id,
                    'name' => $location->name,
                    'code' => $location->code,
                    'type' => $location->type,
                    'is_active' => (bool) $location->is_active,
                    'access_level' => $this->globalManagerAccessLevel(),
                ])
                ->values();
        }

        return $user->locations()
            ->where('locations.is_active', true)
            ->whereIn('location_user.access_level', ['view', 'receive', 'manage'])
            ->orderBy('locations.name')
            ->get(['locations.id', 'locations.name', 'locations.code', 'locations.type', 'locations.is_active'])
            ->map(fn (Location $location): array => [
                'id' => (int) $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'is_active' => (bool) $location->is_active,
                'access_level' => (string) data_get($location, 'pivot.access_level'),
            ])
            ->values();
    }

    /**
     * Apply the user's viewable-location scope to a location_id query.
     *
     * @template TModel of Model = Model
     *
     * @param  Builder<TModel>|QueryBuilder  $query
     * @return Builder<TModel>|QueryBuilder
     */
    public function scopeViewable(EloquentBuilder|QueryBuilder $query, User $user, string $column = 'location_id'): EloquentBuilder|QueryBuilder
    {
        if ($this->isGlobalManager($user)) {
            return $query;
        }

        return $query->whereIn($column, $user->locations()
            ->whereIn('location_user.access_level', ['view', 'receive', 'manage'])
            ->select('locations.id'));
    }

    public function assertView(User $user, int $locationId): void
    {
        abort_unless($this->canView($user, $locationId), 403, 'You do not have view access to this location.');
    }

    public function assertReceive(User $user, int $locationId): void
    {
        abort_unless($this->canReceive($user, $locationId), 403, 'You do not have receive access to this location.');
    }

    public function assertManage(User $user, int $locationId): void
    {
        abort_unless($this->canManage($user, $locationId), 403, 'You do not have manage access to this location.');
    }

    /**
     * @param  list<string>  $levels
     */
    private function canAccess(User $user, int $locationId, array $levels): bool
    {
        if ($this->isGlobalManager($user)) {
            return true;
        }

        return $user->locations()
            ->where('locations.id', $locationId)
            ->whereIn('location_user.access_level', $levels)
            ->exists();
    }

    private function isGlobalManager(User $user): bool
    {
        return $this->authorization->can($user, 'system.locations.manage');
    }

    private function globalManagerAccessLevel(): string
    {
        return 'manage';
    }
}
