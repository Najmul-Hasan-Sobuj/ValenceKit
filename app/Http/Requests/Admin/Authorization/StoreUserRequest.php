<?php

namespace App\Http\Requests\Admin\Authorization;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

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
                'bail',
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $role = Role::where('guard_name', 'admin')->where('name', $value)->first();
                    if (! $role || ! $this->user('admin')->canDelegateRole($role)) {
                        $fail('You cannot assign this role.');
                    }
                },
            ],
            'is_active' => ['boolean'],
        ];
    }
}
