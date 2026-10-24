<?php

use App\Models\Admin;
use App\Models\McpUsageLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

it('does not include mcpStats if admin lacks permission', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('mcpStats', null)
        );
});

it('renders mcpStats with total_calls and calls_today when permitted', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    McpUsageLog::create([
        'token_used' => 'bearer_oauth_key',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PhpUnitTest',
        'endpoint' => 'database-query',
        'created_at' => now(),
    ]);

    $log2 = McpUsageLog::create([
        'token_used' => 'passport_token',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PhpUnitTest',
        'endpoint' => 'search-docs',
    ]);
    $log2->timestamps = false;
    $log2->created_at = now()->subDays(2);
    $log2->save();

    $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->has('mcpStats', fn (Assert $stats) => $stats
                ->where('total_calls', 2)
                ->where('calls_today', 1)
                ->missing('total_tokens')
                ->missing('tokens_today')
            )
        );
});
