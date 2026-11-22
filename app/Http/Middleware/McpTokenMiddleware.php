<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\McpUsageLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

        if (! $actor instanceof Admin || ! $actor->is_active || ! $token || (! $token->can('mcp:use') && ! $token->can('mcp:access'))) {
            abort(401, 'Unauthorized MCP Access');
        }

        $clientId = $this->oauthClientId($actor);
        $tokenUsed = $clientId ? 'oauth_token' : 'passport_token';
        $authMethod = $clientId ? 'oauth' : 'passport';

        $started = hrtime(true);
        $usage = McpUsageLog::create([
            'token_used' => $tokenUsed,
            'admin_id' => $actor->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => '/'.ltrim($request->path(), '/'),
            'rpc_method' => in_array($request->input('method'), [
                'initialize', 'ping', 'tools/list', 'tools/call', 'resources/list',
                'resources/read', 'prompts/list', 'prompts/get', 'notifications/initialized',
            ], true) ? $request->input('method') : null,
            'auth_method' => $authMethod,
            'oauth_client_id' => $clientId,
            'scopes' => $this->scopes($token),
        ]);

        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();

            return $response;
        } catch (\Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            throw $exception;
        } finally {
            $usage->update(['response_status' => $status, 'duration_ms' => (int) ((hrtime(true) - $started) / 1000000), 'request_id' => Context::get('request_id')]);
        }
    }

    private function oauthClientId(Admin $actor): ?string
    {
        $guard = rescue(static fn () => auth()->guard('mcp'), null, false);

        $client = method_exists($guard, 'client') ? $guard->client() : null;

        if ($client) {
            return $client->hasGrantType('personal_access') ? null : (string) $client->getKey();
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
