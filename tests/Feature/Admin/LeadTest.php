<?php

use App\Models\Admin;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->createPersonalAccessClient();
    $this->admin = Admin::factory()->create();
    $this->admin->givePermissionTo(['leads.view', 'leads.import', 'leads.export', 'leads.delete']);
    $this->payload = ['source' => 'google_maps', 'source_id' => 'cid:1234', 'name' => 'Dhaka Dental', 'phone' => '01712345678'];
});

it('imports once and enriches a retry without replacing known contact details', function () {
    $token = $this->admin->createToken('Scout', ['leads:import'])->accessToken;
    $this->withToken($token)->postJson(route('api.leads.webhook'), $this->payload)
        ->assertCreated()->assertJsonPath('created', true);
    $this->withToken($token)->postJson(route('api.leads.webhook'), [...$this->payload, 'phone' => 'changed', 'email' => 'hello@example.com'])
        ->assertOk()->assertJsonPath('created', false);
    $this->assertDatabaseCount('leads', 1);
    $this->assertDatabaseHas('leads', ['phone' => '01712345678', 'email' => 'hello@example.com']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'lead.imported', 'actor_id' => $this->admin->id]);
});

it('rejects untrusted payload fields and unsafe links', function () {
    $token = $this->admin->createToken('Scout', ['leads:import'])->accessToken;
    $this->withToken($token)->postJson(route('api.leads.webhook'), [...$this->payload,
        'website' => 'javascript:alert(1)', 'details' => ['unexpected' => 'value'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['website', 'details']);
    $this->assertDatabaseCount('leads', 0);
});

it('rejects invalid authorization for imports', function (string $condition) {
    $result = $this->admin->createToken('Scout', [$condition === 'scope' ? 'mcp:use' : 'leads:import']);
    match ($condition) {
        'permission' => $this->admin->revokePermissionTo('leads.import'),
        'inactive' => $this->admin->update(['is_active' => false]),
        'password' => $this->admin->update(['must_change_password' => true]),
        'expired' => $result->token->update(['expires_at' => now()->subMinute()]),
        default => null,
    };
    $this->withToken($result->accessToken)->postJson(route('api.leads.webhook'), $this->payload)->assertForbidden();
    $this->assertDatabaseCount('leads', 0);
})->with(['scope', 'permission', 'inactive', 'password', 'expired']);

it('rejects guests and revoked tokens', function () {
    $this->postJson(route('api.leads.webhook'), $this->payload)->assertUnauthorized();
    $result = $this->admin->createToken('Scout', ['leads:import']);
    $result->token->revoke();
    $this->withToken($result->accessToken)->postJson(route('api.leads.webhook'), $this->payload)->assertUnauthorized();
});

it('rejects user tokens even with a matching scope', function () {
    $this->createPersonalAccessClient('users');
    $user = User::factory()->create();
    $token = $user->createToken('Wrong provider', ['leads:import'])->accessToken;
    $this->withToken($token)->postJson(route('api.leads.webhook'), $this->payload)->assertUnauthorized();
});

it('lists and searches leads for authorized admins', function () {
    Lead::factory()->create(['name' => 'Dhaka Dental']);
    Lead::factory()->create(['name' => 'Chattogram Bakery']);
    $this->actingAs($this->admin, 'admin')->get(route('admin.leads.index', ['search' => 'Dental']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/leads/Index')
        ->has('leads.data', 1)->where('leads.data.0.name', 'Dhaka Dental')->has('tokens', 0));
});

it('enforces granular permissions on admin operations', function () {
    $admin = Admin::factory()->create();
    $lead = Lead::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.leads.index'))->assertForbidden();
    $this->get(route('admin.leads.export'))->assertForbidden();
    $this->delete(route('admin.leads.destroy', $lead))->assertForbidden();
    $this->postJson(route('admin.leads.tokens.store'), ['name' => 'Scout'])->assertForbidden();
});

it('issues a scoped token without caching and revokes only owned lead tokens', function () {
    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.leads.tokens.store'), ['name' => 'Scout'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json('token'))->toBeString();
    $token = $this->admin->tokens()->firstOrFail();
    expect($token->scopes)->toBe(['leads:import']);
    expect($token->expires_at->diffInDays(now(), true))->toBeBetween(29, 31);
    $other = Admin::factory()->create()->createToken('Other', ['leads:import'])->token;
    $mcp = $this->admin->createToken('MCP', ['mcp:use'])->token;
    $this->delete(route('admin.leads.tokens.destroy', $other->id))->assertNotFound();
    $this->delete(route('admin.leads.tokens.destroy', $mcp->id))->assertNotFound();
    $this->delete(route('admin.leads.tokens.destroy', $token->id))->assertRedirect();
    expect($token->fresh()->revoked)->toBeTrue();
});

it('exports safe CSV and audits deletion', function () {
    $lead = Lead::factory()->create(['name' => '=HYPERLINK("https://example.com")']);
    $response = $this->actingAs($this->admin, 'admin')->get(route('admin.leads.export'))->assertOk();
    expect($response->streamedContent())->toContain("'=HYPERLINK");
    $this->delete(route('admin.leads.destroy', $lead))->assertRedirect();
    $this->assertModelMissing($lead);
    $this->assertDatabaseHas('audit_logs', ['action' => 'lead.deleted']);
});
