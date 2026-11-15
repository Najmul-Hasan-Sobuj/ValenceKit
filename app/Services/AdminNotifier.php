<?php

namespace App\Services;

use App\Models\Admin;
use App\Notifications\AdminNotification;
use Spatie\Permission\Models\Permission;

class AdminNotifier
{
    public function send(AdminNotification $notification): void
    {
        $permission = Permission::query()->where('guard_name', 'admin')->where('name', $notification->permission)->first();
        if (! $permission) {
            return;
        }
        Admin::query()->where('is_active', true)->permission($permission)->chunkById(100, function ($admins) use ($notification): void {
            foreach ($admins as $admin) {
                $admin->notify(clone $notification);
            }
        });
    }
}
