<?php

use App\Models\Admin;
use App\Notifications\AdminNotification;
use App\Services\AdminNotifier;
use Database\Seeders\RoleAndPermissionSeeder;

it('delivers notifications only to active permitted accounts and deduplicates retried batches', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $allowed = Admin::factory()->create(['is_active' => true]);
    $inactive = Admin::factory()->create(['is_active' => false]);
    $denied = Admin::factory()->create(['is_active' => true]);
    $allowed->givePermissionTo('finance.view');
    $inactive->givePermissionTo('finance.view');
    $notification = new AdminNotification('finance.view', 'Update', 'A finance update.', 'admin.finance.index');
    app(AdminNotifier::class)->send($notification);
    app(AdminNotifier::class)->send($notification);
    expect($allowed->notifications()->count())->toBe(1)
        ->and($inactive->notifications()->count())->toBe(0)
        ->and($denied->notifications()->count())->toBe(0);
});
