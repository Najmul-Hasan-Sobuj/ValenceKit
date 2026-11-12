<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class BillingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['invoices.view' => 'Invoices', 'invoices.create' => 'Invoices', 'invoices.payments.create' => 'Invoices', 'payroll.view' => 'Payroll', 'payroll.create' => 'Payroll'] as $name => $group) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin'], ['group_name' => $group]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('admin_all_permissions');
    }
}
