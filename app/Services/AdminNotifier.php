<?php

namespace App\Services;

use App\Jobs\FanOutAdminNotification;
use App\Models\Admin;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;

class AdminNotifier
{
    public function send(AdminNotification $notification, bool $allowQueue = true): void
    {
        $notification->id ??= (string) Str::uuid();
        DB::afterCommit(fn () => $this->deliver($notification, $allowQueue));
    }

    private function deliver(AdminNotification $notification, bool $allowQueue): void
    {
        $permission = Permission::query()->where('guard_name', 'admin')->where('name', $notification->permission)->first();
        if (! $permission) {
            return;
        }
        if ($allowQueue && Admin::query()->where('is_active', true)->permission($permission)->count() > 50) {
            FanOutAdminNotification::dispatch($notification)->afterCommit();

            return;
        }
        Admin::query()->where('is_active', true)->permission($permission)->chunkById(100, function ($admins) use ($notification): void {
            foreach ($admins as $admin) {
                $copy = clone $notification;
                $copy->id = Uuid::uuid5(Uuid::NAMESPACE_URL, $notification->id.':'.$admin->id)->toString();
                if (! $admin->notifications()->whereKey($copy->id)->exists()) {
                    $admin->notify($copy);
                }
            }
        });
    }
}
