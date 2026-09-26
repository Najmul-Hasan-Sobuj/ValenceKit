<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $user = auth('sanctum')->user();

        if ($defaultToken && $bearerToken === $defaultToken) {
            $tokenUsed = 'default_env_token';
            $userId = null;
        } elseif ($user) {
            $tokenUsed = 'sanctum_token';
            $userId = $user->id;
        } else {
            abort(401, 'Unauthorized MCP Access');
        }

        \App\Models\McpUsageLog::create([
            'token_used' => $tokenUsed,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->fullUrl(),
        ]);

        return $next($request);
    }
}
