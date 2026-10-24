<?php

namespace App\Providers;

use App\Models\Admin;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

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

        $this->configureRateLimiting();

        Gate::before(function ($user, string $ability) {
            if ($user instanceof Admin && $user->hasRole('Super Admin')) {
                return true;
            }

            return null;
        });

        Permission::saved(fn () => Cache::forget('admin_all_permissions'));
        Permission::deleted(fn () => Cache::forget('admin_all_permissions'));
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
     * Configure API and MCP rate limits.
     */
    protected function configureRateLimiting(): void
    {
        $tooManyRequests = static fn (Request $request, array $headers): JsonResponse => response()->json([
            'message' => 'Too Many Requests.',
        ], 429, $headers);

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($this->requestIdentity($request, 'api'))
            ->response($tooManyRequests));

        $mcpRead = fn (Request $request): Limit => $this->mcpLimit($request, 60, 'read')
            ->response($tooManyRequests);
        $mcpWrite = fn (Request $request): Limit => $this->mcpLimit($request, 20, 'write')
            ->response($tooManyRequests);

        RateLimiter::for('mcp-read', $mcpRead);
        RateLimiter::for('mcp-write', $mcpWrite);
        RateLimiter::for('mcp', fn (Request $request): Limit => $this->isMcpWriteRequest($request)
            ? $mcpWrite($request)
            : $mcpRead($request));
    }

    protected function mcpLimit(Request $request, int $maxAttempts, string $operation): Limit
    {
        $actor = rescue(static fn () => auth('mcp')->user(), null, false) ?: auth('sanctum')->user();
        $identity = $actor
            ? get_class($actor).':'.$actor->getAuthIdentifier()
            : hash('sha256', (string) $request->bearerToken());

        return Limit::perMinute($maxAttempts)
            ->by("mcp:{$operation}:{$identity}:{$request->ip()}");
    }

    protected function requestIdentity(Request $request, string $scope): string
    {
        return "{$scope}:".hash('sha256', (string) $request->bearerToken()).":{$request->ip()}";
    }

    protected function isMcpWriteRequest(Request $request): bool
    {
        return $request->input('method') === 'tools/call'
            && $request->input('params.name') === 'record-transaction-tool';
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
