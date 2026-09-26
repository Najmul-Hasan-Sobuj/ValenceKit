<?php

use App\Models\Admin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure roles and permissions are seeded
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);

    // Create an admin with users.view permission
    $this->admin = Admin::factory()->create();
    $role = Role::create(['name' => 'View Users Role', 'guard_name' => 'admin']);
    $role->givePermissionTo(Permission::where('name', 'users.view')->first());
    $this->admin->assignRole($role);
});

it('can list users with metrics', function () {
    // Create some extra admins
    Admin::factory()->count(2)->create(['is_active' => true]);
    Admin::factory()->count(1)->create(['is_active' => false]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/authorization/Users')
            ->has('admins.data', 4) // 3 newly created + 1 current admin
            ->has('metrics', fn ($page) => $page
                ->where('total', 4)
                ->where('active', 3)
                ->has('mfaEnabled')
                ->has('mfaMissing')
            )
        );
});
