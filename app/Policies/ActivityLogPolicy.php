<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuthorizationService;

class ActivityLogPolicy
{
    public function __construct(
        protected AuthorizationService $authorization,
    ) {
    }

    /**
     * Determine whether the user may view activity logs.
     */
    public function viewAny(User $user): bool
    {
        return $this->authorization->can(
            $user,
            'system.activity_logs.view',
        );
    }

    /**
     * Determine whether the user may view an individual activity log.
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $this->authorization->can(
            $user,
            'system.activity_logs.view',
        );
    }

    /**
     * Activity logs are immutable.
     *
     * Therefore no policy methods for create/update/delete
     * are intentionally exposed.
     */
}
