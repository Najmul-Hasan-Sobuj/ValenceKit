<?php

namespace App\Http\Requests\Admin\Authorization;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

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

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $targetAdmin = $this->route('user');
                $currentAdmin = $this->user('admin');

                if (! $targetAdmin instanceof Admin || ! $currentAdmin instanceof Admin) {
                    return;
                }

                if (! $currentAdmin->canDelegatePermissions($targetAdmin->getAllPermissions()->pluck('name')->all())) {
                    $validator->errors()->add('role', 'You cannot manage an administrator with permissions you do not have.');
                }

                if ($currentAdmin->is($targetAdmin) && $this->boolean('is_active') === false) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                if ($targetAdmin->is_active && $targetAdmin->hasRole('Super Admin') && Admin::role('Super Admin')->where('is_active', true)->count() <= 1) {
                    if ($this->input('role') !== 'Super Admin') {
                        $validator->errors()->add('role', 'Cannot remove the last Super Admin role.');
                    }
                    if ($this->boolean('is_active') === false) {
                        $validator->errors()->add('is_active', 'Cannot deactivate the only remaining Super Admin.');
                    }
                }
            },
        ];
    }
}
