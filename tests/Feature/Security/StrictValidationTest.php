<?php

use App\Mcp\Tools\GetCategoriesForModuleTool;
use App\Mcp\Tools\GetFinancialSummaryTool;
use App\Mcp\Tools\GetUserRoles;
use App\Mcp\Tools\RecordTransactionTool;
use App\Models\Admin;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    $this->admin = Admin::factory()->create();
    $role = Role::firstOrCreate(['name' => 'View Users Role', 'guard_name' => 'admin']);
    $permission = Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'admin']);
    $role->givePermissionTo($permission);
    $this->admin->assignRole($role);
});

test('admin user filter rejects oversized search with 422', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.users.index', [
            'search' => str_repeat('a', 101),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['search']);
});

test('admin user filter rejects non-existent role with 422', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.users.index', [
            'role' => 'NonExistentRole123',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

test('admin user filter rejects malformed date and invalid ranges with 422', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.users.index', [
            'from' => 'invalid-date',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from']);

    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.users.index', [
            'from' => '2026-10-15',
            'to' => '2026-10-10',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);
});

test('admin user filter accepts valid inputs', function () {
    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.users.index', [
            'search' => 'Admin',
            'from' => '2026-10-01',
            'to' => '2026-10-20',
        ]))
        ->assertOk();
});

test('get user roles mcp tool validates email strictly', function () {
    Sanctum::actingAs($this->admin, ['mcp:users.read']);
    $tool = new GetUserRoles;

    expect(fn () => $tool->handle(new McpRequest(['email' => 'not-an-email'])))
        ->toThrow(ValidationException::class);

    expect(fn () => $tool->handle(new McpRequest(['email' => ''])))
        ->toThrow(ValidationException::class);
});

test('get categories for module mcp tool validates module and type strictly', function () {
    Sanctum::actingAs($this->admin, ['mcp:tools.read']);
    $tool = new GetCategoriesForModuleTool;

    expect(fn () => $tool->handle(new McpRequest(['module' => ''])))
        ->toThrow(ValidationException::class);

    expect(fn () => $tool->handle(new McpRequest([
        'module' => 'finance',
        'type' => 'unsupported_type',
    ])))->toThrow(ValidationException::class);
});

test('get financial summary mcp tool validates year and month strictly', function () {
    Sanctum::actingAs($this->admin, ['mcp:finance.read']);
    $tool = new GetFinancialSummaryTool;

    expect(fn () => $tool->handle(new McpRequest(['month' => 13])))
        ->toThrow(ValidationException::class);

    expect(fn () => $tool->handle(new McpRequest(['year' => 1990])))
        ->toThrow(ValidationException::class);
});

test('record transaction mcp tool validates amount and type strictly', function () {
    Sanctum::actingAs($this->admin, ['mcp:finance.write']);
    $tool = new RecordTransactionTool;

    expect(fn () => $tool->handle(new McpRequest([
        'type' => 'invalid',
        'amount' => 50,
        'description' => 'Test',
    ])))->toThrow(ValidationException::class);

    expect(fn () => $tool->handle(new McpRequest([
        'type' => 'income',
        'amount' => -10,
        'description' => 'Negative amount',
    ])))->toThrow(ValidationException::class);

    expect(fn () => $tool->handle(new McpRequest([
        'type' => 'income',
        'amount' => 50,
        'description' => str_repeat('d', 300),
    ])))->toThrow(ValidationException::class);
});
