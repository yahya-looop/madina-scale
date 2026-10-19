<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

    /** Apply the user's viewable-location scope to a location_id query. */
    public function scopeViewable(Builder $query, User $user, string $column = 'location_id'): Builder
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
}
