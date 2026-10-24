<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\McpUsageLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
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
        $actor = rescue(static fn () => auth('mcp')->user(), null, false);
        $token = $actor?->currentAccessToken();

        if (! $actor || ! $token || (! $token->can('mcp:use') && ! $token->can('mcp:access'))) {
            abort(401, 'Unauthorized MCP Access');
        }

        $clientId = $this->oauthClientId($actor);
        $tokenUsed = $clientId ? 'oauth_token' : 'passport_token';
        $authMethod = $clientId ? 'oauth' : 'passport';
        $userId = $actor instanceof User ? $actor->id : null;
        $adminId = $actor instanceof Admin ? $actor->id : null;

        McpUsageLog::create([
            'token_used' => $tokenUsed,
            'user_id' => $userId,
            'admin_id' => $adminId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => '/'.ltrim($request->path(), '/'),
            'auth_method' => $authMethod,
            'oauth_client_id' => $clientId,
            'scopes' => $this->scopes($token),
        ]);

        return $next($request);
    }

    private function oauthClientId(mixed $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        $guard = rescue(static fn () => auth()->guard('mcp'), null, false);

        if (method_exists($guard, 'client') && $guard->client()) {
            return (string) $guard->client()->getKey();
        }

        $token = $actor->currentAccessToken();

        if ($token instanceof Token && $token->client && ! in_array('personal_access', $token->client->grant_types ?? [], true)) {
            return (string) $token->client_id;
        }

        return null;
    }

    /**
     * @return array<string>|null
     */
    private function scopes(mixed $token): ?array
    {
        if (! $token) {
            return null;
        }

        if ($token instanceof AccessToken) {
            return $token->oauth_scopes;
        }

        if (isset($token->scopes) && is_array($token->scopes)) {
            return array_values($token->scopes);
        }

        $abilities = data_get($token, 'abilities');

        return is_array($abilities)
            ? array_map(static fn (mixed $ability): string => (string) $ability, array_values($abilities))
            : null;
    }
}
