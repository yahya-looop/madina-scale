<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountSecurityService
{
    private const DEFAULT_MAX_FAILED_ATTEMPTS = 5;

    private const DEFAULT_LOCK_MINUTES = 15;

    /**
     * Determine whether the user is currently allowed to authenticate.
     */
    public function canAuthenticate(User $user): bool
    {
        if ($user->status !== 'active') {
            return false;
        }

        return ! $this->isLocked($user);
    }

    /**
     * Determine whether the user's account is currently locked.
     */
    public function isLocked(User $user): bool
    {
        if ($user->locked_until === null) {
            return false;
        }

        return $user->locked_until->isFuture();
    }

    /**
     * Record a failed authentication attempt without losing concurrent updates.
     */
    public function recordFailedLogin(
        User $user,
        ?int $maxAttempts = null,
        ?int $lockMinutes = null,
    ): void {
        $maxAttempts ??= self::DEFAULT_MAX_FAILED_ATTEMPTS;
        $lockMinutes ??= self::DEFAULT_LOCK_MINUTES;

        DB::transaction(function () use ($user, $maxAttempts, $lockMinutes): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $attempts = (int) $lockedUser->failed_login_attempts + 1;
            $lockedUser->failed_login_attempts = $attempts;

            if ($attempts >= $maxAttempts) {
                $lockedUser->locked_until = Carbon::now()->addMinutes($lockMinutes);
            }

            $lockedUser->save();
            $user->setRawAttributes($lockedUser->getAttributes(), true);
        }, 3);
    }

    /**
     * Record a successful authentication against the latest locked row.
     */
    public function recordSuccessfulLogin(
        User $user,
        ?string $ipAddress = null,
    ): void {
        DB::transaction(function () use ($user, $ipAddress): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $lockedUser->failed_login_attempts = 0;
            $lockedUser->locked_until = null;
            $lockedUser->last_login_at = Carbon::now();
            $lockedUser->last_login_ip = $ipAddress;
            $lockedUser->save();

            $user->setRawAttributes($lockedUser->getAttributes(), true);
        }, 3);
    }
}
