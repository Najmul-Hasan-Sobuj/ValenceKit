<?php

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Payroll;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('enforces payroll transitions and exposes only published own payslips', function () {
    $actor = Admin::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['admin_id' => $actor->id]);
    $payroll = Payroll::create(['employee_id' => $employee->id, 'employee_name' => $employee->name, 'period' => '2026-11', 'base_minor' => 10000, 'allowance_minor' => 0, 'deduction_minor' => 0, 'currency' => 'BDT']);
    $this->actingAs($actor, 'admin')->postJson(route('admin.payroll.approve', $payroll))->assertForbidden();
    $actor->givePermissionTo(['hr.self', 'payroll.pay', 'payroll.approve']);
    $this->get(route('admin.payroll.index'))->assertInertia(fn ($page) => $page->where('payrolls.total', 0));
    $payment = ['paid_on' => now()->toDateString(), 'payment_reference' => 'BANK-123'];
    $this->postJson(route('admin.payroll.pay', $payroll), $payment)->assertUnprocessable();
    $this->post(route('admin.payroll.approve', $payroll))->assertSessionHasNoErrors();
    $this->postJson(route('admin.payroll.approve', $payroll))->assertUnprocessable();
    $this->get(route('admin.payroll.index'))->assertInertia(fn ($page) => $page->where('payrolls.total', 1));
    $other = Admin::factory()->create();
    $other->givePermissionTo('hr.self');
    $this->actingAs($other, 'admin')->get(route('admin.payroll.index'))->assertInertia(fn ($page) => $page->where('payrolls.total', 0));
    $this->actingAs($actor, 'admin')->post(route('admin.payroll.pay', $payroll), $payment)->assertSessionHasNoErrors();
    $this->postJson(route('admin.payroll.pay', $payroll), $payment)->assertUnprocessable();
    expect($payroll->fresh()->status)->toBe('paid');
    $this->assertDatabaseHas('audit_logs', ['action' => 'payroll.paid']);
});

it('validates lead ownership and sends a due follow-up only once', function () {
    $actor = Admin::factory()->create(['is_active' => true]);
    $lead = Lead::factory()->create();
    $payload = ['assigned_to' => $actor->id, 'stage' => 'contacted', 'next_follow_up_at' => now()->subMinute()->toIso8601String()];
    $this->actingAs($actor, 'admin')->patchJson(route('admin.leads.update', $lead), $payload)->assertForbidden();
    $actor->givePermissionTo('leads.manage');
    $this->patchJson(route('admin.leads.update', $lead), $payload)->assertUnprocessable();
    $actor->givePermissionTo('leads.view');
    $this->patch(route('admin.leads.update', $lead), $payload)->assertSessionHasNoErrors();
    $before = $actor->notifications()->count();
    $this->artisan('business:remind')->assertSuccessful();
    $this->artisan('business:remind')->assertSuccessful();
    expect($actor->notifications()->count())->toBe($before + 1);
    $this->patch(route('admin.leads.update', $lead), [...$payload, 'stage' => 'won'])->assertSessionHasNoErrors();
    expect($lead->fresh()->next_follow_up_at)->toBeNull();
});
