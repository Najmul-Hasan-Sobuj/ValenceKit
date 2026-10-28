<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\McpUsageLog;
use App\Models\Passkey;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\Request;
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
        $categories = null;
        $paymentMethods = null;

        if ($admin && $admin->can('finance.view')) {
            $financeSummary = $this->financeService->getMonthlySummary();
            $categories = $this->financeService->getCategories();
            $paymentMethods = config('finance.payment_methods', []);
        }

        $mcpStats = null;
        if ($admin && $admin->can('settings.mcp.usage.view')) {
            $mcpStats = [
                'total_calls' => McpUsageLog::count(),
                'calls_today' => McpUsageLog::whereDate('created_at', now()->toDateString())->count(),
            ];
        }

        $platformStats = [
            'total_users' => User::count(),
            'total_admins' => Admin::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'passkey_count' => Passkey::where('user_type', Admin::class)->count(),
        ];

        return Inertia::render('admin/Dashboard', [
            'financeSummary' => $financeSummary,
            'financeCategories' => $categories,
            'paymentMethods' => $paymentMethods,
            'mcpStats' => $mcpStats,
            'platformStats' => $platformStats,
        ]);
    }
}
