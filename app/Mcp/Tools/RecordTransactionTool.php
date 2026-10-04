<?php

namespace App\Mcp\Tools;

use App\Models\Admin;
use App\Services\FinanceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record a new financial income or expense transaction with amount, category, and description.')]
class RecordTransactionTool extends BaseDbTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        if ($denied = $this->denyUnlessAllowed($request, 'mcp:finance.write')) {
            return $denied;
        }

        $type = strtolower((string) $request->get('type'));
        $amount = (float) $request->get('amount');
        $description = (string) $request->get('description');
        $categoryKey = (string) $request->get('category', '');
        $paymentMethod = (string) $request->get('payment_method', 'card');

        if (! in_array($type, ['income', 'expense'], true)) {
            return Response::text("Error: Type must be either 'income' or 'expense'. Received: {$type}");
        }

        if ($amount <= 0) {
            return Response::text('Error: Amount must be greater than zero.');
        }

        $financeService = app(FinanceService::class);
        $currency = $financeService->getActiveCurrency();
        $admin = $request->user('mcp') ?? $request->user('sanctum');

        if (! $admin instanceof Admin) {
            return Response::text('Error: Recording transactions requires an admin MCP token.');
        }

        if (empty($categoryKey)) {
            $categories = $financeService->getCategories();
            $categoryKey = $type === 'income'
                ? ($categories['income'][0]['id'] ?? 'other_income')
                : ($categories['expense'][0]['id'] ?? 'other_expense');
        }

        $category = $financeService->findActiveCategory($categoryKey, $type);
        if (! $category) {
            return Response::text("Error: Active {$type} category not found for '{$categoryKey}'.");
        }

        $transaction = $financeService->createTransaction([
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'category_key' => $categoryKey,
            'payment_method' => $paymentMethod,
            'transaction_date' => now()->toDateString(),
        ], $admin->id);

        $formattedAmount = number_format($amount, 2);

        return Response::text(
            "Transaction #{$transaction->id} recorded successfully: {$type} of {$currency['symbol']}{$formattedAmount} for '{$description}' under category '{$transaction->category_name}'."
        );
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description("Transaction type: 'income' or 'expense'")->required(),
            'amount' => $schema->number()->description('The monetary amount of the transaction')->required(),
            'description' => $schema->string()->description('Brief description or reason for the transaction')->required(),
            'category' => $schema->string()->description("Category identifier (e.g. 'software_saas', 'cloud_infra', 'client_retainer', 'marketing')"),
            'payment_method' => $schema->string()->description("Payment method ('card', 'bank_transfer', 'cash', 'bkash_nagad', 'other')"),
        ];
    }
}
