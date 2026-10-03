<?php

namespace App\Http\Requests\Admin\Category;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin') && $this->user('admin')->can('categories.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module' => ['sometimes', 'required', 'string', 'max:64'],
            'type' => ['nullable', 'string', 'max:64'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:128'],
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

                $category = $this->route('category');
                if (! $category instanceof Category) {
                    return;
                }

                $parentId = $this->integer('parent_id');
                if (! $parentId) {
                    return;
                }

                if ($parentId === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');

                    return;
                }

                $parent = Category::find($parentId);
                if (! $parent) {
                    return;
                }

                if ($parent->module !== $this->input('module', $category->module)) {
                    $validator->errors()->add('parent_id', 'Parent category must belong to the same module.');
                }

                $type = $this->input('type', $category->type);
                if ($parent->type && $type && $parent->type !== $type) {
                    $validator->errors()->add('parent_id', 'Parent category must use the same type.');
                }

                $cursor = $parent;
                $visited = [];
                while ($cursor) {
                    if (isset($visited[$cursor->id])) {
                        break;
                    }

                    $visited[$cursor->id] = true;

                    if ($cursor->parent_id === $category->id) {
                        $validator->errors()->add('parent_id', 'A category cannot be nested under one of its descendants.');

                        return;
                    }

                    $cursor = $cursor->parent;
                }
            },
        ];
    }
}
