<?php

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
use App\Services\HrService;
use Carbon\CarbonImmutable;
use Database\Seeders\HrPermissionSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function hrEmployee(?Admin $account = null): Employee
{
    $employee = Employee::factory()->create(['admin_id' => $account?->id]);
    $schedule = WorkSchedule::create(['name' => 'Day shift', 'start_time' => '09:00', 'end_time' => '18:00', 'weekdays' => [1, 2, 3, 4, 5], 'grace_minutes' => 10, 'timezone' => 'Asia/Dhaka']);
    DB::table('employee_schedules')->insert(['employee_id' => $employee->id, 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-01-01', 'snapshot' => json_encode($schedule->only(['start_time', 'end_time', 'weekdays', 'grace_minutes', 'timezone']))]);

    return $employee;
}

it('requires authentication and HR permissions', function () {
    $this->get(route('admin.hr.index'))->assertRedirect();
    $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.hr.index'))->assertForbidden();
});

it('paginates employees and attendance while preserving the selected HR tab and filters', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('employees.view');
    $employee = hrEmployee();
    Employee::factory()->count(15)->create(['department_id' => $employee->department_id]);
    $this->actingAs($actor, 'admin')->get(route('admin.hr.index', ['section' => 'employees', 'department_id' => $employee->department_id, 'page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'employees')->where('employees.total', 16)->has('employees.data', 1)->where('filters.department_id', (string) $employee->department_id));
    $this->get(route('admin.hr.index', ['section' => 'attendance', 'department_id' => $employee->department_id, 'from' => '2026-11-01', 'to' => '2026-11-30', 'report_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('section', 'attendance')->where('reportPagination.total', 30)->where('reportPagination.from', 16)->where('report.0.date', '2026-11-16')->has('report', 15)->has('reportLinks', 4)->where('reportLinks.2.active', true));
});

it('paginates report rows even when one employee spans multiple pages', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('employees.view');
    $employee = hrEmployee();
    $params = ['section' => 'reports', 'from' => '2026-11-01', 'to' => '2026-11-30', 'department_id' => $employee->department_id];
    $this->actingAs($actor, 'admin')->get(route('admin.hr.index', $params))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportPagination.total', 30)->where('reportPagination.from', 1)->where('reportPagination.to', 15)->where('report.0.date', '2026-11-01')->has('report', 15));
    $this->get(route('admin.hr.index', [...$params, 'report_page' => 2]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportPagination.from', 16)->where('reportPagination.to', 30)->where('report.0.date', '2026-11-16')->where('report.14.date', '2026-11-30')->where('reportLinks.2.active', true)->has('report', 15));
    $this->get(route('admin.hr.index', [...$params, 'report_page' => 9]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportPagination.total', 30)->where('reportPagination.from', null)->has('report', 0));
    $second = hrEmployee();
    $second->update(['department_id' => $employee->department_id]);
    $this->get(route('admin.hr.index', [...$params, 'report_page' => 3]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('reportPagination.total', 60)->where('report.0.employee_id', $second->id)->where('report.0.date', '2026-11-01')->has('report', 15));
});

it('filters attendance and unique employee totals by date range and department', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['employees.view', 'hr.export']);
    $employee = hrEmployee();
    $other = hrEmployee();
    $schedule = json_decode(DB::table('employee_schedules')->where('employee_id', $employee->id)->value('snapshot'), true);
    foreach (['2026-11-09', '2026-11-10'] as $date) {
        Attendance::create(['employee_id' => $employee->id, 'work_date' => $date, 'check_in' => $date.' 03:00:00', 'schedule' => $schedule]);
    }
    $type = LeaveType::create(['name' => 'Annual']);
    LeaveRequest::create(['employee_id' => $other->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'dates' => ['2026-11-10'], 'reason' => 'Leave', 'status' => 'approved']);
    $this->actingAs($actor, 'admin')->get(route('admin.hr.index', ['section' => 'attendance', 'from' => '2026-11-09', 'to' => '2026-11-10']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('rangeStart', '2026-11-09')->where('rangeEnd', '2026-11-10')->where('totals.present', 1)->where('totals.on_leave', 1)->has('report', 4));
    $this->get(route('admin.hr.index', ['section' => 'attendance', 'department_id' => $employee->department_id, 'from' => '2026-11-09', 'to' => '2026-11-10']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('totals.present', 1)->where('totals.on_leave', 0)->has('report', 2));
    $csv = $this->get(route('admin.hr.export', ['from' => '2026-11-09', 'to' => '2026-11-10', 'department_id' => $employee->department_id]))->assertOk()->streamedContent();
    expect($csv)->toContain('2026-11-09', '2026-11-10')->not->toContain('2026-11-11', $other->name);
    $this->getJson(route('admin.hr.index', ['from' => '2026-11-10', 'to' => '2026-11-09']))->assertUnprocessable()->assertJsonValidationErrors('to');
    $this->getJson(route('admin.hr.export', ['from' => '2024-01-01', 'to' => '2026-11-10']))->assertUnprocessable()->assertJsonValidationErrors('to');
});

it('limits self service to the linked employee', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.self');
    $own = hrEmployee($actor);
    $other = hrEmployee();
    $this->actingAs($actor, 'admin')->get(route('admin.hr.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/hr/Index')->has('employees.data', 1)->where('employees.data.0.id', $own->id));
    $this->postJson(route('admin.hr.attendance.store'), ['employee_id' => $other->id, 'work_date' => '2026-11-10', 'check_in' => '2026-11-10T09:00', 'check_out' => '2026-11-10T18:00', 'reason' => 'Correction'])->assertNotFound();
});

it('creates employee records without granting account permissions', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['employees.create', 'employees.view']);
    $existing = hrEmployee();
    $schedule = WorkSchedule::first();
    $account = Admin::factory()->create();
    $this->actingAs($actor, 'admin')->post(route('admin.hr.employees.store'), ['name' => 'Ayesha', 'designation' => 'Developer', 'department_id' => $existing->department_id, 'admin_id' => $account->id, 'joined_on' => '2026-01-01', 'is_active' => true, 'work_schedule_id' => $schedule->id])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('employees', ['name' => 'Ayesha', 'admin_id' => $account->id]);
    $this->assertSame(Employee::where('name', 'Ayesha')->value('id'), session('hr_created.id'));
    $this->assertSame('employee', session('hr_created.kind'));
    expect($account->fresh()->getAllPermissions())->toHaveCount(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'hr.employee.saved']);
});

it('preserves every employee form field when creating and editing the record', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['employees.create', 'employees.view', 'employees.edit']);
    $manager = hrEmployee();
    $account = Admin::factory()->create();
    $payload = [
        'name' => 'Complete employee', 'email' => 'employee@example.test', 'phone' => '+8801700000000',
        'designation' => 'Operations specialist', 'department_id' => $manager->department_id,
        'manager_id' => $manager->id, 'admin_id' => $account->id, 'joined_on' => '2026-01-01',
        'ended_on' => '2026-12-31', 'is_active' => true, 'work_schedule_id' => WorkSchedule::firstOrFail()->id,
        'effective_from' => '2026-01-02', 'emergency_contact' => 'Emergency contact: +8801800000000',
        'notes' => 'First line'.PHP_EOL.'Second line',
    ];
    $this->actingAs($actor, 'admin')->post(route('admin.hr.employees.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $employee = Employee::where('email', 'employee@example.test')->firstOrFail();
    $this->assertDatabaseHas('employee_schedules', ['employee_id' => $employee->id, 'effective_from' => '2026-01-02']);
    $this->get(route('admin.hr.index', ['section' => 'employees', 'search' => 'Complete employee']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('employees.data', 1)
        ->where('employees.data.0.emergency_contact', $payload['emergency_contact'])
        ->where('employees.data.0.notes', $payload['notes'])
        ->where('employees.data.0.manager_id', $manager->id)
        ->where('employees.data.0.admin_id', $account->id));
    $payload['work_schedule_id'] = null;
    $payload['effective_from'] = null;
    $payload['notes'] = 'Updated notes';
    $payload['emergency_contact'] = 'Updated emergency contact';
    $this->put(route('admin.hr.employees.update', $employee), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'notes' => 'Updated notes', 'emergency_contact' => 'Updated emergency contact', 'manager_id' => $manager->id, 'admin_id' => $account->id]);
    expect($account->fresh()->getAllPermissions())->toHaveCount(0);
});

it('reserves working days and rejects overlaps and insufficient balances', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.self');
    $employee = hrEmployee($actor);
    $type = LeaveType::create(['name' => 'Annual']);
    LeaveAllocation::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'days' => 2, 'reason' => 'Annual allowance']);
    $payload = ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-13', 'end_date' => '2026-11-16', 'reason' => 'Family'];
    $this->actingAs($actor, 'admin')->post(route('admin.hr.leave.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect(LeaveRequest::first()->dates)->toBe(['2026-11-13', '2026-11-16']);
    $this->postJson(route('admin.hr.leave.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('start_date');
    $this->postJson(route('admin.hr.leave.store'), [...$payload, 'start_date' => '2026-11-17', 'end_date' => '2026-11-17'])->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
});

it('prevents self approval and preserves reservations through cancellation review', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['hr.self', 'leave.approve', 'employees.view']);
    $employee = hrEmployee($actor);
    $type = LeaveType::create(['name' => 'Annual']);
    LeaveAllocation::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'days' => 2, 'reason' => 'Allocation']);
    $this->actingAs($actor, 'admin')->post(route('admin.hr.leave.store'), ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'reason' => 'Family'])->assertSessionHasNoErrors();
    $leave = LeaveRequest::first();
    $this->post(route('admin.hr.leave.review', $leave), ['action' => 'approve'])->assertForbidden();
    $reviewer = Admin::factory()->create();
    $reviewer->givePermissionTo(['employees.view', 'leave.approve']);
    $this->actingAs($reviewer, 'admin')->post(route('admin.hr.leave.review', $leave), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe('approved');
    $this->actingAs($actor, 'admin')->post(route('admin.hr.leave.review', $leave), ['action' => 'cancel'])->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe('cancellation_requested');
    expect(app(HrService::class)->reserved($employee->id, $type->id, 2026))->toBe(1);
    $this->actingAs($reviewer, 'admin')->post(route('admin.hr.leave.review', $leave), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe('cancelled');
    expect(app(HrService::class)->reserved($employee->id, $type->id, 2026))->toBe(0);
});

it('records server time and rejects duplicate punches', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 03:15:00', 'UTC'));
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.self');
    $employee = hrEmployee($actor);
    $this->actingAs($actor, 'admin')->post(route('admin.hr.clock'), ['action' => 'in'])->assertSessionHasNoErrors();
    $this->get(route('admin.hr.index', ['section' => 'workspace', 'date' => '2026-11-01']))->assertInertia(fn (Assert $page) => $page->where('clockState.checked_in', true)->where('clockState.checked_out', false));
    $this->postJson(route('admin.hr.clock'), ['action' => 'in'])->assertUnprocessable();
    $this->travel(8)->hours();
    $this->post(route('admin.hr.clock'), ['action' => 'out'])->assertSessionHasNoErrors();
    $this->get(route('admin.hr.index', ['section' => 'workspace']))->assertInertia(fn (Assert $page) => $page->where('clockState.checked_in', true)->where('clockState.checked_out', true));
    $this->postJson(route('admin.hr.clock'), ['action' => 'out'])->assertUnprocessable();
    $record = Attendance::first();
    expect($record->employee_id)->toBe($employee->id)->and($record->check_in->format('H:i'))->toBe('03:15')->and($record->check_out)->not->toBeNull();
    $this->assertDatabaseCount('attendances', 1);
});

it('audits manual corrections and refuses future attendance and approved leave conflicts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC'));
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['employees.view', 'attendance.manage']);
    $employee = hrEmployee();
    $payload = ['employee_id' => $employee->id, 'work_date' => '2026-11-10', 'check_in' => '2026-11-10T09:00', 'check_out' => '2026-11-10T18:00', 'reason' => 'Forgot checkout'];
    $this->actingAs($actor, 'admin')->post(route('admin.hr.attendance.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('audit_logs', ['action' => 'hr.attendance.corrected']);
    $this->postJson(route('admin.hr.attendance.store'), [...$payload, 'work_date' => '2026-11-12', 'check_in' => '2026-11-12T09:00', 'check_out' => null])->assertUnprocessable();
    $type = LeaveType::create(['name' => 'Annual']);
    LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'dates' => ['2026-11-09'], 'reason' => 'Leave', 'status' => 'approved']);
    $this->postJson(route('admin.hr.attendance.store'), [...$payload, 'work_date' => '2026-11-09', 'check_in' => '2026-11-09T09:00', 'check_out' => null])->assertUnprocessable()->assertJsonValidationErrors('work_date');
});

it('protects settings and rejects overnight schedules', function () {
    $actor = Admin::factory()->create();
    $this->actingAs($actor, 'admin')->post(route('admin.hr.settings.store'), ['kind' => 'department', 'name' => 'Engineering'])->assertForbidden();
    $actor->givePermissionTo('hr.settings.edit');
    $this->post(route('admin.hr.settings.store'), ['kind' => 'schedule', 'name' => 'Night', 'start_time' => '18:00', 'end_time' => '09:00', 'weekdays' => [1], 'grace_minutes' => 10, 'timezone' => 'Asia/Dhaka'])->assertSessionHasErrors('end_time');
});

it('returns the created setting identity for dependent forms', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.settings.edit');
    $this->actingAs($actor, 'admin')->post(route('admin.hr.settings.store'), ['kind' => 'department', 'name' => 'Support'])
        ->assertSessionHas('hr_created.kind', 'department')
        ->assertSessionHas('hr_created.id', Department::where('name', 'Support')->value('id'));
});

it('exports scoped safe CSV', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['hr.self', 'hr.export']);
    $employee = hrEmployee($actor);
    $employee->update(['name' => '=DANGER()']);
    hrEmployee();
    $response = $this->actingAs($actor, 'admin')->get(route('admin.hr.export', ['month' => '2026-11']))->assertOk();
    expect($response->streamedContent())->toContain("'=DANGER()");
});

it('keeps referenced settings and preserves existing role assignments when registering HR permissions', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.settings.edit');
    $employee = hrEmployee();
    $this->actingAs($actor, 'admin')->deleteJson(route('admin.hr.settings.destroy', ['kind' => 'department', 'id' => $employee->department_id]))->assertUnprocessable();
    $this->assertDatabaseHas('departments', ['id' => $employee->department_id]);
    $department = Department::create(['name' => 'Unused']);
    $this->delete(route('admin.hr.settings.destroy', ['kind' => 'department', 'id' => $department->id]))->assertSessionHasNoErrors();
    $this->assertModelMissing($department);
    $this->seed(HrPermissionSeeder::class);
    expect($actor->fresh()->getAllPermissions()->pluck('name')->all())->toBe(['hr.settings.edit']);
});

it('reviews employee correction requests once with audited before values', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC'));
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('hr.self');
    $employee = hrEmployee($actor);
    $payload = ['employee_id' => $employee->id, 'work_date' => '2026-11-10', 'check_in' => '2026-11-10T09:00', 'check_out' => '2026-11-10T18:00', 'reason' => 'Missed punches'];
    $this->actingAs($actor, 'admin')->post(route('admin.hr.attendance.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('attendances', 0);
    $correction = AttendanceCorrection::first();
    $reviewer = Admin::factory()->create();
    $reviewer->givePermissionTo(['employees.view', 'attendance.manage']);
    $this->actingAs($reviewer, 'admin')->post(route('admin.hr.corrections.review', $correction), ['action' => 'approve'])->assertSessionHasNoErrors();
    expect($correction->fresh()->status)->toBe('approved');
    expect(Attendance::first()->check_in->format('H:i'))->toBe('03:00');
    $this->postJson(route('admin.hr.corrections.review', $correction), ['action' => 'approve'])->assertUnprocessable();
});

it('restricts managers to direct reports and requires rejection notes', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['hr.team', 'leave.approve-team']);
    $manager = hrEmployee($actor);
    $employee = hrEmployee();
    $employee->update(['manager_id' => $manager->id]);
    $other = hrEmployee();
    $type = LeaveType::create(['name' => 'Annual']);
    $leave = LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'dates' => ['2026-11-16'], 'reason' => 'Family', 'status' => 'pending']);
    $this->actingAs($actor, 'admin')->postJson(route('admin.hr.leave.review', $leave), ['action' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->post(route('admin.hr.leave.review', $leave), ['action' => 'reject', 'note' => 'Discuss dates'])->assertSessionHasNoErrors();
    $leave->update(['employee_id' => $other->id, 'status' => 'pending']);
    $this->post(route('admin.hr.leave.review', $leave), ['action' => 'reject', 'note' => 'Discuss dates'])->assertNotFound();
});

it('does not mark absence before shift end or count holidays as absence', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 03:00:00', 'UTC'));
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('employees.view');
    hrEmployee();
    $this->actingAs($actor, 'admin')->get(route('admin.hr.index', ['section' => 'attendance', 'date' => '2026-11-11']))->assertInertia(fn (Assert $page) => $page->where('report.0.status', 'Not marked'));
    $this->travel(10)->hours();
    $this->get(route('admin.hr.index', ['section' => 'attendance', 'date' => '2026-11-11']))->assertInertia(fn (Assert $page) => $page->where('report.0.status', 'Absent'));
    Holiday::create(['name' => 'Holiday', 'date' => '2026-11-11']);
    $this->get(route('admin.hr.index', ['section' => 'attendance', 'date' => '2026-11-11']))->assertInertia(fn (Assert $page) => $page->where('report.0.status', 'Day off'));
});

it('rejects allowance reductions below reserved days and cross-year leave', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo(['hr.self', 'hr.settings.edit']);
    $employee = hrEmployee($actor);
    $type = LeaveType::create(['name' => 'Annual']);
    LeaveRequest::create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'dates' => ['2026-11-16'], 'reason' => 'Family', 'status' => 'pending']);
    $this->actingAs($actor, 'admin')->postJson(route('admin.hr.settings.store'), ['kind' => 'allocation', 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'days' => 0, 'reason' => 'Adjustment'])->assertUnprocessable()->assertJsonValidationErrors('days');
    $this->postJson(route('admin.hr.leave.store'), ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-31', 'end_date' => '2027-01-01', 'reason' => 'Family'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
});
