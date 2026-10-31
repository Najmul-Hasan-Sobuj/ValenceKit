<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class McpUsageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('settings.mcp.usage.view'), 403);

        $from = rescue(fn () => $request->filled('from') ? $request->date('from')->startOfDay() : null, null, false);
        $to = rescue(fn () => $request->filled('to') ? $request->date('to')->endOfDay() : null, null, false);

        $startDate = $from ?? now()->startOfMonth()->startOfDay();
        $endDate = $to ?? now()->endOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->startOfDay(), $startDate->endOfDay()];
        }

        $query = McpUsageLog::with(['user', 'admin'])->whereBetween('created_at', [$startDate, $endDate]);

        $logs = (clone $query)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $isMonthly = $startDate->diffInDays($endDate) > 31;
        $driver = DB::connection()->getDriverName();

        $monthExpr = match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $dateExpr = match ($driver) {
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            default => 'DATE(created_at)',
        };

        if ($isMonthly) {
            $monthlyCounts = McpUsageLog::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw("{$monthExpr} as month_key, COUNT(*) as count")
                ->groupByRaw($monthExpr)
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
                ->selectRaw("{$dateExpr} as log_date, COUNT(*) as count")
                ->groupByRaw($dateExpr)
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
