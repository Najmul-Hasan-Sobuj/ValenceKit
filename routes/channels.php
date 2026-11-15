<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.Admin.{id}', fn (Admin $admin, string $id): bool => $admin->is_active && (string) $admin->id === $id, ['guards' => ['admin']]);
Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => $user->hasVerifiedEmail() && (string) $user->id === $id, ['guards' => ['web']]);
