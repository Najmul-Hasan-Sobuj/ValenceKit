<?php

namespace App\Http\Requests\Admin\Authorization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')->can('users.edit');
    }

    public function rules(): array
    {
        $adminId = $this->route('user')->id ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('admins')->ignore($adminId),
            ],
            'role' => [
                'required',
                'string',
                'exists:roles,name',
                function ($attribute, $value, $fail) {
                    if ($value === 'Super Admin' && ! $this->user('admin')->hasRole('Super Admin')) {
                        $fail('Only Super Admins can assign the Super Admin role.');
                    }
                },
            ],
            'is_active' => ['boolean'],
        ];
    }
}
