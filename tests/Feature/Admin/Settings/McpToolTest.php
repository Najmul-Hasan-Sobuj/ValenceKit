<?php

use App\Models\Admin;
use App\Models\McpTool;
use App\Models\McpUsageLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('can view the mcp settings page with tools', function () {
    McpTool::create([
        'name' => 'Test Tool',
        'class_name' => 'App\Mcp\Tools\TestTool',
        'description' => 'A test tool.',
        'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Mcp')
            ->has('mcpTools', 1)
        );
});

it('provides aggregated 7-day activity chart data on the mcp settings page', function () {
    McpUsageLog::create([
        'token_used' => 'test-token',
        'ip_address' => '127.0.0.1',
        'endpoint' => '/mcp/test',
        'auth_method' => 'oauth',
    ]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.mcp.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Mcp')
            ->has('activityChart', 7)
            ->where('activityChart.6.requests', 1)
        );
});

it('can toggle the active status of an mcp tool', function () {
    $tool = McpTool::create([
        'name' => 'Toggle Tool',
        'class_name' => 'App\Mcp\Tools\ToggleTool',
        'description' => 'A tool to toggle.',
        'is_active' => true,
    ]);

    Cache::put('active_mcp_tools', ['App\Mcp\Tools\ToggleTool']);

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.mcp.tools.update', $tool), [
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('mcp_tools', [
        'id' => $tool->id,
        'is_active' => false,
    ]);

    expect(Cache::has('active_mcp_tools'))->toBeFalse();
});

it('prevents non-admins from updating mcp tools', function () {
    $tool = McpTool::create([
        'name' => 'Restricted Tool',
        'class_name' => 'App\Mcp\Tools\RestrictedTool',
        'is_active' => true,
    ]);

    // Create an admin without the required permission
    $staffAdmin = Admin::factory()->create();

    // We assume staff has some permissions but we explicitly test lack of 'settings.mcp.edit' if needed,
    // or just a guest.
    $this->actingAs($staffAdmin, 'admin')
        ->patch(route('admin.mcp.tools.update', $tool), [
            'is_active' => false,
        ])
        ->assertForbidden();
});
