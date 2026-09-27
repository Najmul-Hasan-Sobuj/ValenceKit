<?php

namespace App\Http\Requests\Admin\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')->can('finance.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_key' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(config('finance.payment_methods', [])))],
            'is_recurring' => ['boolean'],
            'recurring_frequency' => ['nullable', 'string', Rule::in(['weekly', 'monthly', 'yearly'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
