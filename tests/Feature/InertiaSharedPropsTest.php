<?php

use App\Models\Admin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder']);
});

test('public guest requests do not share finance props', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance', null)
        );
});

test('authenticated web user requests do not share finance props', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance', null)
        );
});

test('authenticated admin without finance.view permission does not receive finance props', function () {
    $admin = Admin::factory()->create();
    $role = Role::create(['name' => 'Support Only', 'guard_name' => 'admin']);
    $role->syncPermissions(['users.view']);
    $admin->assignRole($role);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('finance', null)
        );
});

test('authenticated admin with finance.view receives finance props', function () {
    $admin = Admin::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('finance')
            ->has('finance.currency')
            ->has('finance.categories')
            ->has('finance.payment_methods')
        );
});
