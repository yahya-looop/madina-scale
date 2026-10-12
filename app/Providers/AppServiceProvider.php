<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use App\Services\AuthorizationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(
        AuthorizationService $authorizationService,
    ): void {
        $this->configureDefaults();

        $this->configureAuthorization($authorizationService);
    }

    /**
     * Configure application authorization.
     */
    protected function configureAuthorization(
        AuthorizationService $authorizationService,
    ): void {
        Gate::before(function (
            User $user,
            string $ability,
        ) use ($authorizationService): ?bool {
            /*
             * Application system permissions must always pass through
             * AuthorizationService, including unregistered system abilities.
             */
            if (str_starts_with($ability, 'system.')) {
                return $authorizationService->can(
                    $user,
                    $ability,
                );
            }

            /*
             * Resolve database-backed permissions through the central
             * authorization service without overriding unrelated Laravel
             * gates and policies.
             */
            $isDatabasePermission = Permission::query()
                ->where('slug', $ability)
                ->exists();

            if ($isDatabasePermission) {
                return $authorizationService->can(
                    $user,
                    $ability,
                );
            }

            /*
             * Leave unrelated Laravel gates and policies untouched.
             */
            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
