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

        $startDate = $request->filled('from') ? $request->date('from')->startOfDay() : now()->startOfMonth()->startOfDay();
        $endDate = $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->startOfDay(), $startDate->endOfDay()];
        }

        $query = McpUsageLog::with('user')->whereBetween('created_at', [$startDate, $endDate]);

        $logs = clone $query;
        $logs = $logs->selectRaw('MAX(id) as id, token_used, user_id, ip_address, MAX(user_agent) as user_agent, MAX(endpoint) as endpoint, MAX(created_at) as created_at')
            ->groupBy('token_used', 'user_id', 'ip_address')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $isMonthly = $startDate->diffInDays($endDate) > 31;

        if ($isMonthly) {
            $monthlyCounts = McpUsageLog::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('SUBSTR(created_at, 1, 7) as month_key, COUNT(*) as count')
                ->groupBy('month_key')
                ->pluck('count', 'month_key');

            $current = $startDate->startOfMonth();
            $end = $endDate->startOfMonth();
            $dates = collect();

            while ($current->lte($end)) {
                $dates->push([
                    'day' => $current->format('Y'),
                    'date' => $current->format('M'),
                    'requests' => (int) ($monthlyCounts->get($current->format('Y-m')) ?? 0),
                ]);
                $current = $current->addMonth();
            }
        } else {
            $dailyCounts = McpUsageLog::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as log_date, COUNT(*) as count')
                ->groupBy('log_date')
                ->pluck('count', 'log_date');

            $current = $startDate->startOfDay();
            $end = $endDate->startOfDay();
            $dates = collect();

            while ($current->lte($end)) {
                $dates->push([
                    'day' => $current->format('D'),
                    'date' => $current->format('M d'),
                    'requests' => (int) ($dailyCounts->get($current->toDateString()) ?? 0),
                ]);
                $current = $current->addDay();
            }
        }

        return Inertia::render('admin/McpUsage', [
            'logs' => $logs,
            'activityChart' => $dates,
            'chartInterval' => $isMonthly ? 'month' : 'day',
            'stats' => [
                'totalRequests' => (clone $query)->count(),
                'uniqueClients' => (clone $query)->distinct('ip_address')->count('ip_address'),
                'todayRequests' => McpUsageLog::whereDate('created_at', now()->toDateString())->count(),
            ],
            'filters' => ['from' => $startDate->toDateString(), 'to' => $endDate->toDateString()],
        ]);
    }
}
