<?php

use App\Models\Admin;
use App\Models\McpUsageLog;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('can render mcp usage logs index with default date range', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.usage.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/McpUsage')
            ->has('logs')
            ->has('activityChart')
            ->has('stats')
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->toDateString())
        );
});

it('can filter mcp usage logs by same-day date range without range error', function () {
    McpUsageLog::create([
        'token_used' => 'test-token',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'endpoint' => 'test-tool',
        'created_at' => now(),
    ]);

    $today = now()->toDateString();

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.usage.index', ['from' => $today, 'to' => $today]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/McpUsage')
            ->has('activityChart', 1)
        );
});

it('properly filters data and returns correct chart length for all quick ranges', function ($from, $to, $expectedCount, $expectedInterval) {
    $fromDate = value($from);
    $toDate = value($to);
    $count = value($expectedCount);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.usage.index', ['from' => $fromDate, 'to' => $toDate]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/McpUsage')
            ->where('filters.from', $fromDate)
            ->where('filters.to', $toDate)
            ->where('chartInterval', $expectedInterval)
            ->has('activityChart', $count)
            ->has('logs')
            ->has('stats')
        );
})->with([
    'Today' => [fn () => now()->toDateString(), fn () => now()->toDateString(), 1, 'day'],
    'Yesterday' => [fn () => now()->subDay()->toDateString(), fn () => now()->subDay()->toDateString(), 1, 'day'],
    'Last 7 days' => [fn () => now()->subDays(6)->toDateString(), fn () => now()->toDateString(), 7, 'day'],
    'Last 30 days' => [fn () => now()->subDays(29)->toDateString(), fn () => now()->toDateString(), 30, 'day'],
    'This month' => [fn () => now()->startOfMonth()->toDateString(), fn () => now()->toDateString(), fn () => (int) now()->startOfMonth()->diffInDays(now()->startOfDay()) + 1, 'day'],
    'Last month' => [fn () => now()->subMonth()->startOfMonth()->toDateString(), fn () => now()->subMonth()->endOfMonth()->toDateString(), fn () => (int) now()->subMonth()->startOfMonth()->diffInDays(now()->subMonth()->endOfMonth()->startOfDay()) + 1, 'day'],
    'This year' => [fn () => now()->startOfYear()->toDateString(), fn () => now()->toDateString(), fn () => (int) now()->startOfYear()->diffInMonths(now()->startOfMonth()) + 1, 'month'],
]);

it('gracefully falls back to default date range when invalid dates are provided', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.usage.index', ['from' => 'invalid-date', 'to' => 'malformed-date']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/McpUsage')
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->toDateString())
        );
});

it('lists all individual mcp request invocations without collapsing same-client calls', function () {
    $ip = '192.168.1.100';
    $token = 'test-client-token';

    McpUsageLog::create([
        'token_used' => $token,
        'ip_address' => $ip,
        'user_agent' => 'Agent 1',
        'endpoint' => '/mcp/tools/list',
        'created_at' => now()->subMinutes(10),
    ]);

    McpUsageLog::create([
        'token_used' => $token,
        'ip_address' => $ip,
        'user_agent' => 'Agent 1',
        'endpoint' => '/mcp/tools/call',
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.usage.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/McpUsage')
            ->has('logs.data', 2)
            ->where('stats.totalRequests', 2)
            ->where('logs.total', 2)
            ->where('logs.data.0.endpoint', '/mcp/tools/call')
            ->where('logs.data.1.endpoint', '/mcp/tools/list')
        );
});
