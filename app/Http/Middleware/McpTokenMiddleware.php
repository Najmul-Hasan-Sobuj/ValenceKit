<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\McpUsageLog;
use App\Models\User;
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
        $actor = auth('sanctum')->user();
        $token = $actor?->currentAccessToken();

        if ($defaultToken && $bearerToken === $defaultToken) {
            $tokenUsed = 'default_env_token';
            $userId = null;
            $adminId = null;
        } elseif ($actor && $token && $token->can('mcp:access')) {
            $tokenUsed = 'sanctum_token';
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
        ]);

        return $next($request);
    }
}
