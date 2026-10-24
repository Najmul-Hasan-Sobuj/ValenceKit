<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpTool;
use App\Models\McpUsageLog;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class McpSettingsController extends Controller
{
    /**
     * Display the MCP settings page with existing API tokens.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.mcp.view'), 403);

        $admin = $request->user('admin');

        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            default => 'DATE(created_at)',
        };

        $dailyCounts = McpUsageLog::where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("{$dateExpr} as date, count(*) as count")
            ->groupByRaw($dateExpr)
            ->pluck('count', 'date');

        $dates = collect(range(6, 0))->map(function ($daysAgo) use ($dailyCounts) {
            $date = now()->subDays($daysAgo);
            $dateKey = $date->format('Y-m-d');

            return [
                'day' => $date->format('D'),
                'date' => $date->format('M d'),
                'requests' => (int) ($dailyCounts[$dateKey] ?? 0),
            ];
        });

        return Inertia::render('admin/settings/Mcp', [
            'tokens' => $admin->tokens()->where('revoked', false)->orderBy('created_at', 'desc')->get()->map(fn ($token) => [
                'id' => (string) $token->id,
                'name' => $token->name,
                'last_used_at' => $token->updated_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]),
            'serverUrl' => url('/mcp/valencekit'),
            'oauthDiscoveryUrl' => url('/.well-known/oauth-protected-resource/mcp/valencekit'),
            'oauthScopes' => config('mcp.scopes', []),
            'activityChart' => $dates,
            'stats' => [
                'totalRequests' => McpUsageLog::count(),
                'uniqueClients' => McpUsageLog::distinct('ip_address')->count('ip_address'),
                'activeTokensCount' => $admin->tokens()->where('revoked', false)->count(),
            ],
            'mcpTools' => McpTool::orderBy('name')->get(),
        ]);
    }

    /**
     * Create a new MCP API token.
     */
    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $admin = $request->user('admin');

        $tokenResult = $admin->createToken($request->name, ['mcp:use', 'mcp:access']);

        $auditLogService->record(
            'mcp.token.created',
            afterValues: ['name' => $tokenResult->token->name, 'abilities' => ['mcp:use', 'mcp:access']],
            description: 'An MCP personal access token was created.',
            actor: $admin,
        );

        return back()->with('flash', [
            'token' => $tokenResult->accessToken,
            'message' => 'Token created successfully. Please copy it now, you won\'t be able to see it again.',
        ]);
    }

    /**
     * Revoke an existing API token.
     */
    public function destroy(Request $request, string $tokenId, AuditLogService $auditLogService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $admin = $request->user('admin');

        $token = $admin->tokens()->where('id', $tokenId)->firstOrFail();
        $tokenName = $token->name;
        $token->revoke();
        $token->delete();

        $auditLogService->record(
            'mcp.token.revoked',
            beforeValues: ['name' => $tokenName, 'token_id' => $tokenId],
            description: 'An MCP token was revoked.',
            actor: $admin,
        );

        return back()->with('success', 'Token revoked successfully.');
    }
}
