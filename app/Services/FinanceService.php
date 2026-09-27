<?php

namespace App\Services;

use App\Models\FinanceSetting;
use App\Models\FinanceTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceService
{
    /**
     * Get the active currency configuration.
     *
     * @return array{code: string, symbol: string, name: string, locale: string, precision: int}
     */
    public function getActiveCurrency(): array
    {
        $code = FinanceSetting::get('currency', config('finance.default_currency', 'USD'));
        $currencies = config('finance.currencies', []);

        return $currencies[$code] ?? $currencies['USD'] ?? [
            'code' => 'USD',
            'symbol' => '$',
            'name' => 'US Dollar',
            'locale' => 'en-US',
            'precision' => 2,
        ];
    }

    /**
     * Set the global active currency.
     */
    public function setActiveCurrency(string $code): void
    {
        $validCodes = array_keys(config('finance.currencies', []));
        if (in_array($code, $validCodes, true)) {
            FinanceSetting::set('currency', $code);
        }
    }

    /**
     * Get the monthly budget limit.
     */
    public function getMonthlyBudget(): float
    {
        return (float) FinanceSetting::get('monthly_budget', config('finance.default_monthly_budget', 3500.00));
    }

    /**
     * Set the monthly budget limit.
     */
    public function setMonthlyBudget(float $budget): void
    {
        FinanceSetting::set('monthly_budget', max(0, $budget));
    }

    /**
     * Get defined categories from config.
     *
     * @return array<string, list<array{id: string, name: string, icon: string, color: string, monthly_budget?: float}>>
     */
    public function getCategories(): array
    {
        return config('finance.categories', ['income' => [], 'expense' => []]);
    }

    /**
     * Find a category definition by key.
     *
     * @return array{id: string, name: string, icon: string, color: string, monthly_budget?: float}|null
     */
    public function findCategory(string $key): ?array
    {
        $categories = $this->getCategories();
        foreach (['income', 'expense'] as $type) {
            foreach ($categories[$type] ?? [] as $cat) {
                if ($cat['id'] === $key) {
                    return $cat;
                }
            }
        }

        return null;
    }

    /**
     * Compute current or specified month's financial summary.
     *
     * @return array{
     *     total_income: float,
     *     total_expense: float,
     *     net_balance: float,
     *     budget_total: float,
     *     budget_spent_percentage: float,
     *     remaining_budget: float,
     *     burn_rate: float,
     *     days_passed: int,
     *     days_remaining: int,
     *     currency: array{code: string, symbol: string, name: string, locale: string, precision: int}
     * }
     */
    public function getMonthlySummary(?CarbonInterface $date = null): array
    {
        $targetDate = $date ?? now();
        $startOfMonth = $targetDate->copy()->startOfMonth();
        $endOfMonth = $targetDate->copy()->endOfMonth();

        $transactions = FinanceTransaction::betweenDates($startOfMonth->toDateString(), $endOfMonth->toDateString())
            ->get(['type', 'amount']);

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        $budgetTotal = $this->getMonthlyBudget();
        $budgetSpentPercentage = $budgetTotal > 0
            ? round(($totalExpense / $budgetTotal) * 100, 1)
            : 0.0;

        $remainingBudget = max(0.0, $budgetTotal - $totalExpense);

        $daysPassed = max(1, $targetDate->day);
        $daysInMonth = $targetDate->daysInMonth;
        $daysRemaining = max(0, $daysInMonth - $daysPassed);
        $burnRate = round($totalExpense / $daysPassed, 2);
        $dailyBudget = round($budgetTotal / max(1, $daysInMonth), 2);
        $netMargin = $totalIncome > 0
            ? round(($netBalance / $totalIncome) * 100, 1)
            : ($netBalance > 0 ? 100.0 : 0.0);

        $topExpense = FinanceTransaction::betweenDates($startOfMonth->toDateString(), $endOfMonth->toDateString())
            ->where('type', 'expense')
            ->selectRaw('category_key, category_name, SUM(amount) as total_amount')
            ->groupBy('category_key', 'category_name')
            ->orderByDesc('total_amount')
            ->first();

        $topExpenseData = $topExpense ? [
            'category_key' => $topExpense->category_key,
            'name' => $topExpense->category_name,
            'amount' => round((float) $topExpense->total_amount, 2),
            'percentage' => $totalExpense > 0 ? round(($topExpense->total_amount / $totalExpense) * 100, 1) : 0.0,
        ] : null;

        $transactionCount = FinanceTransaction::betweenDates($startOfMonth->toDateString(), $endOfMonth->toDateString())->count();

        $recentTransactions = FinanceTransaction::latest('transaction_date')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'type', 'amount', 'category_name', 'category_key', 'description', 'payment_method', 'transaction_date', 'is_recurring'])
            ->toArray();

        return [
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_balance' => round($netBalance, 2),
            'net_margin' => $netMargin,
            'daily_budget' => $dailyBudget,
            'budget_total' => round($budgetTotal, 2),
            'budget_spent_percentage' => $budgetSpentPercentage,
            'remaining_budget' => round($remainingBudget, 2),
            'burn_rate' => $burnRate,
            'days_passed' => $daysPassed,
            'days_remaining' => $daysRemaining,
            'days_in_month' => $daysInMonth,
            'transaction_count' => $transactionCount,
            'top_expense' => $topExpenseData,
            'recent_transactions' => $recentTransactions,
            'currency' => $this->getActiveCurrency(),
        ];
    }

    /**
     * Generate timeline analytics for Chart.js.
     *
     * @return array{
     *     labels: list<string>,
     *     income: list<float>,
     *     expense: list<float>,
     *     interval: string
     * }
     */
    public function getAnalyticsTimeline(CarbonInterface $from, CarbonInterface $to): array
    {
        $isMonthly = $from->diffInDays($to) > 31;
        $transactions = FinanceTransaction::betweenDates($from->toDateString(), $to->toDateString())
            ->get(['type', 'amount', 'transaction_date']);

        $labels = [];
        $incomeData = [];
        $expenseData = [];

        if ($isMonthly) {
            $current = $from->copy()->startOfMonth();
            $end = $to->copy()->startOfMonth();

            while ($current->lte($end)) {
                $monthKey = $current->format('Y-m');
                $labels[] = $current->format('M Y');

                $monthTx = $transactions->filter(function ($tx) use ($monthKey) {
                    return $tx->transaction_date->format('Y-m') === $monthKey;
                });

                $incomeData[] = round((float) $monthTx->where('type', 'income')->sum('amount'), 2);
                $expenseData[] = round((float) $monthTx->where('type', 'expense')->sum('amount'), 2);

                $current = $current->addMonth();
            }

            $interval = 'month';
        } else {
            $current = $from->copy()->startOfDay();
            $end = $to->copy()->startOfDay();

            while ($current->lte($end)) {
                $dateKey = $current->toDateString();
                $labels[] = $current->format('M d');

                $dayTx = $transactions->filter(function ($tx) use ($dateKey) {
                    return $tx->transaction_date->toDateString() === $dateKey;
                });

                $incomeData[] = round((float) $dayTx->where('type', 'income')->sum('amount'), 2);
                $expenseData[] = round((float) $dayTx->where('type', 'expense')->sum('amount'), 2);

                $current = $current->addDay();
            }

            $interval = 'day';
        }

        return [
            'labels' => $labels,
            'income' => $incomeData,
            'expense' => $expenseData,
            'interval' => $interval,
        ];
    }

    /**
     * Get expenses aggregated by category for donut chart.
     *
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     colors: list<string>,
     *     icons: list<string>
     * }
     */
    public function getCategoryBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        $expenses = FinanceTransaction::expense()
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->get(['category_key', 'category_name', 'amount']);

        $grouped = $expenses->groupBy('category_key');
        $expenseCategories = collect(config('finance.categories.expense', []))->keyBy('id');

        $labels = [];
        $data = [];
        $colors = [];
        $icons = [];

        foreach ($grouped as $key => $items) {
            $categoryConfig = $expenseCategories->get($key);
            $labels[] = $categoryConfig['name'] ?? ($items->first()->category_name ?? ucfirst($key));
            $data[] = round((float) $items->sum('amount'), 2);
            $colors[] = $categoryConfig['color'] ?? '#64748b';
            $icons[] = $categoryConfig['icon'] ?? 'Receipt';
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colors,
            'icons' => $icons,
        ];
    }

    /**
     * Create a new transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTransaction(array $data, int $adminId): FinanceTransaction
    {
        $activeCurrency = $this->getActiveCurrency();
        $category = $this->findCategory($data['category_key'] ?? '');

        return FinanceTransaction::create([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $activeCurrency['code'],
            'category_key' => $data['category_key'],
            'category_name' => $category['name'] ?? ($data['category_name'] ?? ucfirst($data['category_key'])),
            'description' => $data['description'],
            'reference' => ! empty($data['reference']) ? $data['reference'] : FinanceTransaction::generateReference(),
            'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? 'card',
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
            'recurring_frequency' => $data['recurring_frequency'] ?? null,
            'notes' => $data['notes'] ?? null,
            'admin_id' => $adminId,
        ]);
    }

    /**
     * Update an existing transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTransaction(FinanceTransaction $transaction, array $data): FinanceTransaction
    {
        if (isset($data['category_key']) && $data['category_key'] !== $transaction->category_key) {
            $category = $this->findCategory($data['category_key']);
            if ($category) {
                $data['category_name'] = $category['name'];
            }
        }

        $transaction->update($data);

        return $transaction;
    }

    /**
     * Delete multiple transactions by IDs.
     *
     * @param  list<int>  $ids
     */
    public function deleteTransactions(array $ids): int
    {
        return FinanceTransaction::whereIn('id', $ids)->delete();
    }

    /**
     * Build a filtered FinanceTransaction query from a plain filter array.
     *
     * Callable from controllers, jobs, commands, or any other context.
     * When 'ids' is present it takes priority — date range and other filters are skipped.
     *
     * @param  array{search?: string, type?: string, category?: string, payment_method?: string, from?: string, to?: string, ids?: int[]}  $filters
     */
    public function filteredTransactionQuery(array $filters): Builder
    {
        if (! empty($filters['ids'])) {
            return FinanceTransaction::query()->whereIn('id', $filters['ids']);
        }

        $from = ! empty($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = ! empty($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return FinanceTransaction::query()
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($sub) use ($search) {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('category_name', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['type']) && in_array($filters['type'], ['income', 'expense']), function ($q) use ($filters) {
                $q->where('type', $filters['type']);
            })
            ->when(! empty($filters['category']), function ($q) use ($filters) {
                $q->where('category_key', $filters['category']);
            })
            ->when(! empty($filters['payment_method']), function ($q) use ($filters) {
                $q->where('payment_method', $filters['payment_method']);
            })
            ->betweenDates($from->toDateString(), $to->toDateString());
    }

    /**
     * Stream an Excel XML (.xlsx / SpreadsheetML) spreadsheet for download.
     *
     * @param  Builder<FinanceTransaction>  $query
     */
    public function exportExcel(Builder $query): StreamedResponse
    {
        $filename = 'finance_transactions_'.now()->format('Y-m-d_His').'.xls';
        $activeCurrency = $this->getActiveCurrency();

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $output = fopen('php://output', 'w');

            // XML Spreadsheet 2003 / SpreadsheetML header
            fwrite($output, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            fwrite($output, "<?mso-application progid=\"Excel.Sheet\"?>\n");
            fwrite($output, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
            fwrite($output, " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n");
            fwrite($output, " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n");
            fwrite($output, " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
            fwrite($output, " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n");

            // Styles
            fwrite($output, " <Styles>\n");
            fwrite($output, "  <Style ss:ID=\"Default\" ss:Name=\"Normal\">\n");
            fwrite($output, "   <Alignment ss:Vertical=\"Center\"/>\n");
            fwrite($output, "  </Style>\n");
            fwrite($output, "  <Style ss:ID=\"HeaderStyle\">\n");
            fwrite($output, "   <Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/>\n");
            fwrite($output, "   <Interior ss:Color=\"#1E293B\" ss:Pattern=\"Solid\"/>\n");
            fwrite($output, "   <Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/>\n");
            fwrite($output, "  </Style>\n");
            fwrite($output, "  <Style ss:ID=\"IncomeStyle\">\n");
            fwrite($output, "   <Font ss:Color=\"#059669\" ss:Bold=\"1\"/>\n");
            fwrite($output, "   <Alignment ss:Horizontal=\"Right\"/>\n");
            fwrite($output, "  </Style>\n");
            fwrite($output, "  <Style ss:ID=\"ExpenseStyle\">\n");
            fwrite($output, "   <Font ss:Color=\"#DC2626\" ss:Bold=\"1\"/>\n");
            fwrite($output, "   <Alignment ss:Horizontal=\"Right\"/>\n");
            fwrite($output, "  </Style>\n");
            fwrite($output, " </Styles>\n");

            fwrite($output, " <Worksheet ss:Name=\"Transactions\">\n");
            fwrite($output, "  <Table>\n");
            fwrite($output, "   <Column ss:Width=\"50\"/>\n");  // ID
            fwrite($output, "   <Column ss:Width=\"90\"/>\n");  // Date
            fwrite($output, "   <Column ss:Width=\"70\"/>\n");  // Type
            fwrite($output, "   <Column ss:Width=\"90\"/>\n");  // Amount
            fwrite($output, "   <Column ss:Width=\"60\"/>\n");  // Currency
            fwrite($output, "   <Column ss:Width=\"150\"/>\n"); // Category
            fwrite($output, "   <Column ss:Width=\"220\"/>\n"); // Description
            fwrite($output, "   <Column ss:Width=\"110\"/>\n"); // Payment Method
            fwrite($output, "   <Column ss:Width=\"90\"/>\n");  // Reference
            fwrite($output, "   <Column ss:Width=\"120\"/>\n"); // Recorded By

            // Header row
            fwrite($output, "   <Row ss:StyleID=\"HeaderStyle\" ss:Height=\"25\">\n");
            foreach (['ID', 'Date', 'Type', 'Amount', 'Currency', 'Category', 'Description', 'Payment Method', 'Reference', 'Recorded By'] as $col) {
                fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($col)."</Data></Cell>\n");
            }
            fwrite($output, "   </Row>\n");

            // Data rows
            $query->with('admin')->chunk(100, function ($transactions) use ($output) {
                foreach ($transactions as $tx) {
                    $typeStyle = $tx->type === 'income' ? 'IncomeStyle' : 'ExpenseStyle';
                    $amountPrefix = $tx->type === 'income' ? '+' : '-';
                    $formattedAmount = $amountPrefix.number_format((float) $tx->amount, 2);

                    fwrite($output, "   <Row>\n");
                    fwrite($output, "    <Cell><Data ss:Type=\"Number\">{$tx->id}</Data></Cell>\n");
                    fwrite($output, "    <Cell><Data ss:Type=\"String\">{$tx->transaction_date->toDateString()}</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.strtoupper($tx->type)."</Data></Cell>\n");
                    fwrite($output, "    <Cell ss:StyleID=\"{$typeStyle}\"><Data ss:Type=\"String\">".htmlspecialchars($formattedAmount)."</Data></Cell>\n");
                    fwrite($output, "    <Cell><Data ss:Type=\"String\">{$tx->currency}</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($tx->category_name)."</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($tx->description)."</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars(ucwords(str_replace('_', ' ', $tx->payment_method)))."</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($tx->reference ?? '-')."</Data></Cell>\n");
                    fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($tx->admin->name ?? 'System')."</Data></Cell>\n");
                    fwrite($output, "   </Row>\n");
                }
            });

            fwrite($output, "  </Table>\n");
            fwrite($output, " </Worksheet>\n");
            fwrite($output, "</Workbook>\n");

            fclose($output);
        }, 200, $headers);
    }
}
