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

it('prevents an admin from deactivating their own account', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => $this->admin->roles->first()->name,
            'is_active' => false,
        ])
        ->assertSessionHasErrors(['is_active' => 'You cannot deactivate your own account.']);

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('prevents demoting or deactivating the only remaining super admin', function () {
    $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'admin')->first();
    $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'admin']);

    $soleSuperAdmin = Admin::factory()->create(['is_active' => true]);
    $soleSuperAdmin->syncRoles([$superAdminRole]);

    // Ensure there is only 1 Super Admin in the system
    Admin::role('Super Admin')->where('id', '!=', $soleSuperAdmin->id)->each(fn ($a) => $a->removeRole($superAdminRole));

    // Try to demote role
    $this->actingAs($soleSuperAdmin, 'admin')
        ->put(route('admin.users.update', $soleSuperAdmin), [
            'name' => 'Sole Super Admin',
            'email' => $soleSuperAdmin->email,
            'role' => 'Manager',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['role' => 'Cannot remove the last Super Admin role.']);

    expect($soleSuperAdmin->fresh()->hasRole('Super Admin'))->toBeTrue();

    // Try to deactivate
    $this->actingAs($soleSuperAdmin, 'admin')
        ->put(route('admin.users.update', $soleSuperAdmin), [
            'name' => 'Sole Super Admin',
            'email' => $soleSuperAdmin->email,
            'role' => 'Super Admin',
            'is_active' => false,
        ])
        ->assertSessionHasErrors(['is_active']);
});
