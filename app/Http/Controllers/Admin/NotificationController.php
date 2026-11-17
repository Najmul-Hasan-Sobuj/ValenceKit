<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\NotificationController as UserNotificationController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends UserNotificationController
{
    /** @return Builder<DatabaseNotification> */
    protected function visible(Request $request): Builder
    {
        $admin = $request->user('admin');

        return $admin->notifications()->getQuery()->where(fn ($query) => $query->whereIn('data->permission', $admin->getAllPermissions()->pluck('name'))->orWhere('data->permission', 'account.self'));
    }
}
