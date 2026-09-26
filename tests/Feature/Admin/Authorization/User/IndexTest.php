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

it('can filter users by last login date range', function () {
    // Admin with login 10 days ago
    Admin::factory()->create([
        'name' => 'Old Login Admin',
        'last_login_at' => now()->subDays(10),
    ]);

    // Admin with login today
    Admin::factory()->create([
        'name' => 'Today Login Admin',
        'last_login_at' => now(),
    ]);

    // Admin with login 30 days ago
    Admin::factory()->create([
        'name' => 'Ancient Login Admin',
        'last_login_at' => now()->subDays(30),
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.users.index', [
            'from' => now()->subDays(12)->toDateString(),
            'to' => now()->subDays(8)->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/authorization/Users')
            ->has('admins.data', 1)
            ->where('admins.data.0.name', 'Old Login Admin')
            ->where('filters.from', now()->subDays(12)->toDateString())
            ->where('filters.to', now()->subDays(8)->toDateString())
        );
});
