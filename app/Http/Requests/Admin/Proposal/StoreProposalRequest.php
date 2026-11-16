<?php

namespace App\Http\Requests\Admin\Proposal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('proposals.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'max:100', 'unique:proposals,number'],
            'type' => ['required', Rule::in(['proposal', 'quotation', 'contract'])],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:description,quantity,unit_price_minor'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:1000000'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'discount_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'tax_rate_basis_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'terms' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
