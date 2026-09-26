<?php

namespace App\Http\Requests\Admin\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')->can('users.invite');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
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
