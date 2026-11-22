<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\McpUsageLog;
use App\Models\Module;
use App\Models\User;
use App\Services\FinanceService;
use DriveSnap\Authorization;
use DriveSnap\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    /**
     * Show the admin dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $admin = $request->user('admin');
        $financeSummary = null;
        $modules = Module::states();

        if (($modules['finance'] ?? false) && $admin && $admin->can('finance.view')) {
            $financeSummary = $this->financeService->getMonthlySummary();
        }

        $mcpStats = null;
        if (($modules['mcp'] ?? false) && $admin && $admin->can('settings.mcp.usage.view')) {
            $start = today()->subDays(13);
            $counts = McpUsageLog::query()->where('rpc_method', 'tools/call')->whereBetween('created_at', [$start, today()->endOfDay()])
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupByRaw('DATE(created_at)')->pluck('total', 'day');
            $mcpStats = [
                'total_calls' => McpUsageLog::where('rpc_method', 'tools/call')->count(),
                'calls_today' => McpUsageLog::where('rpc_method', 'tools/call')->whereDate('created_at', now()->toDateString())->count(),
                'daily' => collect(range(0, 13))->map(fn (int $offset): array => [
                    'date' => $start->copy()->addDays($offset)->toDateString(),
                    'count' => (int) $counts->get($start->copy()->addDays($offset)->toDateString(), 0),
                ])->all(),
            ];
        }

        $contacts = ($modules['contact'] ?? false) && $admin->can('contact.view') ? [
            'unread' => ContactMessage::unread()->count(),
            'latest' => ContactMessage::latest('id')->limit(4)->get(['id', 'name', 'subject', 'created_at']),
        ] : null;
        $latestBackup = null;
        if (($modules['backups'] ?? false) && Authorization::allows($admin, 'view')) {
            $backup = Backup::latest('created_at')->orderByDesc('id')->first();
            if ($backup) {
                $canLocate = Authorization::allows($admin, 'download');
                $local = $canLocate && $backup->path && Storage::disk('drivesnap')->exists($backup->path);
                $drive = $canLocate && $backup->status === 'completed' && $backup->drive_file_id !== null;
                $latestBackup = [
                    'name' => $canLocate && $backup->path ? basename($backup->path) : 'Backup '.$backup->id,
                    'date' => $backup->completed_at ?? $backup->created_at,
                    'status' => $backup->status,
                    'location' => $canLocate ? ($local && $drive ? 'Both' : ($local ? 'Local' : ($drive ? 'Drive' : 'Not saved'))) : null,
                ];
            }
        }

        $platformStats = $admin && $admin->can('users.view') ? [
            'total_users' => User::count(),
            'total_admins' => Admin::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
        ] : null;

        return Inertia::render('admin/Dashboard', [
            'recentActivity' => ($modules['audit'] ?? false) && $admin->can('audit.view')
                ? AuditLog::latest('id')->limit(10)->get(['id', 'action', 'description', 'created_at'])
                : null,
            'contacts' => $contacts,
            'backupOverview' => ($modules['backups'] ?? false) && Authorization::allows($admin, 'view') ? ['latest' => $latestBackup] : null,
            'financeSummary' => $financeSummary,
            'mcpStats' => $mcpStats,
            'platformStats' => $platformStats,
        ]);
    }
}
