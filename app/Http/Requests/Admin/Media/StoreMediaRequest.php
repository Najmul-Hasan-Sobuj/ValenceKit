<?php

namespace App\Http\Requests\Admin\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')->can('media.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'],
            'owner_type' => ['nullable', 'required_with:owner_id', 'in:employee'],
            'owner_id' => ['nullable', 'required_with:owner_type', 'integer', 'min:1'],
            'collection' => ['required', 'in:avatar,gallery'],
            'folder' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $files = $this->file('files', []);
            if ($this->input('collection') === 'avatar' && is_array($files) && count($files) > 1) {
                $validator->errors()->add('files', 'Choose only one profile photo.');
            }
        }];
    }
}
