<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateMcpToolRequest;
use App\Models\McpTool;
use App\Services\McpToolSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class McpController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.mcp.view'), 403);

        return Inertia::render('admin/settings/Mcp', [
            'mcpTools' => McpTool::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMcpToolRequest $request, McpTool $mcpTool): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $mcpTool->update($request->validated());

        Cache::forget('active_mcp_tools');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tool status updated.')]);

        return back();
    }

    public function sync(Request $request, McpToolSyncService $syncService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.mcp.edit'), 403);

        $syncService->sync();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('MCP registry synchronized.')]);

        return back();
    }
}
