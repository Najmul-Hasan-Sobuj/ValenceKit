<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class HrPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (RoleAndPermissionSeeder::hrPermissions() as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin'], ['group_name' => 'People & HR']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('admin_all_permissions');
    }
}
