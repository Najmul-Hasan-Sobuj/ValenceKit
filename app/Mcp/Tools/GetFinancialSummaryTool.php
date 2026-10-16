<?php

namespace App\Mcp\Tools;

use App\Services\FinanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get real-time financial cashflow summary, net balance, and budget health for the current or specified month.')]
class GetFinancialSummaryTool extends BaseDbTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessAllowed($request, 'mcp:finance.read')) {
            return $denied;
        }

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);

        $date = Carbon::createFromDate($year, $month, 1);
        $financeService = app(FinanceService::class);
        $summary = $financeService->getMonthlySummary($date);
        $currency = $summary['currency'];

        $income = number_format($summary['total_income'], 2);
        $expense = number_format($summary['total_expense'], 2);
        $net = number_format($summary['net_balance'], 2);
        $budget = number_format($summary['budget_total'], 2);
        $percent = $summary['budget_spent_percentage'];

        $monthName = $date->format('F Y');

        $output = "Financial Summary for {$monthName}:\n";
        $output .= "• Active Currency: {$currency['name']} ({$currency['symbol']} {$currency['code']})\n";
        $output .= "• Total Income: +{$currency['symbol']}{$income}\n";
        $output .= "• Total Expenses: -{$currency['symbol']}{$expense}\n";
        $output .= '• Net Cash Flow: '.($summary['net_balance'] >= 0 ? '+' : '')."{$currency['symbol']}{$net}\n";
        $output .= "• Monthly Budget: {$currency['symbol']}{$budget} ({$percent}% utilized)\n";
        $output .= "• Avg Daily Burn Rate: {$currency['symbol']}".number_format($summary['burn_rate'], 2)." / day\n";
        $output .= "• Days Remaining in Month: {$summary['days_remaining']}";

        return Response::text($output);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer('Calendar year (defaults to current year)')->minimum(2000)->maximum(2100),
            'month' => $schema->integer('Calendar month number (1-12, defaults to current month)')->minimum(1)->maximum(12),
        ];
    }
}
