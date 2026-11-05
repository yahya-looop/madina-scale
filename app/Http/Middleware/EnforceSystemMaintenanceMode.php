<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSystemMaintenanceMode
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuthorizationService $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('login', 'login.store', 'logout')) {
            return $next($request);
        }

        if (! $this->settings->get('system.maintenance_mode', false)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user instanceof User && $this->authorization->isDeveloper($user)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'The application is temporarily under maintenance. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE, [
                'Retry-After' => '300',
            ]);
        }

        return response()->view('errors.maintenance', [], Response::HTTP_SERVICE_UNAVAILABLE, [
            'Retry-After' => '300',
        ]);
    }
}
