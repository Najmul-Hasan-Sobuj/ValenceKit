<?php

use App\Models\Admin;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('caches all permissions for super admin in inertia shared props', function () {
    Cache::forget('admin_all_permissions');
    expect(Cache::has('admin_all_permissions'))->toBeFalse();

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.admin.permissions')
        );

    expect(Cache::has('admin_all_permissions'))->toBeTrue();
    $cached = Cache::get('admin_all_permissions');
    expect($cached)->toBeArray()
        ->and($cached)->toContain('users.view');
});

it('invalidates permission cache when permission is saved or deleted', function () {
    Cache::put('admin_all_permissions', ['dummy.permission']);
    expect(Cache::has('admin_all_permissions'))->toBeTrue();

    // Create / save new permission triggers observer
    $perm = Permission::create(['name' => 'test.dynamic.perm', 'guard_name' => 'admin']);
    expect(Cache::has('admin_all_permissions'))->toBeFalse();

    Cache::put('admin_all_permissions', ['dummy.permission']);
    expect(Cache::has('admin_all_permissions'))->toBeTrue();

    $perm->delete();
    expect(Cache::has('admin_all_permissions'))->toBeFalse();
});
