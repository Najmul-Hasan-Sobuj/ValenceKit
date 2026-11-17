<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrService
{
    /** @param Collection<int, Employee> $employees
     * @return list<array<string, mixed>>
     */
    public function report(Collection $employees, string $start, string $end): array
    {
        $rows = [];
        $assignments = DB::table('employee_schedules')->whereIn('employee_id', $employees->pluck('id'))->where('effective_from', '<=', $end)->orderByDesc('effective_from')->get()->groupBy('employee_id');
        $holidays = Holiday::whereBetween('date', [$start, $end])->pluck('date')->all();
        $records = Attendance::whereIn('employee_id', $employees->pluck('id'))->whereBetween('work_date', [$start, $end])->get()->keyBy(fn ($a) => $a->employee_id.'|'.$a->work_date);
        $leaves = LeaveRequest::whereIn('employee_id', $employees->pluck('id'))->whereIn('status', ['approved', 'cancellation_requested'])->where('start_date', '<=', $end)->where('end_date', '>=', $start)->get();
        foreach ($employees as $employee) {
            for ($day = CarbonImmutable::parse($start); $day->toDateString() <= $end; $day = $day->addDay()) {
                $date = $day->toDateString();
                if ($date < $employee->joined_on || ($employee->ended_on && $date > $employee->ended_on)) {
                    continue;
                }
                $record = $records->get($employee->id.'|'.$date);
                $assignment = $assignments->get($employee->id, collect())->first(fn ($assignment) => $assignment->effective_from <= $date);
                if (! $record && ! $assignment) {
                    continue;
                }
                $schedule = $record->schedule ?? json_decode($assignment->snapshot, true);
                $working = in_array($day->dayOfWeekIso, $schedule['weekdays']) && ! in_array($date, $holidays);
                $late = $record ? max(0, (int) CarbonImmutable::parse($date.' '.$schedule['start_time'], $schedule['timezone'])->addMinutes($schedule['grace_minutes'])->diffInMinutes($record->check_in, false)) : 0;
                $onLeave = $leaves->contains(fn ($leave) => $leave->employee_id === $employee->id && in_array($date, $leave->dates));
                $status = $record ? ($late ? 'Late' : 'Present') : ($onLeave ? 'On leave' : (! $working ? 'Day off' : (CarbonImmutable::now($schedule['timezone']) > CarbonImmutable::parse($date.' '.$schedule['end_time'], $schedule['timezone']) ? 'Absent' : 'Not marked')));
                $rows[] = ['employee_id' => $employee->id, 'name' => $employee->name, 'date' => $date, 'status' => $status, 'check_in' => $record?->check_in->setTimezone($schedule['timezone'])->format('H:i'), 'check_out' => $record?->check_out?->setTimezone($schedule['timezone'])->format('H:i'), 'minutes' => $record?->check_out ? (int) $record->check_in->diffInMinutes($record->check_out) : null, 'late_minutes' => $late, 'timezone' => $schedule['timezone']];
            }
        }

        return $rows;
    }

    /** @return Builder<Employee> */
    public function employees(Admin $actor): Builder
    {
        $query = Employee::query();
        if ($actor->can('employees.view')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($actor): void {
            $query->where('admin_id', $actor->id);
            if ($actor->can('hr.team')) {
                $query->orWhereIn('manager_id', Employee::where('admin_id', $actor->id)->select('id'));
            }
        });
    }

    public function requireEmployee(Admin $actor, int $id, bool $manage = false): Employee
    {
        $employee = $this->employees($actor)->findOrFail($id);
        if ($manage) {
            abort_unless($actor->can('attendance.manage') || ($actor->can('hr.self') && $employee->admin_id === $actor->id), 403);
        }

        return $employee;
    }

    /** @return array<string, mixed> */
    public function schedule(Employee $employee, string $date): array
    {
        $snapshot = DB::table('employee_schedules')->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)->orderByDesc('effective_from')->value('snapshot');
        $this->check($snapshot !== null, 'work_schedule_id', 'Assign a work schedule covering this date first.');

        return json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $schedule */
    public function working(Employee $employee, string $date, array $schedule): bool
    {
        return $date >= $employee->joined_on && (! $employee->ended_on || $date <= $employee->ended_on)
            && in_array(CarbonImmutable::parse($date)->dayOfWeekIso, $schedule['weekdays'])
            && ! Holiday::where('date', $date)->exists();
    }

    public function reserved(int $employeeId, int $typeId, int $year): int
    {
        return LeaveRequest::where('employee_id', $employeeId)->where('leave_type_id', $typeId)
            ->whereYear('start_date', $year)->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
            ->get(['dates'])->sum(fn ($leave) => count($leave->dates));
    }

    /** @param array<string, mixed> $data */
    public function requestLeave(Employee $employee, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data): LeaveRequest {
            $employee = Employee::lockForUpdate()->findOrFail($employee->id);
            $this->check($employee->is_active, 'employee_id', 'This employee is inactive.');
            $start = CarbonImmutable::parse($data['start_date']);
            $end = CarbonImmutable::parse($data['end_date']);
            $this->check($start->year === $end->year, 'end_date', 'Submit a separate request for each calendar year.');
            $this->check($data['start_date'] >= $employee->joined_on && (! $employee->ended_on || $data['end_date'] <= $employee->ended_on), 'start_date', 'Dates must be inside employment dates.');
            $dates = [];
            for ($day = $start; $day <= $end; $day = $day->addDay()) {
                $date = $day->toDateString();
                if ($this->working($employee, $date, $this->schedule($employee, $date))) {
                    $dates[] = $date;
                }
            }
            $this->check(count($dates) > 0, 'start_date', 'Select at least one working day.');
            $overlaps = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved', 'cancellation_requested'])
                ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->get();
            $this->check(! $overlaps->contains(fn ($leave) => count(array_intersect($dates, $leave->dates)) > 0), 'start_date', 'These dates overlap an existing request.');
            $allowance = LeaveAllocation::where('employee_id', $employee->id)->where('leave_type_id', $data['leave_type_id'])->where('year', $start->year)->value('days') ?? 0;
            $this->check($allowance - $this->reserved($employee->id, $data['leave_type_id'], $start->year) >= count($dates), 'leave_type_id', 'Not enough available leave balance.');
            $this->check(! Attendance::where('employee_id', $employee->id)->whereIn('work_date', $dates)->exists(), 'start_date', 'Attendance already exists on these dates. Resolve it before requesting leave.');

            return LeaveRequest::create([...$data, 'employee_id' => $employee->id, 'dates' => $dates, 'status' => 'pending']);
        });
    }

    public function reviewLeave(Admin $actor, LeaveRequest $leave, string $action, ?string $note): void
    {
        DB::transaction(function () use ($actor, $leave, $action, $note): void {
            $employee = Employee::lockForUpdate()->findOrFail($leave->employee_id);
            $leave = LeaveRequest::lockForUpdate()->findOrFail($leave->id);
            $own = $employee->admin_id === $actor->id;
            if ($action === 'cancel') {
                abort_unless(($own && $actor->can('hr.self')) || $actor->can('leave.manage'), 403);
                $this->check(in_array($leave->status, ['pending', 'approved']), 'action', 'This request cannot be cancelled.');
                $status = $leave->status === 'pending' ? 'cancelled' : 'cancellation_requested';
            } else {
                abort_unless(! $own && ($actor->can('leave.approve') || ($actor->can('leave.approve-team') && Employee::whereKey($employee->manager_id)->where('admin_id', $actor->id)->exists())), 403);
                $this->check(in_array($leave->status, ['pending', 'cancellation_requested']), 'action', 'This request has already been reviewed.');
                $status = $leave->status === 'cancellation_requested' ? ($action === 'approve' ? 'cancelled' : 'approved') : ($action === 'approve' ? 'approved' : 'rejected');
                if ($status === 'approved' && $leave->status === 'pending') {
                    $allowance = LeaveAllocation::where('employee_id', $employee->id)->where('leave_type_id', $leave->leave_type_id)->where('year', substr($leave->start_date, 0, 4))->value('days') ?? 0;
                    $this->check($allowance >= $this->reserved($employee->id, $leave->leave_type_id, (int) substr($leave->start_date, 0, 4)), 'action', 'The leave allowance is no longer sufficient.');
                    $this->check(! Attendance::where('employee_id', $employee->id)->whereIn('work_date', $leave->dates)->exists(), 'action', 'Attendance conflicts with this leave.');
                }
            }
            $before = $leave->auditLogValues();
            $leave->update(['status' => $status, 'reviewer_id' => $actor->id, 'review_note' => $note]);
            app(AuditLogService::class)->updated('hr.leave.'.$status, $leave, $before, actor: $actor);
            app(WorkflowNotifier::class)->leaveReviewed($leave);
        });
    }

    /** @param array<string, mixed> $data */
    public function saveAttendance(Employee $employee, array $data): Attendance
    {
        $date = $data['work_date'];
        $this->check($employee->is_active, 'employee_id', 'This employee is inactive.');
        $existing = Attendance::where('employee_id', $employee->id)->where('work_date', $date)->first();
        $schedule = $existing->schedule ?? $this->schedule($employee, $date);
        $in = CarbonImmutable::parse($data['check_in'], $schedule['timezone']);
        $out = empty($data['check_out']) ? null : CarbonImmutable::parse($data['check_out'], $schedule['timezone']);
        $this->check($date >= $employee->joined_on && (! $employee->ended_on || $date <= $employee->ended_on), 'work_date', 'Date must be inside employment dates.');
        $this->check($in->toDateString() === $date && (! $out || ($out->toDateString() === $date && $out > $in)), 'check_out', 'Use ordered check-in and check-out times on the same work date.');
        $this->check($in <= CarbonImmutable::now($schedule['timezone']) && (! $out || $out <= CarbonImmutable::now($schedule['timezone'])), 'check_in', 'Attendance cannot be recorded in the future.');
        $leaves = LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['approved', 'cancellation_requested'])->where('start_date', '<=', $date)->where('end_date', '>=', $date)->get();
        $this->check(! $leaves->contains(fn ($leave) => in_array($date, $leave->dates)), 'work_date', 'Approved leave conflicts with this attendance.');

        return Attendance::updateOrCreate(['employee_id' => $employee->id, 'work_date' => $date], [
            'check_in' => $in->utc(), 'check_out' => $out?->utc(), 'schedule' => $schedule, 'reason' => $data['reason'] ?? null,
        ]);
    }

    public function check(bool $valid, string $field, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
