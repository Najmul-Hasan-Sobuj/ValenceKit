<?php

use App\Models\Admin;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    // Create an admin without any permissions
    $this->admin = Admin::factory()->create();
    $role = Role::create(['name' => 'No Permissions Role', 'guard_name' => 'admin']);
    $this->admin->assignRole($role);
});

it('prevents unauthorized access to user index', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('prevents unauthorized access to store user', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.users.store'), [
            'name' => 'Test',
            'email' => 'test@example.com',
            'role' => 'Some Role',
        ])
        ->assertForbidden();
});

it('prevents unauthorized access to update user', function () {
    $targetUser = Admin::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.users.update', $targetUser), [
            'name' => 'Test',
            'email' => 'test@example.com',
            'role' => 'Some Role',
        ])
        ->assertForbidden();
});

it('prevents unauthorized access to delete user', function () {
    $targetUser = Admin::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.users.destroy', $targetUser))
        ->assertForbidden();
});
