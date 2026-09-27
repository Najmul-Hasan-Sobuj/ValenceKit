<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\StoreFinanceTransactionRequest;
use App\Http\Requests\Admin\Finance\UpdateFinanceTransactionRequest;
use App\Models\FinanceTransaction;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        protected FinanceService $financeService
    ) {}

    /**
     * Display a listing of finance transactions and dashboard metrics.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('finance.view'), 403);

        $from = $request->filled('from')
            ? $request->date('from')->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? $request->date('to')->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $baseQuery = FinanceTransaction::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($sub) use ($search) {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type') && in_array($request->input('type'), ['income', 'expense']), function ($q) use ($request) {
                $q->where('type', $request->input('type'));
            })
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->where('category_key', $request->input('category'));
            })
            ->when($request->filled('payment_method'), function ($q) use ($request) {
                $q->where('payment_method', $request->input('payment_method'));
            })
            ->betweenDates($from->toDateString(), $to->toDateString());

        $transactions = (clone $baseQuery)
            ->with('admin:id,name,email')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Calculate period metrics for current filter
        $periodIncome = (float) (clone $baseQuery)->where('type', 'income')->sum('amount');
        $periodExpense = (float) (clone $baseQuery)->where('type', 'expense')->sum('amount');
        $periodNet = $periodIncome - $periodExpense;

        // Current month global executive summary (for KPIs & Budget)
        $monthlySummary = $this->financeService->getMonthlySummary();

        // Charts data
        $timelineData = $this->financeService->getAnalyticsTimeline($from, $to);
        $categoryBreakdown = $this->financeService->getCategoryBreakdown($from, $to);

        return Inertia::render('admin/finance/Index', [
            'transactions' => $transactions,
            'monthlySummary' => $monthlySummary,
            'periodMetrics' => [
                'income' => round($periodIncome, 2),
                'expense' => round($periodExpense, 2),
                'net' => round($periodNet, 2),
                'count' => (clone $baseQuery)->count(),
            ],
            'timelineChart' => $timelineData,
            'categoryChart' => $categoryBreakdown,
            'categories' => $this->financeService->getCategories(),
            'paymentMethods' => config('finance.payment_methods', []),
            'currency' => $this->financeService->getActiveCurrency(),
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', ''),
                'category' => $request->input('category', ''),
                'payment_method' => $request->input('payment_method', ''),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ]);
    }

    /**
     * Provide a live JSON summary of monthly cashflow for the sidebar widget.
     */
    public function summary()
    {
        return response()->json($this->financeService->getMonthlySummary());
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(StoreFinanceTransactionRequest $request)
    {
        $this->financeService->createTransaction(
            $request->validated(),
            $request->user('admin')->id
        );

        return back()->with('success', 'Transaction recorded successfully.');
    }

    /**
     * Update the specified transaction in storage.
     */
    public function update(UpdateFinanceTransactionRequest $request, FinanceTransaction $transaction)
    {
        $this->financeService->updateTransaction($transaction, $request->validated());

        return back()->with('success', 'Transaction updated successfully.');
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function destroy(Request $request, FinanceTransaction $transaction)
    {
        abort_unless($request->user('admin')->can('finance.delete'), 403);

        $transaction->delete();

        return back()->with('success', 'Transaction removed successfully.');
    }

    /**
     * Perform bulk deletion of transactions.
     */
    public function bulkDestroy(Request $request)
    {
        abort_unless($request->user('admin')->can('finance.delete'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:finance_transactions,id'],
        ]);

        $count = $this->financeService->deleteTransactions($validated['ids']);

        return back()->with('success', "{$count} transactions removed successfully.");
    }

    /**
     * Export the filtered transaction ledger to Excel (.xlsx / SpreadsheetML).
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user('admin')->can('finance.export'), 403);

        $filters = $request->only(['search', 'type', 'category', 'payment_method', 'from', 'to']);

        if ($request->filled('ids')) {
            $filters['ids'] = is_array($request->input('ids'))
                ? $request->input('ids')
                : explode(',', $request->input('ids'));
        }

        $query = $this->financeService
            ->filteredTransactionQuery($filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        return $this->financeService->exportExcel($query);
    }
}
