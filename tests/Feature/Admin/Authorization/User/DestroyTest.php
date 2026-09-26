<?php

use App\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    $this->admin = Admin::factory()->create();
    $role = Role::create(['name' => 'Delete Users Role', 'guard_name' => 'admin']);
    $role->givePermissionTo(Permission::where('name', 'users.delete')->first());
    $this->admin->assignRole($role);
});

it('can delete a user', function () {
    $targetUser = Admin::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.users.destroy', $targetUser))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('admins', [
        'id' => $targetUser->id,
    ]);
});

it('cannot delete the last super admin', function () {
    $targetUser = Admin::factory()->create();
    $targetUser->assignRole('Super Admin');

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.users.destroy', $targetUser))
        ->assertRedirect()
        ->assertSessionHas('error', 'Cannot delete the last Super Admin.');

    $this->assertDatabaseHas('admins', [
        'id' => $targetUser->id,
    ]);
});

it('cannot delete yourself', function () {
    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.users.destroy', $this->admin))
        ->assertRedirect()
        ->assertSessionHas('error', 'You cannot delete yourself.');

    $this->assertDatabaseHas('admins', [
        'id' => $this->admin->id,
    ]);
});
