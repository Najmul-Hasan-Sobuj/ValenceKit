<?php

use App\Models\Admin;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    // Admin with ONLY users.* and settings.* permissions (no roles.*)
    $this->limitedAdmin = Admin::factory()->create();
    $role = Role::create(['name' => 'Limited Role', 'guard_name' => 'admin']);
    $role->syncPermissions([
        'users.view', 'users.invite', 'users.edit', 'users.delete',
        'settings.profile.view', 'settings.profile.edit', 'settings.security.view',
    ]);
    $this->limitedAdmin->assignRole($role);
});

it('prevents user with only setting and user permissions from accessing roles index', function () {
    $this->actingAs($this->limitedAdmin, 'admin')
        ->get(route('admin.authorization.roles.index'))
        ->assertForbidden();
});

it('prevents user with only setting and user permissions from creating roles', function () {
    $this->actingAs($this->limitedAdmin, 'admin')
        ->get(route('admin.authorization.roles.create'))
        ->assertForbidden();

    $this->actingAs($this->limitedAdmin, 'admin')
        ->post(route('admin.authorization.roles.store'), [
            'name' => 'Hacker Role',
            'permissions' => ['roles.view'],
        ])
        ->assertForbidden();
});

it('prevents non-super-admin from assigning super admin role to another user', function () {
    $this->actingAs($this->limitedAdmin, 'admin')
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'Super Admin',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['role']);
});

it('allows admin with roles.view to view roles index but not create', function () {
    $roleViewer = Admin::factory()->create();
    $role = Role::create(['name' => 'Role Viewer', 'guard_name' => 'admin']);
    $role->givePermissionTo('roles.view');
    $roleViewer->assignRole($role);

    $this->actingAs($roleViewer, 'admin')
        ->get(route('admin.authorization.roles.index'))
        ->assertOk();

    $this->actingAs($roleViewer, 'admin')
        ->get(route('admin.authorization.roles.create'))
        ->assertForbidden();
});

it('allows super admin unrestricted access via gate bypass', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.authorization.roles.index'))
        ->assertOk();

    $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.authorization.roles.create'))
        ->assertOk();
});

it('prepopulates role creation form when duplicating a role', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $staff = Role::where('name', 'Staff')->first();

    $response = $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.authorization.roles.create', ['duplicate' => $staff->id]))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('admin/authorization/RoleCreate')
        ->has('initialRole')
        ->where('initialRole.name', 'Staff (Copy)')
        ->where('initialRole.description', $staff->description)
        ->where('initialRole.permissions', $staff->permissions->pluck('name')->all())
    );
});

it('generates unique copy names on repeated duplication', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    Role::create(['name' => 'Support (Copy)', 'guard_name' => 'admin']);
    $support = Role::where('name', 'Support')->first();

    $response = $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.authorization.roles.create', ['duplicate' => $support->id]))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('initialRole.name', 'Support (Copy 2)')
    );
});

it('prevents non-super-admin from duplicating system roles', function () {
    $roleCreator = Admin::factory()->create();
    $role = Role::create(['name' => 'Role Creator', 'guard_name' => 'admin']);
    $role->givePermissionTo('roles.create');
    $roleCreator->assignRole($role);

    $superAdminRole = Role::where('name', 'Super Admin')->first();

    $this->actingAs($roleCreator, 'admin')
        ->get(route('admin.authorization.roles.create', ['duplicate' => $superAdminRole->id]))
        ->assertForbidden();
});

it('prevents renaming system roles such as Super Admin', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $superAdminRole = Role::where('name', 'Super Admin')->first();

    $response = $this->actingAs($superAdmin, 'admin')
        ->put(route('admin.authorization.roles.update', $superAdminRole), [
            'name' => 'Renamed Super Admin',
            'description' => 'Attempted rename',
            'permissions' => $superAdminRole->permissions->pluck('name')->all(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'System role names cannot be changed.');
    expect($superAdminRole->fresh()->name)->toBe('Super Admin');
});

it('accurately counts role members using native withCount on index and prevents deletion when members assigned', function () {
    $superAdmin = Admin::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $customRole = Role::create(['name' => 'Custom Editor', 'guard_name' => 'admin']);
    $editor1 = Admin::factory()->create();
    $editor2 = Admin::factory()->create();
    $editor1->assignRole($customRole);
    $editor2->assignRole($customRole);

    $this->actingAs($superAdmin, 'admin')
        ->get(route('admin.authorization.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/authorization/Roles')
            ->has('roles', fn ($roles) => $roles
                ->where('data', fn ($collection) => collect($collection)->contains(
                    fn ($item) => $item['name'] === 'Custom Editor' && $item['members'] === 2
                ))
            )
        );

    $this->actingAs($superAdmin, 'admin')
        ->delete(route('admin.authorization.roles.destroy', $customRole))
        ->assertRedirect()
        ->assertSessionHas('error', 'Cannot delete role with assigned members.');

    expect(Role::where('id', $customRole->id)->exists())->toBeTrue();
});
