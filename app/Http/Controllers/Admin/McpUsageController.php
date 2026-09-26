<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class McpUsageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('settings.mcp.usage.view'), 403);

        $logs = McpUsageLog::with('user')->latest()->paginate(20);

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

        return Inertia::render('admin/McpUsage', [
            'logs' => $logs,
            'activityChart' => $dates,
            'stats' => [
                'totalRequests' => McpUsageLog::count(),
                'uniqueClients' => McpUsageLog::distinct('ip_address')->count('ip_address'),
                'todayRequests' => McpUsageLog::whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
