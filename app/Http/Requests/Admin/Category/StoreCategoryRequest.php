<?php

namespace App\Http\Requests\Admin\Category;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin') && $this->user('admin')->can('categories.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'slug' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('categories', 'slug')->where('module', $this->input('module')),
            ],
            'name' => ['required', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
