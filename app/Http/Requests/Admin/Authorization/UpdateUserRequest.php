<?php

namespace App\Http\Requests\Admin\Authorization;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

                if ($currentAdmin->is($targetAdmin) && $this->boolean('is_active') === false) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                if ($targetAdmin->hasRole('Super Admin')
                    && $this->input('role') !== 'Super Admin'
                    && Admin::role('Super Admin')->count() <= 1) {
                    $validator->errors()->add('role', 'Cannot remove the last Super Admin role.');
                }
            },
        ];
    }
}
