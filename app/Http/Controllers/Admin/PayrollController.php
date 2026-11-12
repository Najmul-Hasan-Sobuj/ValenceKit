<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('payroll.view'), 403);

        return Inertia::render('admin/billing/Payroll', [
            'payrolls' => Payroll::query()->latest('id')->paginate(15)->withQueryString(),
            'employees' => $request->user('admin')->can('payroll.create') ? Employee::where('is_active', true)->orderBy('name')->get(['id', 'name', 'employee_code']) : [],
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('payroll.create'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m', Rule::unique('payrolls')->where('employee_id', $request->input('employee_id'))],
            'base_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'allowance_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'deduction_minor' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        if ((int) $data['deduction_minor'] > (int) $data['base_minor'] + (int) $data['allowance_minor']) {
            throw ValidationException::withMessages(['deduction_minor' => 'Deductions cannot exceed gross pay.']);
        }
        DB::transaction(function () use ($data, $audit, $request): void {
            $employee = Employee::findOrFail($data['employee_id']);
            $payroll = Payroll::create([...$data, 'employee_name' => $employee->name]);
            $audit->created('payroll.created', $payroll, actor: $request->user('admin'));
        });

        return back()->with('success', 'Payroll record created.');
    }
}
