<?php

use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Models\Admin;
use App\Models\Category;
use App\Models\FinanceTransaction;
use App\Services\FinanceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('can render finance dashboard with default date range and props', function () {
    FinanceTransaction::create([
        'type' => 'income',
        'amount' => 500.00,
        'category_key' => 'client_retainer',
        'category_name' => 'Client Retainer',
        'description' => 'Test Retainer Income',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'card',
        'admin_id' => $this->superAdmin->id,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/finance/Index')
            ->has('transactions.data', 1)
            ->has('monthlySummary')
            ->has('periodMetrics')
            ->has('timelineChart')
            ->has('categoryChart')
            ->has('currency')
            ->where('currency.code', 'USD')
        );
});

it('denies access to admins without finance.view permission', function () {
    $supportAdmin = Admin::factory()->create();
    $supportAdmin->assignRole('Support');

    $this->actingAs($supportAdmin, 'admin')
        ->get(route('admin.finance.index'))
        ->assertForbidden();
});

it('denies the live finance summary widget to admins without finance.view permission', function () {
    $supportAdmin = Admin::factory()->create();
    $supportAdmin->assignRole('Support');

    $this->actingAs($supportAdmin, 'admin')
        ->get(route('admin.finance.summary'))
        ->assertForbidden();
});

it('allows the live finance summary widget for admins with finance.view permission', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.finance.summary'))
        ->assertOk()
        ->assertJsonStructure(['total_income', 'total_expense', 'net_balance', 'currency']);
});

it('can record a new income and expense transaction', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.finance.transactions.store'), [
            'type' => 'expense',
            'amount' => 120.50,
            'category_key' => 'cloud_infra',
            'description' => 'Cloud server subscription',
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'card',
            'reference' => 'INV-999',
            'notes' => 'Monthly AWS billing',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('finance_transactions', [
        'type' => 'expense',
        'amount' => 120.50,
        'category_key' => 'cloud_infra',
        'description' => 'Cloud server subscription',
        'reference' => 'INV-999',
        'admin_id' => $this->superAdmin->id,
    ]);
});

it('validates transaction fields properly', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.finance.transactions.store'), [
            'type' => 'invalid_type',
            'amount' => -10,
            'category_key' => '',
            'description' => '',
            'transaction_date' => 'invalid-date',
            'payment_method' => 'invalid_method',
        ])
        ->assertSessionHasErrors(['type', 'amount', 'category_key', 'description', 'transaction_date', 'payment_method']);
});

it('rejects inactive finance categories instead of falling back to config categories', function () {
    Category::create([
        'module' => 'finance',
        'type' => 'expense',
        'name' => 'Cloud Infrastructure',
        'slug' => 'cloud_infra',
        'is_active' => false,
        'is_system' => false,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.finance.transactions.store'), [
            'type' => 'expense',
            'amount' => 120.50,
            'category_key' => 'cloud_infra',
            'description' => 'Inactive category attempt',
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'card',
        ])
        ->assertSessionHasErrors('category_key');

    $this->assertDatabaseMissing('finance_transactions', [
        'description' => 'Inactive category attempt',
    ]);
});

it('can update an existing transaction', function () {
    $transaction = FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 50.00,
        'category_key' => 'meals',
        'category_name' => 'Meals & Entertainment',
        'description' => 'Lunch with client',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'admin_id' => $this->superAdmin->id,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->put(route('admin.finance.transactions.update', $transaction), [
            'description' => 'Updated lunch description',
            'amount' => 65.00,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($transaction->refresh()->description)->toBe('Updated lunch description');
    expect((float) $transaction->amount)->toBe(65.00);
});

it('can delete a transaction', function () {
    $transaction = FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 15.00,
        'category_key' => 'office',
        'category_name' => 'Office & Supplies',
        'description' => 'Notebooks',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'admin_id' => $this->superAdmin->id,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->delete(route('admin.finance.transactions.destroy', $transaction))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('finance_transactions', ['id' => $transaction->id]);
});

it('can bulk delete multiple transactions', function () {
    $t1 = FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 10.00,
        'category_key' => 'office',
        'category_name' => 'Office',
        'description' => 'Pen',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'admin_id' => $this->superAdmin->id,
    ]);

    $t2 = FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 20.00,
        'category_key' => 'office',
        'category_name' => 'Office',
        'description' => 'Paper',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'admin_id' => $this->superAdmin->id,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.finance.transactions.bulk-destroy'), [
            'ids' => [$t1->id, $t2->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('finance_transactions', ['id' => $t1->id]);
    $this->assertDatabaseMissing('finance_transactions', ['id' => $t2->id]);
});

it('exports transactions as formula-safe CSV', function () {
    FinanceTransaction::create([
        'type' => 'income',
        'amount' => 1200.00,
        'category_key' => 'consulting',
        'category_name' => 'Consulting & Services',
        'description' => '=HYPERLINK("https://example.com")',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'bank_transfer',
        'admin_id' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.finance.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment; filename="finance_transactions_')
        ->toContain('.csv"');
    expect($response->streamedContent())
        ->toContain("'=HYPERLINK")
        ->not->toContain('ss:Workbook');
});

it('can update global currency to Bangladeshi Taka (BDT) and adjust monthly budget', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.finance.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Finance')
            ->has('activeCurrency')
            ->has('monthlyBudget')
            ->has('currencies')
        );

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.finance.settings.update'), [
            'currency' => 'BDT',
            'monthly_budget' => 50000.00,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $financeService = app(FinanceService::class);
    $activeCurrency = $financeService->getActiveCurrency();

    expect($activeCurrency['code'])->toBe('BDT');
    expect($activeCurrency['symbol'])->toBe('৳');
    expect($financeService->getMonthlyBudget())->toBe(50000.00);
});

it('can execute MCP tools to record transaction and get financial summary', function () {
    Passport::actingAs($this->superAdmin, ['mcp:access'], 'mcp');

    $recordTool = new RecordTransactionTool;
    $request = new McpRequest([
        'type' => 'income',
        'amount' => 2500,
        'description' => 'Quarterly Retainer via AI',
        'category' => 'client_retainer',
    ]);

    $response = $recordTool->handle($request);
    $text = (string) $response->content();

    expect($text)->toContain('recorded successfully');
    expect($text)->toContain('Quarterly Retainer via AI');

    $summaryTool = new GetFinancialSummaryTool;
    $summaryRequest = new McpRequest([
        'year' => now()->year,
        'month' => now()->month,
    ]);

    $summaryResponse = $summaryTool->handle($summaryRequest);
    $summaryText = (string) $summaryResponse->content();

    expect($summaryText)->toContain('Financial Summary for');
    expect($summaryText)->toContain('Total Income');
    expect($summaryText)->toContain('Net Cash Flow');
});

it('properly aggregates analytics timeline using SQL for daily and monthly intervals', function () {
    $service = app(FinanceService::class);

    FinanceTransaction::create([
        'type' => 'income',
        'amount' => 150.00,
        'category_key' => 'client_retainer',
        'category_name' => 'Client Retainer',
        'description' => 'Income today',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'card',
        'admin_id' => $this->superAdmin->id,
    ]);

    FinanceTransaction::create([
        'type' => 'expense',
        'amount' => 50.00,
        'category_key' => 'server_hosting',
        'category_name' => 'Hosting',
        'description' => 'Hosting today',
        'transaction_date' => now()->toDateString(),
        'payment_method' => 'card',
        'admin_id' => $this->superAdmin->id,
    ]);

    // Daily interval (<= 31 days)
    $daily = $service->getAnalyticsTimeline(now()->subDays(6)->startOfDay(), now()->endOfDay());
    expect($daily['interval'])->toBe('day')
        ->and($daily['labels'])->toHaveCount(7)
        ->and(end($daily['income']))->toBe(150.00)
        ->and(end($daily['expense']))->toBe(50.00);

    // Monthly interval (> 31 days)
    $monthly = $service->getAnalyticsTimeline(now()->subMonths(2)->startOfMonth(), now()->endOfMonth());
    expect($monthly['interval'])->toBe('month')
        ->and(count($monthly['labels']))->toBeGreaterThanOrEqual(3);
});
