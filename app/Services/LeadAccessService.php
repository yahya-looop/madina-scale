<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Enforces CRM lead visibility and mutation access across assigned locations.
 *
 * Users with the global lead-assignment or location-management permission,
 * and active Developers, retain cross-location access. Other users are limited
 * to active locations assigned to them; unlocated leads remain visible to their
 * creator, immutable owner, or current assignee.
 */
class LeadAccessService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeViewable(Builder $query, User $user): Builder
    {
        if ($this->hasGlobalAccess($user)) {
            return $query;
        }

        $locationIds = $user->locations()
            ->where('locations.is_active', true)
            ->select('locations.id');

        return $query->where(function (Builder $scope) use ($locationIds, $user): void {
            $scope->whereIn('location_id', $locationIds)
                ->orWhere(function (Builder $unlocated) use ($user): void {
                    $unlocated->whereNull('location_id')
                        ->where(function (Builder $ownership) use ($user): void {
                            $ownership->where('created_by', $user->id)
                                ->orWhere('owner_id', $user->id)
                                ->orWhere('assigned_to', $user->id);
                        });
                });
        });
    }

    public function assertView(User $user, Lead $lead): void
    {
        abort_unless($this->canView($user, $lead), 403, 'You do not have access to this lead.');
    }

    public function assertManage(User $user, Lead $lead): void
    {
        if ($this->hasGlobalAccess($user)) {
            return;
        }

        abort_unless($this->canView($user, $lead), 403, 'You do not have access to this lead.');

        $isOwnerOrCreator = (int) $lead->assigned_to === (int) $user->id
            || (int) $lead->owner_id === (int) $user->id
            || (int) $lead->created_by === (int) $user->id;
        $isLocationManager = $lead->location_id !== null && $this->isManagerOrSupervisor($user);

        abort_unless(
            $isOwnerOrCreator || $isLocationManager,
            403,
            'Only the lead owner or a location manager can modify this lead.',
        );
    }

    private function canView(User $user, Lead $lead): bool
    {
        if ($this->hasGlobalAccess($user)) {
            return true;
        }

        if ($lead->location_id !== null) {
            return $user->locations()
                ->where('locations.id', $lead->location_id)
                ->where('locations.is_active', true)
                ->exists();
        }

        return (int) $lead->created_by === (int) $user->id
            || (int) $lead->owner_id === (int) $user->id
            || (int) $lead->assigned_to === (int) $user->id;
    }

    private function hasGlobalAccess(User $user): bool
    {
        return $this->authorization->isDeveloper($user)
            || $this->authorization->can($user, 'system.crm.leads.assign')
            || $this->authorization->can($user, 'system.locations.manage');
    }

    private function isManagerOrSupervisor(User $user): bool
    {
        $designation = Str::lower(trim((string) $user->designation));

        return in_array($designation, [
            'manager',
            'supervisor',
            'sales manager',
            'sales supervisor',
            'branch manager',
            'team leader',
        ], true);
    }
}
