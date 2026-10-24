<?php

namespace App\Mcp\Tools;

use App\Models\Admin;
use App\Services\AuditLogService;
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

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'description' => ['required', 'string', 'min:1', 'max:255'],
            'category' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'payment_method' => ['nullable', 'string', 'in:card,bank_transfer,cash,bkash_nagad,other'],
        ]);

        $type = strtolower((string) $validated['type']);
        $amount = (float) $validated['amount'];
        $description = (string) $validated['description'];
        $categoryKey = (string) ($validated['category'] ?? '');
        $paymentMethod = (string) ($validated['payment_method'] ?? 'card');

        $financeService = app(FinanceService::class);
        $currency = $financeService->getActiveCurrency();
        $admin = rescue(static fn () => $request->user('mcp'), null, false) ?? $request->user('sanctum');

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

        app(AuditLogService::class)->record(
            'finance.transaction.created',
            $transaction,
            afterValues: $transaction->only(['type', 'amount', 'currency', 'category_key', 'description', 'reference', 'transaction_date', 'payment_method']),
            description: 'A finance transaction was recorded through MCP.',
            actor: $admin,
        );

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
            'type' => $schema->string()->description("Transaction type: 'income' or 'expense'")->enum(['income', 'expense'])->required(),
            'amount' => $schema->number()->description('The monetary amount of the transaction')->minimum(0.01)->maximum(999999999.99)->required(),
            'description' => $schema->string()->description('Brief description or reason for the transaction')->minLength(1)->maxLength(255)->required(),
            'category' => $schema->string()->description("Category identifier (e.g. 'software_saas', 'cloud_infra', 'client_retainer', 'marketing')")->maxLength(64),
            'payment_method' => $schema->string()->description("Payment method ('card', 'bank_transfer', 'cash', 'bkash_nagad', 'other')")->enum(['card', 'bank_transfer', 'cash', 'bkash_nagad', 'other']),
        ];
    }
}
