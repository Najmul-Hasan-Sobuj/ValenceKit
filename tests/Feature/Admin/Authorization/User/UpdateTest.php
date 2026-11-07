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

it('prevents an editor from assigning a more privileged role to themselves', function () {
    $role = Role::create(['name' => 'Privileged', 'guard_name' => 'admin']);
    $role->givePermissionTo('roles.edit');

    $this->actingAs($this->admin, 'admin')->put(route('admin.users.update', $this->admin), [
        'name' => $this->admin->name, 'email' => $this->admin->email,
        'role' => $role->name, 'is_active' => true,
    ])->assertSessionHasErrors('role');

    expect($this->admin->fresh()->hasRole($role))->toBeFalse();
});

it('rejects invalid role values when updating an administrator', function ($role) {
    $target = Admin::factory()->create();

    $this->actingAs($this->admin, 'admin')->put(route('admin.users.update', $target), [
        'name' => $target->name, 'email' => $target->email, 'role' => $role, 'is_active' => true,
    ])->assertSessionHasErrors('role');
})->with(['unknown role' => 'Not a role', 'invalid type' => [['invalid']]]);

it('prevents changing an administrator with higher permissions', function () {
    $target = Admin::factory()->create();
    $target->givePermissionTo('roles.edit');

    $this->actingAs($this->admin, 'admin')->put(route('admin.users.update', $target), [
        'name' => $target->name, 'email' => 'changed@example.com',
        'role' => $this->admin->roles->first()->name, 'is_active' => true,
    ])->assertSessionHasErrors('role');

    expect($target->fresh()->email)->not->toBe('changed@example.com');
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

it('does not count inactive super admins when protecting the last active super admin', function () {
    $active = Admin::factory()->create(['is_active' => true]);
    $active->assignRole('Super Admin');
    $inactive = Admin::factory()->create(['is_active' => false]);
    $inactive->assignRole('Super Admin');

    $this->actingAs($active, 'admin')->put(route('admin.users.update', $active), [
        'name' => $active->name, 'email' => $active->email, 'role' => 'Staff', 'is_active' => true,
    ])->assertSessionHasErrors('role');

    expect($active->fresh()->hasRole('Super Admin'))->toBeTrue();
});
