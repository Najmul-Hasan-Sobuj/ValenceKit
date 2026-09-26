<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\McpUsageLog;

class McpUsageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('settings.mcp.usage.view'), 403);

        $logs = McpUsageLog::with('user')->latest()->paginate(20);

        return Inertia::render('admin/McpUsage', [
            'logs' => $logs,
        ]);
    }
}
