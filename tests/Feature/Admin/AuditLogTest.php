<?php

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Category;
use App\Services\AuditLogService;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('records an audit action and redacts sensitive values', function () {
    $admin = Admin::factory()->create();

    app(AuditLogService::class)->record(
        'test.secret_changed',
        beforeValues: ['password' => 'old', 'name' => 'Before'],
        afterValues: ['token' => 'new-token', 'name' => 'After'],
        actor: $admin,
    );

    $log = AuditLog::query()->latest('id')->firstOrFail();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->before_values)->toMatchArray([
            'password' => '[REDACTED]',
            'name' => 'Before',
        ])
        ->and($log->after_values)->toMatchArray([
            'token' => '[REDACTED]',
            'name' => 'After',
        ]);
});

it('allows authorized admins to browse audit logs', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    AuditLog::create(['action' => 'role.updated', 'description' => 'Role changed.']);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AuditLogs')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'role.updated')
        );
});

it('denies audit logs to admins without audit permission', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Support');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});

it('records role creation from the admin endpoint', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.authorization.roles.store'), [
            'name' => 'Auditor',
            'description' => 'Review activity.',
            'permissions' => ['audit.view'],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'role.created',
        'description' => 'Role Auditor was created.',
        'actor_id' => $admin->id,
    ]);
});

it('records user and category changes from their admin endpoints', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');
    $target = Admin::factory()->create(['name' => 'Original User']);
    $target->assignRole('Staff');

    $this->actingAs($admin, 'admin')
        ->put(route('admin.users.update', $target), [
            'name' => 'Updated User',
            'email' => $target->email,
            'role' => 'Support',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.categories.store'), [
            'module' => 'finance',
            'type' => 'expense',
            'name' => 'Audit Category',
            'icon' => 'Tag',
            'color' => '#10b981',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.updated',
        'subject_id' => $target->id,
        'actor_id' => $admin->id,
    ]);

    $category = Category::query()->where('name', 'Audit Category')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'category.created',
        'subject_id' => $category->id,
        'actor_id' => $admin->id,
    ]);
});

it('records profile and finance settings changes', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.profile.update'), [
            'name' => 'Updated Admin',
            'email' => $admin->email,
        ])
        ->assertRedirect();

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.finance.settings.update'), [
            'currency' => 'BDT',
            'monthly_budget' => 50000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.profile.updated',
        'subject_id' => $admin->id,
        'actor_id' => $admin->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.finance.updated',
        'actor_id' => $admin->id,
    ]);
});
