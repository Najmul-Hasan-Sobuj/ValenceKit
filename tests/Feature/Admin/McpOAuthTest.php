<?php

use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Models\Admin;
use App\Services\McpToolSyncService;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('publishes MCP OAuth discovery metadata', function () {
    $this->get('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

it('accepts OAuth redirects from configured domains', function () {
    config(['mcp.redirect_domains' => ['https://client.example']]);

    $this->postJson('/oauth/register', [
        'client_name' => 'Trusted MCP Client',
        'redirect_uris' => ['https://client.example/callback'],
    ])
        ->assertCreated()
        ->assertJsonPath('redirect_uris.0', 'https://client.example/callback');
});

it('rejects OAuth redirects from unknown domains', function () {
    config(['mcp.redirect_domains' => ['https://client.example']]);

    $this->postJson('/oauth/register', [
        'client_name' => 'Untrusted MCP Client',
        'redirect_uris' => ['https://evil.example/callback'],
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
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

    Passport::actingAs($admin, ['mcp:access'], 'mcp');

    $response = (new GetFinancialSummaryTool)->handle(new McpRequest);

    expect((string) $response->content())
        ->toContain('required scope');
});

it('rejects the legacy default MCP token', function () {
    config(['services.mcp.default_token' => 'test-local-token']);

    $this->withToken('test-local-token')
        ->postJson('/mcp/valencekit', [])
        ->assertUnauthorized();
});

it('allows an admin with finance permission to use finance tools', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Staff');

    Passport::actingAs($admin, ['mcp:access'], 'mcp');

    $response = (new RecordTransactionTool)->handle(new McpRequest([
        'type' => 'income',
        'amount' => 100,
        'description' => 'OAuth scope test',
        'category' => 'client_retainer',
    ]));

    expect((string) $response->content())
        ->toContain('recorded successfully');
});

it('allows tool execution when token has direct scope without relying on role permissions', function () {
    $admin = Admin::factory()->create();
    // Admin has no roles or permissions
    Passport::actingAs($admin, ['mcp:finance.read'], 'mcp');

    $response = (new GetFinancialSummaryTool)->handle(new McpRequest([
        'year' => now()->year,
        'month' => now()->month,
    ]));

    expect((string) $response->content())
        ->toContain('Financial Summary for');
});

it('denies tool execution when request has no authenticated token', function () {
    $response = (new GetFinancialSummaryTool)->handle(new McpRequest);

    expect((string) $response->content())
        ->toContain('required scope');
});
