<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpUsageLog;
use Illuminate\Http\Request;
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

        $dates = collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
            $count = McpUsageLog::whereBetween('created_at', [$start, $end])->count();

            return [
                'day' => $date->format('D'),
                'date' => $date->format('M d'),
                'requests' => $count,
            ];
        });

        return Inertia::render('admin/settings/Mcp', [
            'tokens' => $admin->tokens()->orderBy('created_at', 'desc')->get()->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ]),
            'serverUrl' => url('/mcp/valencekit'),
            'activityChart' => $dates,
            'stats' => [
                'totalRequests' => McpUsageLog::count(),
                'uniqueClients' => McpUsageLog::distinct('ip_address')->count('ip_address'),
                'activeTokensCount' => $admin->tokens()->count(),
            ],
        ]);
    }

    /**
     * Create a new MCP API token.
     */
    public function store(Request $request)
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $admin = $request->user('admin');

        $token = $admin->createToken($request->name, ['mcp:access']);

        return back()->with('flash', [
            'token' => $token->plainTextToken,
            'message' => 'Token created successfully. Please copy it now, you won\'t be able to see it again.',
        ]);
    }

    /**
     * Revoke an existing API token.
     */
    public function destroy(Request $request, string $tokenId)
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $admin = $request->user('admin');

        $admin->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'Token revoked successfully.');
    }
}
