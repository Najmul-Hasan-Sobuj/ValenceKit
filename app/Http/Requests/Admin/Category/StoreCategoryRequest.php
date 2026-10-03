<?php

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'name' => ['required', 'string', 'max:128'],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['module', 'type', 'parent_id'])) {
                    return;
                }

                $parentId = $this->integer('parent_id');
                if (! $parentId) {
                    return;
                }

                $parent = Category::find($parentId);
                if (! $parent) {
                    return;
                }

                if ($parent->module !== $this->string('module')->toString()) {
                    $validator->errors()->add('parent_id', 'Parent category must belong to the same module.');
                }

                $type = $this->input('type');
                if ($parent->type && $type && $parent->type !== $type) {
                    $validator->errors()->add('parent_id', 'Parent category must use the same type.');
                }
            },
        ];
    }
}
