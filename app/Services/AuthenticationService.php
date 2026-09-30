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
    ) {
    }

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
            throw $this->invalidCredentials();
        }

        /*
         * Account security must be checked before
         * password verification.
         */
        if (! $this->accountSecurity->canAuthenticate($user)) {
            throw $this->invalidCredentials();
        }

        /*
         * Verify credentials.
         */
        if (! Hash::check($password, $user->password)) {
            $this->accountSecurity->recordFailedLogin($user);

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

        return $user->fresh();
    }

    /**
     * Log out the currently authenticated user.
     */
    public function logout(): void
    {
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
