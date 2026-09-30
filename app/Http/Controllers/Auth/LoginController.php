<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        protected AuthenticationService $authentication,
    ) {
    }

    /**
     * Show the login page.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Authenticate the user.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->authentication->authenticate(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            ipAddress: $request->ip(),
            remember: $request->boolean('remember'),
        );

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log out the authenticated user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->authentication->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
