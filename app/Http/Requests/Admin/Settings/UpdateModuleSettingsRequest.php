<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('settings.modules.edit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*' => ['required', 'boolean'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function ($validator): void {
            $unknownModules = array_diff(array_keys($this->array('modules')), array_keys(config('modules', [])));

            if ($unknownModules !== []) {
                $validator->errors()->add('modules', 'One or more modules are not supported.');
            }
        }];
    }
}
