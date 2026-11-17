<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Hr\EmployeeRequest;
use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkSchedule;
use App\Services\AuditLogService;
use App\Services\HrService;
use App\Services\WorkflowNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrController extends Controller
{
    public function __construct(private HrService $hr, private AuditLogService $audit) {}

    public function index(Request $request): Response
    {
        $actor = $request->user('admin');
        abort_unless($actor->can('employees.view') || $actor->can('hr.self') || $actor->can('hr.team') || $actor->can('hr.settings.edit'), 403);
        $filters = $request->validate(['section' => ['nullable', Rule::in(['overview', 'employees', 'attendance', 'leave', 'reports', 'settings', 'workspace'])], 'search' => ['nullable', 'string', 'max:100'], 'date' => ['nullable', 'date_format:Y-m-d'], 'status' => ['nullable', 'string', 'max:30'], 'department_id' => ['nullable', 'integer'], 'employee_id' => ['nullable', 'integer'], 'month' => ['nullable', 'date_format:Y-m']]);
        $range = $request->validate(['from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'], 'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $filters += $range;
        $section = $filters['section'] ?? ($actor->can('employees.view') ? 'overview' : 'workspace');
        if ($section === 'settings') {
            abort_unless($actor->can('hr.settings.edit'), 403);
        }
        $query = $this->hr->employees($actor)->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$v.'%')->orWhere('employee_code', 'like', '%'.$v.'%')))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v));
        if ($section === 'workspace') {
            $query->where('admin_id', $actor->id);
        }
        if ($filters['employee_id'] ?? null) {
            $query->whereKey($filters['employee_id']);
        }
        $ids = (clone $query)->select('employees.id');
        $date = $filters['date'] ?? now('Asia/Dhaka')->toDateString();
        $month = $filters['month'] ?? substr($date, 0, 7);
        $start = $filters['from'] ?? ($section === 'reports' ? $month.'-01' : $date);
        $end = $filters['to'] ?? ($section === 'reports' ? CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString() : $date);
        $this->hr->check(CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) <= 366, 'to', 'Select a range of at most 367 days.');
        $employees = (clone $query)->with('department:id,name')->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
        $leaves = LeaveRequest::whereIn('employee_id', clone $ids)->with(['employee:id,name,employee_code,admin_id,manager_id', 'leaveType:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when(isset($filters['from']), fn ($q) => $q->where('start_date', '<=', $end)->where('end_date', '>=', $start))->latest('id')->paginate(15, ['*'], 'leave_page')->withQueryString();
        $presentCount = Attendance::whereIn('employee_id', clone $ids)->whereBetween('work_date', [$start, $end])->distinct()->count('employee_id');
        $onLeaveCount = LeaveRequest::whereIn('employee_id', clone $ids)->whereIn('status', ['approved', 'cancellation_requested'])->where('start_date', '<=', $end)->where('end_date', '>=', $start)->get(['employee_id', 'dates'])->filter(fn ($leave) => collect($leave->dates)->contains(fn ($day) => $day >= $start && $day <= $end))->pluck('employee_id')->unique()->count();
        $allocations = LeaveAllocation::whereIn('employee_id', clone $ids)->with(['employee:id,name', 'leaveType:id,name'])->where('year', substr($date, 0, 4))->get();
        $allocations->each(fn ($allocation) => $allocation->setAttribute('reserved', $this->hr->reserved($allocation->employee_id, $allocation->leave_type_id, $allocation->year)));
        $own = Employee::where('admin_id', $actor->id)->first(['id', 'name', 'is_active']);
        $clockState = ['checked_in' => false, 'checked_out' => false];
        if ($own) {
            $assignment = DB::table('employee_schedules')->where('employee_id', $own->id)->orderByDesc('effective_from')->get()->first(fn ($assignment) => $assignment->effective_from <= now(json_decode($assignment->snapshot, true)['timezone'])->toDateString());
            $timezone = $assignment ? json_decode($assignment->snapshot, true)['timezone'] : 'Asia/Dhaka';
            $todayAttendance = Attendance::where('employee_id', $own->id)->where('work_date', now($timezone)->toDateString())->first();
            $clockState = ['checked_in' => $todayAttendance !== null, 'checked_out' => $todayAttendance?->check_out !== null];
        }
        $reportRows = [];
        $reportPaginator = null;
        if (in_array($section, ['overview', 'attendance', 'reports', 'workspace'])) {
            $currentPage = max(1, (int) $request->query('report_page', 1));
            $perPage = 15;
            $offset = ($currentPage - 1) * $perPage;
            $rows = [];
            $total = 0;
            (clone $query)->reorder()->chunkById(50, function (Collection $employees) use ($start, $end, $offset, $perPage, &$rows, &$total): void {
                $batch = $this->hr->report($employees, $start, $end);
                $batchCount = count($batch);
                if ($total < $offset + $perPage && $total + $batchCount > $offset) {
                    $rows = array_merge($rows, array_slice($batch, max(0, $offset - $total), $perPage - count($rows)));
                }
                $total += $batchCount;
            });
            $reportPaginator = (new LengthAwarePaginator($rows, $total, $perPage, $currentPage, ['path' => $request->url(), 'pageName' => 'report_page']))->withQueryString();
            $reportRows = $reportPaginator->items();
        }
        $calendar = $section === 'leave' ? LeaveRequest::whereIn('employee_id', clone $ids)->with('employee:id,name')->whereIn('status', ['approved', 'cancellation_requested'])->where('start_date', '<=', CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString())->where('end_date', '>=', $month.'-01')->get()->flatMap(fn ($leave) => collect($leave->dates)->filter(fn ($date) => str_starts_with($date, $month))->map(fn ($date) => ['date' => $date, 'name' => $leave->employee->name]))->values() : [];

        return Inertia::render('admin/hr/Index', [
            'section' => $section, 'filters' => $filters, 'date' => $date, 'month' => $month, 'rangeStart' => $start, 'rangeEnd' => $end,
            'clockState' => $clockState,
            'employeeChoices' => $this->hr->employees($actor)->orderBy('name')->get(['id', 'name']),
            'calendar' => $calendar, 'reportLinks' => $reportPaginator?->linkCollection() ?? [],
            'reportPagination' => $reportPaginator ? ['from' => $reportPaginator->firstItem(), 'to' => $reportPaginator->lastItem(), 'total' => $reportPaginator->total(), 'currentPage' => $reportPaginator->currentPage(), 'lastPage' => $reportPaginator->lastPage()] : null,
            'employees' => $employees->through(fn ($employee) => $employee->only(['id', 'employee_code', 'name', 'email', 'phone', 'department_id', 'department', 'designation', 'manager_id', 'admin_id', 'joined_on', 'ended_on', 'is_active'])),
            'leaves' => $leaves, 'allocations' => $allocations, 'own' => $own,
            'corrections' => AttendanceCorrection::whereIn('employee_id', clone $ids)->with('employee:id,name')->where('status', 'pending')->orderBy('id')->paginate(15, ['*'], 'correction_page')->withQueryString(),
            'departments' => Department::orderBy('name')->get(), 'schedules' => WorkSchedule::orderBy('name')->get(),
            'holidays' => Holiday::whereYear('date', substr($date, 0, 4))->orderBy('date')->get(), 'leaveTypes' => LeaveType::orderBy('name')->get(),
            'accounts' => $actor->can('employees.edit') || $actor->can('employees.create') ? Admin::orderBy('name')->get(['id', 'name']) : [],
            'report' => $reportRows,
            'totals' => ['active' => (clone $query)->where('is_active', true)->count(), 'present' => $presentCount, 'on_leave' => $onLeaveCount, 'pending' => LeaveRequest::whereIn('employee_id', clone $ids)->whereIn('status', ['pending', 'cancellation_requested'])->count()],
        ]);
    }

    public function employee(EmployeeRequest $request, ?Employee $employee = null): RedirectResponse
    {
        $created = DB::transaction(function () use ($request, $employee): int {
            $data = $request->validated();
            $before = $employee?->auditLogValues() ?? [];
            $scheduleId = $data['work_schedule_id'] ?? null;
            $effective = $data['effective_from'] ?? $data['joined_on'];
            unset($data['work_schedule_id'], $data['effective_from']);
            if ($employee) {
                $employee = Employee::lockForUpdate()->findOrFail($employee->id);
                $hasHistory = Attendance::where('employee_id', $employee->id)->exists() || LeaveRequest::where('employee_id', $employee->id)->exists();
                $this->hr->check(! $hasHistory || $employee->joined_on === $data['joined_on'], 'joined_on', 'Joining date cannot change after HR history exists.');
                if (! empty($data['ended_on'])) {
                    $this->hr->check(! Attendance::where('employee_id', $employee->id)->where('work_date', '>', $data['ended_on'])->exists() && ! LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved', 'cancellation_requested'])->where('end_date', '>', $data['ended_on'])->exists(), 'ended_on', 'Resolve attendance and active leave after this end date first.');
                }
                $managerId = $data['manager_id'] ?? null;
                $seen = [$employee->id];
                while ($managerId) {
                    $this->hr->check(! in_array($managerId, $seen), 'manager_id', 'Managers cannot form a reporting cycle.');
                    $seen[] = $managerId;
                    $managerId = Employee::whereKey($managerId)->value('manager_id');
                }
                $employee->update($data);
            } else {
                $employee = Employee::create(['employee_code' => 'EMP-'.Str::upper(Str::random(8)), ...$data]);
            }
            if ($scheduleId) {
                $this->hr->check(! DB::table('employee_schedules')->where('employee_id', $employee->id)->where('effective_from', '>=', $effective)->exists(), 'effective_from', 'Schedule changes must follow existing assignments.');
                $this->hr->check(! Attendance::where('employee_id', $employee->id)->where('work_date', '>=', $effective)->exists() && ! LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved', 'cancellation_requested'])->where('end_date', '>=', $effective)->exists(), 'effective_from', 'Choose a date after existing attendance and active leave requests.');
                $schedule = WorkSchedule::findOrFail($scheduleId);
                DB::table('employee_schedules')->insert(['employee_id' => $employee->id, 'work_schedule_id' => $scheduleId, 'effective_from' => $effective, 'snapshot' => json_encode($schedule->only(['start_time', 'end_time', 'weekdays', 'grace_minutes', 'timezone']))]);
            }
            $this->audit->updated('hr.employee.saved', $employee, $before);

            return $employee->id;
        });

        return back()->with('success', 'Employee saved.')->with('hr_created', ['kind' => 'employee', 'id' => $created]);
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->can('hr.settings.edit'), 403);
        $control = $request->validate(['kind' => ['required', Rule::in(['department', 'schedule', 'holiday', 'leave-type', 'allocation'])], 'id' => ['nullable', 'integer']]);
        $kind = $control['kind'];
        $id = $control['id'] ?? null;
        $rules = match ($kind) {
            'department' => ['name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($id)], 'is_active' => ['sometimes', 'boolean']],
            'schedule' => ['name' => ['required', 'string', 'max:255'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'weekdays' => ['required', 'array', 'min:1', 'max:7'], 'weekdays.*' => ['integer', 'between:1,7', 'distinct'], 'grace_minutes' => ['required', 'integer', 'between:0,120'], 'timezone' => ['required', 'timezone']],
            'holiday' => ['name' => ['required', 'string', 'max:255'], 'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', Rule::unique('holidays', 'date')->ignore($id)]],
            'leave-type' => ['name' => ['required', 'string', 'max:255', Rule::unique('leave_types', 'name')->ignore($id)], 'is_paid' => ['required', 'boolean'], 'is_active' => ['sometimes', 'boolean']],
            'allocation' => ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'], 'year' => ['required', 'integer', 'between:2000,2100'], 'days' => ['required', 'integer', 'between:0,366'], 'reason' => ['required', 'string', 'max:255']],
            default => abort(422),
        };
        $data = $request->validate($rules);
        $created = DB::transaction(function () use ($kind, $data, $id): int {
            if ($kind === 'allocation') {
                Employee::lockForUpdate()->findOrFail($data['employee_id']);
                $this->hr->check($data['days'] >= $this->hr->reserved($data['employee_id'], $data['leave_type_id'], $data['year']), 'days', 'Allowance cannot be less than reserved leave.');
                $record = LeaveAllocation::firstOrNew(array_intersect_key($data, array_flip(['employee_id', 'leave_type_id', 'year'])));
                $before = $record->auditLogValues();
                $record->fill($data)->save();
                $this->audit->updated('hr.allocation.saved', $record, $before);
            } else {
                $class = match ($kind) {
                    'department' => Department::class, 'schedule' => WorkSchedule::class, 'holiday' => Holiday::class, default => LeaveType::class
                };
                if ($kind === 'holiday') {
                    $this->hr->check(! LeaveRequest::whereIn('status', ['pending', 'approved', 'cancellation_requested'])->where('start_date', '<=', $data['date'])->where('end_date', '>=', $data['date'])->exists(), 'date', 'Resolve overlapping leave requests before adding this holiday.');
                }
                $record = $id ? $class::lockForUpdate()->whereKey($id)->firstOrFail() : new $class;
                if ($id && $record instanceof Holiday) {
                    $this->hr->check($record->date >= now('Asia/Dhaka')->toDateString() && ! LeaveRequest::whereIn('status', ['pending', 'approved', 'cancellation_requested'])->where('start_date', '<=', $record->date)->where('end_date', '>=', $record->date)->exists(), 'date', 'Historical or referenced holidays cannot change.');
                }
                $before = $record->auditLogValues();
                $record->fill($data)->save();
                $this->audit->updated('hr.'.$kind.'.saved', $record, $before);
            }

            return $record->id;
        });

        return back()->with('success', 'HR settings saved.')->with('hr_created', ['kind' => $kind, 'id' => $created]);
    }

    public function deleteSetting(Request $request, string $kind, int $id): RedirectResponse
    {
        abort_unless($request->user('admin')->can('hr.settings.edit'), 403);
        $class = match ($kind) {
            'department' => Department::class, 'schedule' => WorkSchedule::class, 'holiday' => Holiday::class, 'leave-type' => LeaveType::class, default => abort(404)
        };
        DB::transaction(function () use ($class, $kind, $id): void {
            $record = $class::lockForUpdate()->whereKey($id)->firstOrFail();
            $used = match ($kind) {
                'department' => Employee::where('department_id', $id)->exists(),
                'schedule' => DB::table('employee_schedules')->where('work_schedule_id', $id)->exists(),
                'leave-type' => LeaveRequest::where('leave_type_id', $id)->exists() || LeaveAllocation::where('leave_type_id', $id)->exists(),
                'holiday' => $record instanceof Holiday && ($record->date < now('Asia/Dhaka')->toDateString() || LeaveRequest::whereIn('status', ['pending', 'approved', 'cancellation_requested'])->where('start_date', '<=', $record->date)->where('end_date', '>=', $record->date)->exists()),
            };
            $this->hr->check(! $used, 'record', 'This record is used by HR history and cannot be deleted.');
            $this->audit->deleted('hr.'.$kind.'.deleted', $record, $record->auditLogValues());
            $record->delete();
        });

        return back()->with('success', 'HR setting deleted.');
    }

    public function leave(Request $request): RedirectResponse
    {
        $actor = $request->user('admin');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->where('is_active', true)], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'reason' => ['required', 'string', 'max:2000']]);
        $employee = $this->hr->requireEmployee($actor, $data['employee_id']);
        abort_unless($actor->can('leave.manage') || ($actor->can('hr.self') && $employee->admin_id === $actor->id), 403);
        DB::transaction(function () use ($employee, $data): void {
            $leave = $this->hr->requestLeave($employee, $data);
            $this->audit->created('hr.leave.requested', $leave);
            app(WorkflowNotifier::class)->leaveRequested($leave);
        });

        return back()->with('success', 'Leave request submitted.');
    }

    public function review(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->hr->requireEmployee($request->user('admin'), $leave->employee_id);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'cancel'])], 'note' => ['nullable', 'required_if:action,reject', 'string', 'max:2000']]);
        $this->hr->reviewLeave($request->user('admin'), $leave, $data['action'], $data['note'] ?? null);

        return back()->with('success', 'Leave request updated.');
    }

    public function clock(Request $request): RedirectResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor->can('hr.self'), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['in', 'out'])]]);
        DB::transaction(function () use ($actor, $data): void {
            $employee = Employee::where('admin_id', $actor->id)->lockForUpdate()->firstOrFail();
            $assignments = DB::table('employee_schedules')->where('employee_id', $employee->id)->orderByDesc('effective_from')->get();
            $current = $assignments->first(fn ($assignment) => $assignment->effective_from <= now(json_decode($assignment->snapshot, true)['timezone'])->toDateString());
            $timezone = $current ? json_decode($current->snapshot, true)['timezone'] : 'Asia/Dhaka';
            $date = now($timezone)->toDateString();
            $schedule = $this->hr->schedule($employee, $date);
            $record = Attendance::where('employee_id', $employee->id)->where('work_date', $date)->first();
            $this->hr->check($data['action'] === 'in' ? ! $record : ($record && ! $record->check_out), 'action', 'Already checked in/out, or check-in is missing.');
            $before = $record?->auditLogValues() ?? [];
            $saved = $this->hr->saveAttendance($employee, ['work_date' => $date, 'check_in' => $record ? $record->check_in->setTimezone($schedule['timezone'])->format('Y-m-d H:i:s') : now($schedule['timezone'])->format('Y-m-d H:i:s'), 'check_out' => $data['action'] === 'out' ? now($schedule['timezone'])->format('Y-m-d H:i:s') : null]);
            $this->audit->updated('hr.attendance.clock-'.$data['action'], $saved, $before);
        });

        return back()->with('success', 'Attendance recorded.');
    }

    public function attendance(Request $request): RedirectResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d'], 'check_in' => ['required', 'date_format:Y-m-d\TH:i'], 'check_out' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:check_in'], 'reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user('admin');
        $employee = $this->hr->requireEmployee($actor, $data['employee_id'], true);
        DB::transaction(function () use ($actor, $employee, $data): void {
            $employee = Employee::lockForUpdate()->findOrFail($employee->id);
            if ($actor->can('attendance.manage')) {
                $before = Attendance::where('employee_id', $employee->id)->where('work_date', $data['work_date'])->first()?->auditLogValues() ?? [];
                $this->audit->updated('hr.attendance.corrected', $this->hr->saveAttendance($employee, $data), $before);
            } else {
                $schedule = $this->hr->schedule($employee, $data['work_date']);
                $in = CarbonImmutable::parse($data['check_in'], $schedule['timezone']);
                $out = empty($data['check_out']) ? null : CarbonImmutable::parse($data['check_out'], $schedule['timezone']);
                $this->hr->check($employee->is_active && $data['work_date'] >= $employee->joined_on && (! $employee->ended_on || $data['work_date'] <= $employee->ended_on), 'work_date', 'Select a date within active employment.');
                $this->hr->check($in->toDateString() === $data['work_date'] && (! $out || $out->toDateString() === $data['work_date']) && $in <= now($schedule['timezone']) && (! $out || $out <= now($schedule['timezone'])), 'check_in', 'Use past times on the selected work date.');
                $this->hr->check(! AttendanceCorrection::where('employee_id', $employee->id)->where('work_date', $data['work_date'])->where('status', 'pending')->exists(), 'work_date', 'A correction is already pending.');
                $this->audit->created('hr.correction.requested', AttendanceCorrection::create([...$data, 'check_in' => CarbonImmutable::parse($data['check_in'], $schedule['timezone'])->utc(), 'check_out' => empty($data['check_out']) ? null : CarbonImmutable::parse($data['check_out'], $schedule['timezone'])->utc(), 'status' => 'pending']));
            }
        });

        return back()->with('success', $actor->can('attendance.manage') ? 'Attendance saved.' : 'Correction submitted for review.');
    }

    public function correction(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        abort_unless($request->user('admin')->can('attendance.manage'), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['nullable', 'required_if:action,reject', 'string', 'max:2000']]);
        $this->hr->requireEmployee($request->user('admin'), $correction->employee_id);
        DB::transaction(function () use ($request, $correction, $data): void {
            $employee = Employee::lockForUpdate()->findOrFail($correction->employee_id);
            $correction = AttendanceCorrection::lockForUpdate()->findOrFail($correction->id);
            $this->hr->check($correction->status === 'pending', 'action', 'This correction was already reviewed.');
            if ($data['action'] === 'approve') {
                $timezone = $this->hr->schedule($employee, $correction->work_date)['timezone'];
                $before = Attendance::where('employee_id', $employee->id)->where('work_date', $correction->work_date)->first()?->auditLogValues() ?? [];
                $saved = $this->hr->saveAttendance($employee, ['work_date' => $correction->work_date, 'check_in' => $correction->check_in->setTimezone($timezone)->format('Y-m-d H:i:s'), 'check_out' => $correction->check_out?->setTimezone($timezone)->format('Y-m-d H:i:s'), 'reason' => $correction->reason]);
                $this->audit->updated('hr.attendance.corrected', $saved, $before);
            }
            $before = $correction->auditLogValues();
            $correction->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected', 'reviewer_id' => $request->user('admin')->id, 'review_note' => $data['note'] ?? null]);
            $this->audit->updated('hr.correction.reviewed', $correction, $before);
        });

        return back()->with('success', 'Correction reviewed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user('admin')->can('hr.export'), 403);
        $data = $request->validate(['month' => ['nullable', 'required_without:from', 'date_format:Y-m'], 'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'], 'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'], 'employee_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer']]);
        $start = $data['from'] ?? $data['month'].'-01';
        $end = $data['to'] ?? CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $this->hr->check(CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) <= 366, 'to', 'Select a range of at most 367 days.');
        $query = $this->hr->employees($request->user('admin'))->when($data['employee_id'] ?? null, fn ($q, $v) => $q->whereKey($v))->when($data['department_id'] ?? null, fn ($q, $v) => $q->where('department_id', $v));

        return response()->streamDownload(function () use ($query, $start, $end): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Cannot open CSV output.');
            }
            fputcsv($file, ['Employee ID', 'Name', 'Date', 'Status', 'Check-in', 'Check-out', 'Duration minutes', 'Late minutes', 'Timezone'], escape: '');
            foreach ($query->orderBy('id')->lazy(100) as $employee) {
                foreach ($this->hr->report(collect([$employee]), $start, $end) as $row) {
                    fputcsv($file, array_map(fn ($value) => preg_match('/^[\s=+@-]/u', (string) $value) ? "'".$value : $value, array_values($row)), escape: '');
                }
            }
            fclose($file);
        }, 'attendance-'.$start.'-'.$end.'.csv', ['Content-Type' => 'text/csv']);
    }
}
