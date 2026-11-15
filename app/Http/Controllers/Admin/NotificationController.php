<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\NotificationController as UserNotificationController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationController extends UserNotificationController
{
    protected function visible(Request $request): Builder
    {
        $admin = $request->user('admin');

        return $admin->notifications()->getQuery()->whereIn('data->permission', $admin->getAllPermissions()->pluck('name'));
    }
}
