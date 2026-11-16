<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'verification_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
            'fingerprint' => ['required', 'string', 'size:64'],
            'signer_name' => ['nullable', 'required_if:decision,accepted', 'string', 'max:255'],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
