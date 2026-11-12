<?php

use App\Models\Admin;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('requires permission and validates invoice dates and amounts', function () {
    $admin = Admin::factory()->create();
    $payload = ['number' => 'INV-QA-1', 'customer_name' => 'Client', 'description' => 'Services', 'amount_minor' => 10000, 'currency' => 'USD', 'issued_on' => '2026-11-12', 'due_on' => '2026-11-20'];
    $this->actingAs($admin, 'admin')->postJson(route('admin.invoices.store'), $payload)->assertForbidden();
    $admin->givePermissionTo('invoices.create');
    $this->postJson(route('admin.invoices.store'), [...$payload, 'amount_minor' => 0, 'due_on' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors(['amount_minor', 'due_on']);
    $this->post(route('admin.invoices.store'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('invoices', ['number' => 'INV-QA-1', 'amount_minor' => 10000]);
    $this->postJson(route('admin.invoices.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('number');
});

it('records partial payments without accepting an overpayment', function () {
    $admin = Admin::factory()->create();
    $invoice = Invoice::create(['number' => 'INV-QA-2', 'customer_name' => 'Client', 'description' => 'Services', 'amount_minor' => 10000, 'currency' => 'USD', 'issued_on' => '2026-11-12', 'due_on' => '2026-11-20']);
    $payment = ['amount_minor' => 6000, 'paid_on' => '2026-11-13'];
    $this->actingAs($admin, 'admin')->postJson(route('admin.invoices.payments.store', $invoice), $payment)->assertForbidden();
    $admin->givePermissionTo('invoices.payments.create');
    $this->post(route('admin.invoices.payments.store', $invoice), $payment)->assertSessionHasNoErrors();
    $this->postJson(route('admin.invoices.payments.store', $invoice), $payment)->assertUnprocessable()->assertJsonValidationErrors('amount_minor');
    $this->post(route('admin.invoices.payments.store', $invoice), [...$payment, 'amount_minor' => 4000])->assertSessionHasNoErrors();
    expect($invoice->payments()->sum('amount_minor'))->toBe(10000);
    $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.payment.created']);
});

it('only exposes invoices to admins with view permission', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.invoices.index'))->assertForbidden();
    $admin->givePermissionTo('invoices.view');
    $this->get(route('admin.invoices.index'))->assertOk()->assertInertia(fn ($page) => $page->component('admin/billing/Invoices')->has('invoices.data'));
});

it('searches invoice numbers and customer names and keeps the search in pagination', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('invoices.view');
    for ($i = 1; $i <= 16; $i++) {
        Invoice::create(['number' => 'SEARCH-'.$i, 'customer_name' => 'Acme', 'description' => 'Services', 'amount_minor' => 100, 'currency' => 'USD', 'issued_on' => '2026-11-12', 'due_on' => '2026-11-20']);
    }
    Invoice::create(['number' => 'OTHER-1', 'customer_name' => 'Other', 'description' => 'Services', 'amount_minor' => 100, 'currency' => 'USD', 'issued_on' => '2026-11-12', 'due_on' => '2026-11-20']);
    $this->actingAs($admin, 'admin')->get(route('admin.invoices.index', ['search' => 'Acme', 'page' => 2]))
        ->assertOk()->assertInertia(fn ($page) => $page->where('invoices.total', 16)->has('invoices.data', 1)->where('filters.search', 'Acme'));
    $this->get(route('admin.invoices.index', ['search' => 'OTHER-1']))
        ->assertOk()->assertInertia(fn ($page) => $page->where('invoices.total', 1)->where('invoices.data.0.customer_name', 'Other'));
});

it('shows overdue age only for unpaid invoices', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00'));
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('invoices.view');
    $data = ['customer_name' => 'Client', 'description' => 'Services', 'amount_minor' => 10000, 'currency' => 'USD', 'issued_on' => '2026-11-01'];
    $overdue = Invoice::create([...$data, 'number' => 'OVERDUE', 'due_on' => '2026-11-10']);
    $overdue->payments()->create(['amount_minor' => 3000, 'paid_on' => '2026-11-15']);
    $today = Invoice::create([...$data, 'number' => 'TODAY', 'due_on' => '2026-11-20']);
    $paid = Invoice::create([...$data, 'number' => 'PAID', 'due_on' => '2026-11-10']);
    $paid->payments()->create(['amount_minor' => 10000, 'paid_on' => '2026-11-11']);
    $this->actingAs($admin, 'admin')->get(route('admin.invoices.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('invoices.data.0.payment_status', 'paid')->where('invoices.data.0.overdue_days', 0)
        ->where('invoices.data.1.payment_status', 'due_today')->where('invoices.data.1.overdue_days', 0)
        ->where('invoices.data.2.payment_status', 'overdue')->where('invoices.data.2.overdue_days', 10));
});
