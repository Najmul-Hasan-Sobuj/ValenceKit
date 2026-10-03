<?php

namespace App\Http\Requests\Admin\Finance;

use App\Models\FinanceTransaction;
use App\Services\FinanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFinanceTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')->can('finance.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::in(['income', 'expense'])],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'category_key' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'payment_method' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('finance.payment_methods', [])))],
            'is_recurring' => ['boolean'],
            'recurring_frequency' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'yearly'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'category_key'])) {
                    return;
                }

                $transaction = $this->route('transaction');
                if (! $transaction instanceof FinanceTransaction || ! $this->filled('category_key')) {
                    return;
                }

                $category = app(FinanceService::class)->findActiveCategory(
                    (string) $this->input('category_key'),
                    (string) $this->input('type', $transaction->type),
                );

                if (! $category) {
                    $validator->errors()->add('category_key', 'Select an active category that matches the transaction type.');
                }
            },
        ];
    }
}
