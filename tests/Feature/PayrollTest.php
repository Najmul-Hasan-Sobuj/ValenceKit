<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Payroll;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('protects payroll creation and validates deductions and duplicate periods', function () {
    $actor = Admin::factory()->create();
    $employee = Employee::factory()->create(['is_active' => true]);
    $payload = ['employee_id' => $employee->id, 'period' => '2026-11', 'base_minor' => 100000, 'allowance_minor' => 5000, 'deduction_minor' => 10000, 'currency' => 'USD'];
    $this->actingAs($actor, 'admin')->postJson(route('admin.payroll.store'), $payload)->assertForbidden();
    $actor->givePermissionTo('payroll.create');
    $this->postJson(route('admin.payroll.store'), [...$payload, 'deduction_minor' => 105001])->assertUnprocessable()->assertJsonValidationErrors('deduction_minor');
    $this->post(route('admin.payroll.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('payrolls', ['employee_id' => $employee->id, 'employee_name' => $employee->name, 'period' => '2026-11']);
    $this->postJson(route('admin.payroll.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('period');
    $this->assertDatabaseHas('audit_logs', ['action' => 'payroll.created']);
});

it('only exposes payroll to admins with payroll view permission', function () {
    $actor = Admin::factory()->create();
    $this->actingAs($actor, 'admin')->get(route('admin.payroll.index'))->assertForbidden();
    $actor->givePermissionTo('payroll.view');
    $this->get(route('admin.payroll.index'))->assertOk()->assertInertia(fn ($page) => $page->component('admin/billing/Payroll')->has('payrolls.data')->has('employees', 0));
});

it('paginates payroll records', function () {
    $actor = Admin::factory()->create();
    $actor->givePermissionTo('payroll.view');
    $employee = Employee::factory()->create();
    foreach (range(1, 16) as $month) {
        Payroll::create(['employee_id' => $employee->id, 'employee_name' => $employee->name, 'period' => sprintf('%04d-%02d', 2025 + intdiv($month - 1, 12), ($month - 1) % 12 + 1), 'base_minor' => 10000, 'allowance_minor' => 0, 'deduction_minor' => 0, 'currency' => 'BDT']);
    }
    $this->actingAs($actor, 'admin')->get(route('admin.payroll.index', ['page' => 2]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('payrolls.total', 16)->where('payrolls.current_page', 2)->where('payrolls.from', 16)->has('payrolls.data', 1));
});
