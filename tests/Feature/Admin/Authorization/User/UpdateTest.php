<?php

use App\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    $this->admin = Admin::factory()->create();
    $role = Role::create(['name' => 'Update Users Role', 'guard_name' => 'admin']);
    $role->givePermissionTo(Permission::where('name', 'users.edit')->first());
    $this->admin->assignRole($role);
});

it('can update an existing user', function () {
    $targetUser = Admin::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $role = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'admin']);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.users.update', $targetUser), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role' => 'Manager',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('admins', [
        'id' => $targetUser->id,
        'email' => 'new@example.com',
        'name' => 'New Name',
        'is_active' => false,
    ]);

    expect($targetUser->fresh()->hasRole('Manager'))->toBeTrue();
});
