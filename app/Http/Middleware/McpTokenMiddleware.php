<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\McpUsageLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

class McpTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $defaultToken = config('services.mcp.default_token');
        $bearerToken = $request->bearerToken();
        $oauthActor = auth('mcp')->user();
        $sanctumActor = auth('sanctum')->user();
        $actor = $oauthActor ?: $sanctumActor;
        $token = $actor?->currentAccessToken();

        if ($defaultToken && $bearerToken === $defaultToken) {
            $tokenUsed = 'default_env_token';
            $authMethod = 'environment';
            $userId = null;
            $adminId = null;
        } elseif ($oauthActor && $token && $token->can('mcp:use')) {
            $tokenUsed = 'oauth_token';
            $authMethod = 'oauth';
            $userId = $actor instanceof User ? $actor->id : null;
            $adminId = $actor instanceof Admin ? $actor->id : null;
        } elseif ($sanctumActor && $token && $token->can('mcp:access')) {
            $tokenUsed = 'sanctum_token';
            $authMethod = 'sanctum';
            $userId = $actor instanceof User ? $actor->id : null;
            $adminId = $actor instanceof Admin ? $actor->id : null;
        } else {
            abort(401, 'Unauthorized MCP Access');
        }

        McpUsageLog::create([
            'token_used' => $tokenUsed,
            'user_id' => $userId,
            'admin_id' => $adminId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => '/'.ltrim($request->path(), '/'),
            'auth_method' => $authMethod,
            'oauth_client_id' => $this->oauthClientId($oauthActor),
            'scopes' => $this->scopes($token),
        ]);

        return $next($request);
    }

    private function oauthClientId(mixed $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $guard = auth()->guard('mcp');

        return method_exists($guard, 'client')
            ? (string) ($guard->client()?->getKey())
            : null;
    }

    /**
     * @return array<string>|null
     */
    private function scopes(mixed $token): ?array
    {
        if ($token instanceof AccessToken) {
            return $token->oauth_scopes;
        }

        $abilities = data_get($token, 'abilities');

        return is_array($abilities)
            ? array_map(static fn (mixed $ability): string => (string) $ability, array_values($abilities))
            : null;
    }
}
