<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceSettingsController extends Controller
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    /**
     * Show the finance settings page.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.finance.view'), 403);

        return Inertia::render('admin/settings/Finance', [
            'activeCurrency' => $this->financeService->getActiveCurrency(),
            'monthlyBudget' => $this->financeService->getMonthlyBudget(),
            'currencies' => config('finance.currencies', []),
        ]);
    }

    /**
     * Update the global finance configuration.
     */
    public function update(Request $request, AuditLogService $auditLogService)
    {
        abort_unless($request->user('admin')->can('settings.finance.edit'), 403);

        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(array_keys(config('finance.currencies', [])))],
            'monthly_budget' => ['required', 'numeric', 'min:0'],
        ]);

        $before = [
            'currency' => $this->financeService->getActiveCurrency()['code'],
            'monthly_budget' => $this->financeService->getMonthlyBudget(),
        ];

        $this->financeService->setActiveCurrency($validated['currency']);
        $this->financeService->setMonthlyBudget((float) $validated['monthly_budget']);

        $auditLogService->record(
            'settings.finance.updated',
            afterValues: [
                'currency' => $validated['currency'],
                'monthly_budget' => (float) $validated['monthly_budget'],
            ],
            beforeValues: $before,
            description: 'Finance configuration was updated.',
            actor: $request->user('admin'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Finance configuration updated successfully.'),
        ]);

        return back()->with('success', 'Finance configuration updated successfully.');
    }
}
