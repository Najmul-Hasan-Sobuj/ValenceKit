<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\AuditLogService;
use App\Services\WorkflowNotifier;
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
        $admin = $request->user('admin');
        abort_unless($admin->can('payroll.view') || $admin->can('hr.self'), 403);

        return Inertia::render('admin/billing/Payroll', [
            'payrolls' => Payroll::query()->when(! $admin->can('payroll.view'), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where('admin_id', $admin->id))->whereIn('status', ['approved', 'paid']))->latest('id')->paginate(15)->withQueryString(),
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
            $employee = Employee::whereKey($data['employee_id'])->firstOrFail();
            $payroll = Payroll::create([...$data, 'employee_name' => $employee->name, 'created_by' => $request->user('admin')->id, 'status' => 'draft']);
            $audit->created('payroll.created', $payroll, actor: $request->user('admin'));
            app(WorkflowNotifier::class)->admins('payroll.approve', 'Payroll awaiting approval', 'A payroll draft is ready for review.', 'admin.payroll.index');
        });

        return back()->with('success', 'Payroll record created.');
    }

    public function approve(Request $request, Payroll $payroll, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('payroll.approve'), 403);
        DB::transaction(function () use ($request, $payroll, $audit): void {
            $payroll = Payroll::lockForUpdate()->findOrFail($payroll->id);
            if ($payroll->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft payroll can be approved.']);
            }
            $before = $payroll->auditLogValues();
            $payroll->update(['status' => 'approved', 'approved_by' => $request->user('admin')->id, 'approved_at' => now()]);
            $audit->updated('payroll.approved', $payroll, $before, actor: $request->user('admin'));
            $this->notifyEmployee($payroll);
        });

        return back()->with('success', 'Payroll approved.');
    }

    public function pay(Request $request, Payroll $payroll, AuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('admin')->can('payroll.pay'), 403);
        $data = $request->validate(['paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'payment_reference' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $payroll, $audit, $data): void {
            $payroll = Payroll::lockForUpdate()->findOrFail($payroll->id);
            if ($payroll->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Approve this payroll before recording payment.']);
            }
            $before = $payroll->auditLogValues();
            $payroll->update([...$data, 'status' => 'paid', 'paid_by' => $request->user('admin')->id]);
            $audit->updated('payroll.paid', $payroll, $before, actor: $request->user('admin'));
            $this->notifyEmployee($payroll);
        });

        return back()->with('success', 'Payroll payment recorded.');
    }

    private function notifyEmployee(Payroll $payroll): void
    {
        $admin = $payroll->employee?->admin;
        if ($admin) {
            app(WorkflowNotifier::class)->account($admin, 'hr.self', 'Payslip updated', 'Your '.$payroll->period.' payslip is '.$payroll->status.'.', 'admin.payroll.index');
        }
    }
}
