<?php

use App\Models\Admin;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

test('unauthenticated visitors cannot access module settings', function () {
    $this->get(route('admin.modules.edit'))
        ->assertRedirect(route('admin.login'));
});

test('super admin can view configured modules', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.modules.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Modules')
            ->has('modules', count(config('modules')))
            ->where('modules.0.enabled', true)
        );
});

test('normal admin cannot view or update module settings', function () {
    $staff = Admin::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff, 'admin')
        ->get(route('admin.modules.edit'))
        ->assertForbidden();

    $this->patch(route('admin.modules.update'), [
        'modules' => ['finance' => false],
    ])->assertForbidden();

    $this->assertDatabaseMissing('modules', ['key' => 'finance']);
});

test('normal web user cannot access module settings', function () {
    $this->actingAs(User::factory()->create(), 'web')
        ->getJson(route('admin.modules.edit'))
        ->assertUnauthorized();

    $this->patchJson(route('admin.modules.update'), [
        'modules' => ['finance' => false],
    ])->assertUnauthorized();
});

test('super admin can disable and enable modules', function () {
    $modules = collect(config('modules'))->mapWithKeys(fn (array $module, string $key): array => [$key => true])->all();
    $modules['finance'] = false;

    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.modules.update'), ['modules' => $modules])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('modules', ['key' => 'finance', 'enabled' => false]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.modules.updated',
        'actor_id' => $this->superAdmin->id,
        'actor_type' => Admin::class,
    ]);

    $modules['finance'] = true;

    $this->patch(route('admin.modules.update'), ['modules' => $modules])
        ->assertRedirect();

    $this->assertDatabaseHas('modules', ['key' => 'finance', 'enabled' => true]);
});

test('disabled module routes return not found even with permission', function (string $module, string $route) {
    Module::query()->create(['key' => $module, 'enabled' => false]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route($route))
        ->assertNotFound();
})->with([
    'backups' => ['backups', 'drivesnap.index'],
    'exports' => ['exports', 'admin.exports.index'],
    'leads' => ['leads', 'admin.leads.index'],
    'contact' => ['contact', 'admin.contact.index'],
    'people and HR' => ['people', 'admin.hr.index'],
    'payroll' => ['people', 'admin.payroll.index'],
    'invoices' => ['billing', 'admin.invoices.index'],
    'proposals' => ['proposals', 'admin.proposals.index'],
    'finance' => ['finance', 'admin.finance.index'],
    'finance settings' => ['finance', 'admin.finance.settings.edit'],
    'categories' => ['categories', 'admin.categories.index'],
    'MCP usage' => ['mcp', 'admin.mcp.usage.index'],
    'MCP settings' => ['mcp', 'admin.mcp.edit'],
    'audit logs' => ['audit', 'admin.audit-logs.index'],
]);

test('disabled module state is shared separately from the settings page module list', function () {
    Module::query()->create(['key' => 'finance', 'enabled' => false]);

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.modules.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('moduleStates.finance', false)
            ->where('modules.7.key', 'finance')
            ->where('modules.7.enabled', false)
        );
});

test('enabled module routes still enforce permissions', function () {
    Module::query()->create(['key' => 'finance', 'enabled' => true]);
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.finance.index'))
        ->assertForbidden();
});

test('unknown module keys are rejected', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->patch(route('admin.modules.update'), [
            'modules' => ['unknown' => false],
        ])
        ->assertSessionHasErrors('modules');

    $this->assertDatabaseCount('modules', 0);
});
