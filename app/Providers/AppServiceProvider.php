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
             * Only intercept abilities that are actually registered
             * as application permissions.
             *
             * Unknown abilities are left untouched so that future
             * Laravel policies and explicitly defined gates can still
             * handle them normally.
             */
            $permissionExists = Permission::query()
                ->where('slug', $ability)
                ->exists();

            if (! $permissionExists) {
                return null;
            }

            return $authorizationService->can(
                $user,
                $ability,
            );
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

        Password::defaults(fn (): ?Password => app()->isProduction()
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
