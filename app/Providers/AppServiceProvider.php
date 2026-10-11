<?php

namespace App\Providers;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

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
    public function boot(): void
    {
        $this->enforceProductionSecurity();
        $this->configureDefaults();

        Passport::tokensCan([
            'mcp:use' => 'Connect to the ValenceKit MCP server',
            ...config('mcp.scopes', []),
        ]);

        Gate::before(function ($user, string $ability) {
            if ($user instanceof Admin && $user->hasRole('Super Admin')) {
                return true;
            }

            return null;
        });
    }

    /**
     * Prevent production from booting with unsafe application settings.
     */
    protected function enforceProductionSecurity(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $requirements = [
            'APP_DEBUG=false' => config('app.debug') === false,
            'APP_URL uses HTTPS' => parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https',
            'SESSION_SECURE_COOKIE=true' => config('session.secure') === true,
            'SESSION_HTTP_ONLY=true' => config('session.http_only') === true,
            'SESSION_SAME_SITE is lax or strict' => in_array(config('session.same_site'), ['lax', 'strict'], true),
        ];

        foreach ($requirements as $setting => $isSafe) {
            if (! $isSafe) {
                throw new \LogicException("Unsafe production configuration: {$setting}.");
            }
        }
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
