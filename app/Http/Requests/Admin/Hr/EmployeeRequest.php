<?php

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')->can($this->route('employee') ? 'employees.edit' : 'employees.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'designation' => ['required', 'string', 'max:255'],
            'manager_id' => ['nullable', 'integer', 'exists:employees,id', Rule::notIn([$this->route('employee')?->id])],
            'admin_id' => ['nullable', 'integer', 'exists:admins,id', Rule::unique('employees', 'admin_id')->ignore($this->route('employee'))],
            'joined_on' => ['required', 'date_format:Y-m-d'],
            'ended_on' => ['nullable', 'required_if:is_active,false', 'date_format:Y-m-d', 'after_or_equal:joined_on'],
            'is_active' => ['required', 'boolean'],
            'work_schedule_id' => [$this->route('employee') ? 'nullable' : 'required', Rule::exists('work_schedules', 'id')->where('is_active', true)],
            'effective_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_on'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
