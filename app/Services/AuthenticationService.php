<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    public function __construct(
        protected AccountSecurityService $accountSecurity,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * Authenticate a user using email and password.
     *
     * Account eligibility, password verification, and lockout counters are
     * serialized on the user row to prevent concurrent attempts bypassing
     * the failed-login threshold.
     *
     * @throws ValidationException
     */
    public function authenticate(
        string $email,
        string $password,
        ?string $ipAddress = null,
        bool $remember = false,
    ): User {
        /** @var array{status: string, user?: User} $result */
        $result = DB::transaction(function () use ($email, $password, $ipAddress): array {
            $user = User::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return ['status' => 'unknown_account'];
            }

            if (! $this->accountSecurity->canAuthenticate($user)) {
                return ['status' => 'account_not_eligible', 'user' => $user];
            }

            if (! Hash::check($password, $user->password)) {
                $this->accountSecurity->recordFailedLogin($user);

                return ['status' => 'invalid_password', 'user' => $user];
            }

            $this->accountSecurity->recordSuccessfulLogin($user, $ipAddress);

            return ['status' => 'authenticated', 'user' => $user->fresh()];
        }, 3);

        if ($result['status'] === 'unknown_account') {
            $this->activityLog->recordSystem(
                'login_failed',
                'authentication',
                'A login attempt failed.',
                metadata: [
                    'email_fingerprint' => hash('sha256', mb_strtolower(trim($email))),
                    'reason' => 'unknown_account',
                ],
            );

            throw $this->invalidCredentials();
        }

        $user = $result['user'] ?? null;

        if (! $user instanceof User) {
            throw $this->invalidCredentials();
        }

        if ($result['status'] === 'account_not_eligible') {
            $this->activityLog->recordForUser(
                $user,
                'login_blocked',
                'authentication',
                'A login attempt was blocked by account security.',
                metadata: ['reason' => 'account_not_eligible'],
            );

            throw $this->invalidCredentials();
        }

        if ($result['status'] === 'invalid_password') {
            $this->activityLog->recordForUser(
                $user,
                'login_failed',
                'authentication',
                'A login attempt failed.',
                metadata: ['reason' => 'invalid_password'],
            );

            throw $this->invalidCredentials();
        }

        Auth::login($user, $remember);
        $this->activityLog->recordForUser(
            $user,
            'login',
            'authentication',
            'User signed in successfully.',
        );

        return $user->fresh();
    }

    /**
     * Log out the currently authenticated user.
     */
    public function logout(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->activityLog->recordForUser($user, 'logout', 'authentication', 'User signed out.');
        }
        Auth::logout();
    }

    /**
     * Build a generic authentication failure.
     */
    protected function invalidCredentials(): ValidationException
    {
        return ValidationException::withMessages([
            'email' => 'The provided credentials are invalid.',
        ]);
    }
}
