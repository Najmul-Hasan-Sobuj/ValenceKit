<?php

use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Models\Admin;
use App\Services\McpAuthorizationService;
use App\Services\McpToolSyncService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('publishes MCP OAuth discovery metadata', function () {
    $this->get('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

it('syncs local tools into the registry with scopes', function () {
    app(McpToolSyncService::class)->sync();

    $this->assertDatabaseHas('mcp_tools', [
        'class_name' => RecordTransactionTool::class,
        'slug' => 'record-transaction-tool',
        'required_scope' => 'mcp:finance.write',
        'health_status' => 'healthy',
    ]);
});

it('denies finance tools when the admin lacks the mapped permission', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Support');

    Sanctum::actingAs($admin, ['mcp:access']);

    $response = (new GetFinancialSummaryTool)->handle(new McpRequest);

    expect((string) $response->content())
        ->toContain('required scope');
});

it('keeps the configured local MCP token authorized', function () {
    config(['services.mcp.default_token' => 'test-local-token']);

    $httpRequest = HttpRequest::create('/', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer test-local-token',
    ]);
    app()->instance('request', $httpRequest);

    expect(app(McpAuthorizationService::class)->allows(new McpRequest, 'mcp:tools.read'))->toBeTrue();
});

it('allows an admin with finance permission to use finance tools', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Staff');

    Sanctum::actingAs($admin, ['mcp:access']);

    $response = (new RecordTransactionTool)->handle(new McpRequest([
        'type' => 'income',
        'amount' => 100,
        'description' => 'OAuth scope test',
        'category' => 'client_retainer',
    ]));

    expect((string) $response->content())
        ->toContain('recorded successfully');
});
