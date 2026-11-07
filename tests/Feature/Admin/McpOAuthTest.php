<?php

use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\RecordTransactionTool;
use App\Models\Admin;
use App\Models\McpTool;
use App\Services\McpToolSyncService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
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

it('denies a directly scoped token when the admin lacks permission', function () {
    $admin = Admin::factory()->create();
    // Admin has no roles or permissions
    Passport::actingAs($admin, ['mcp:finance.read'], 'mcp');

    $response = (new GetFinancialSummaryTool)->handle(new McpRequest([
        'year' => now()->year,
        'month' => now()->month,
    ]));

    expect((string) $response->content())
        ->toContain('required scope');
});

it('enforces current permissions for directly scoped tokens', function () {
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('finance.view');
    Passport::actingAs($admin, ['mcp:finance.read'], 'mcp');

    expect((string) (new GetFinancialSummaryTool)->handle(new McpRequest)->content())
        ->toContain('Financial Summary for');

    $admin->revokePermissionTo('finance.view');

    expect((string) (new GetFinancialSummaryTool)->handle(new McpRequest)->content())
        ->toContain('required scope');
});

it('denies tool execution when request has no authenticated token', function () {
    $response = (new GetFinancialSummaryTool)->handle(new McpRequest);

    expect((string) $response->content())
        ->toContain('required scope');
});

it('rejects a real token after its admin is deactivated', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    $this->createPersonalAccessClient();
    $token = $admin->createToken('test', ['mcp:use'])->accessToken;
    $admin->update(['is_active' => false]);

    $this->withToken($token)->postJson('/mcp/valencekit', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => new stdClass,
    ])->assertUnauthorized();
});

it('lists tool schemas and records the client type for a bearer token', function (string $grantType, string $authMethod) {
    $admin = Admin::factory()->create();
    $this->createPersonalAccessClient();
    $result = $admin->createToken('test', ['mcp:use']);
    $client = $result->token->client;
    $token = $result->accessToken;
    $client->update(['grant_types' => [$grantType]]);
    Once::flush();
    auth()->forgetGuards();
    app(McpToolSyncService::class)->sync();
    McpTool::query()->update(['is_active' => true]);
    Cache::forget('active_mcp_tools');

    $response = $this->withToken($token)
        ->withHeader('Accept', 'application/json, text/event-stream')
        ->postJson('/mcp/valencekit', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => new stdClass,
        ])
        ->assertOk();

    $tools = collect($response->json('result.tools'))->keyBy('name');
    expect($tools)->toHaveCount(4);
    expect(data_get($tools['get-user-roles'], 'inputSchema.properties.email.maxLength'))->toBe(255)
        ->and(data_get($tools['get-categories-for-module-tool'], 'inputSchema.properties.module.maxLength'))->toBe(50)
        ->and(data_get($tools['record-transaction-tool'], 'inputSchema.properties.amount.minimum'))->toBe(0.01)
        ->and(data_get($tools['record-transaction-tool'], 'inputSchema.properties.description.minLength'))->toBe(1)
        ->and(data_get($tools['get-financial-summary-tool'], 'inputSchema.properties.month.maximum'))->toBe(12);
    $this->assertDatabaseHas('mcp_usage_logs', [
        'admin_id' => $admin->id,
        'token_used' => $authMethod === 'passport' ? 'passport_token' : 'oauth_token',
        'auth_method' => $authMethod,
        'oauth_client_id' => $authMethod === 'passport' ? null : $client->id,
    ]);
})->with([
    'personal access' => ['personal_access', 'passport'],
    'authorization code' => ['authorization_code', 'oauth'],
]);
