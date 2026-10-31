<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
     * Returns the authenticated user on success.
     *
     * @throws ValidationException
     */
    public function authenticate(
        string $email,
        string $password,
        ?string $ipAddress = null,
        bool $remember = false,
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Do not reveal whether the account exists.
         */
        if ($user === null) {
            $this->activityLog->recordSystem('login_failed', 'authentication', 'A login attempt failed.', metadata: ['email_fingerprint' => hash('sha256', mb_strtolower(trim($email))), 'reason' => 'unknown_account']);
            throw $this->invalidCredentials();
        }

        /*
         * Account security must be checked before
         * password verification.
         */
        if (! $this->accountSecurity->canAuthenticate($user)) {
            $this->activityLog->recordForUser($user, 'login_blocked', 'authentication', 'A login attempt was blocked by account security.', metadata: ['reason' => 'account_not_eligible']);
            throw $this->invalidCredentials();
        }

        /*
         * Verify credentials.
         */
        if (! Hash::check($password, $user->password)) {
            $this->accountSecurity->recordFailedLogin($user);
            $this->activityLog->recordForUser($user, 'login_failed', 'authentication', 'A login attempt failed.', metadata: ['reason' => 'invalid_password']);

            throw $this->invalidCredentials();
        }

        /*
         * Authenticate the user.
         */
        Auth::login($user, $remember);

        /*
         * Reset security counters and record login metadata.
         */
        $this->accountSecurity->recordSuccessfulLogin(
            $user,
            $ipAddress,
        );
        $this->activityLog->recordForUser($user, 'login', 'authentication', 'User signed in successfully.');

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
