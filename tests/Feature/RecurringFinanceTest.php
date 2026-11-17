<?php

use App\Models\Admin;
use App\Models\FinanceTransaction;
use App\Services\FinanceService;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('generates each month from its original anchor without duplicate or month-end drift', function () {
    $this->travelTo(now()->setDate(2026, 4, 1));
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('finance.create');
    $category = config('finance.categories.expense.0.id');
    $parent = app(FinanceService::class)->createTransaction(['type' => 'expense', 'amount' => 10, 'category_key' => $category, 'description' => 'Subscription', 'transaction_date' => '2026-01-31', 'is_recurring' => true, 'recurring_frequency' => 'monthly'], $admin->id);
    $this->artisan('finance:run-recurring', ['--dry-run' => true])->assertSuccessful();
    expect(FinanceTransaction::count())->toBe(1);
    $this->artisan('finance:run-recurring')->assertSuccessful();
    $this->artisan('finance:run-recurring')->assertSuccessful();
    expect(FinanceTransaction::where('recurring_parent_id', $parent->id)->orderBy('recurrence_on')->pluck('recurrence_on')->map->toDateString()->all())->toBe(['2026-02-28', '2026-03-31']);
    expect($parent->fresh()->recurring_next_on->toDateString())->toBe('2026-04-30');
    $admin->revokePermissionTo('finance.create');
    $this->travelTo(now()->setDate(2026, 5, 2));
    $this->artisan('finance:run-recurring')->assertSuccessful();
    expect(FinanceTransaction::count())->toBe(3);
});

it('requires a recurring frequency and permits stopping future occurrences', function () {
    $actor = Admin::factory()->create(['is_active' => true]);
    $actor->givePermissionTo(['finance.create', 'finance.edit']);
    $payload = ['type' => 'expense', 'amount' => 10, 'category_key' => config('finance.categories.expense.0.id'), 'description' => 'Recurring service', 'transaction_date' => '2026-01-31', 'payment_method' => 'cash', 'is_recurring' => true];
    $this->actingAs($actor, 'admin')->postJson(route('admin.finance.transactions.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('recurring_frequency');
    $this->post(route('admin.finance.transactions.store'), [...$payload, 'recurring_frequency' => 'monthly'])->assertSessionHasNoErrors();
    $transaction = FinanceTransaction::firstOrFail();
    $this->put(route('admin.finance.transactions.update', $transaction), ['is_recurring' => false])->assertSessionHasNoErrors();
    $this->artisan('finance:run-recurring')->assertSuccessful();
    expect(FinanceTransaction::count())->toBe(1);
});
