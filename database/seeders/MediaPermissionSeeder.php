<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MediaPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::firstOrCreate(['name' => 'media.'.$action, 'guard_name' => 'admin'], ['group_name' => 'Media Library']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('admin_all_permissions');
    }
}
