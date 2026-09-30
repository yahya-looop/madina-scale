<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

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
     * Record a failed authentication attempt.
     *
     * The account is temporarily locked after the configured threshold.
     */
    public function recordFailedLogin(
        User $user,
        ?int $maxAttempts = null,
        ?int $lockMinutes = null,
    ): void {
        $maxAttempts ??= self::DEFAULT_MAX_FAILED_ATTEMPTS;
        $lockMinutes ??= self::DEFAULT_LOCK_MINUTES;

        $attempts = $user->failed_login_attempts + 1;

        $user->failed_login_attempts = $attempts;

        if ($attempts >= $maxAttempts) {
            $user->locked_until = Carbon::now()->addMinutes($lockMinutes);
        }

        $user->save();
    }

    /**
     * Record a successful authentication.
     */
    public function recordSuccessfulLogin(
        User $user,
        ?string $ipAddress = null,
    ): void {
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = Carbon::now();
        $user->last_login_ip = $ipAddress;

        $user->save();
    }
}
