<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasskeyController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Authorization\UserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Settings\PasskeyController as SettingsPasskeyController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SecurityController;
use App\Http\Middleware\Admin\Authenticate;
use App\Http\Middleware\Admin\EnsureAdminIsActive;
use App\Http\Middleware\Admin\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix(config('admin.alias'))
    ->name('admin.')
    ->group(function () {

        // Guest-only admin routes (login, password reset, passkeys)
        Route::middleware(RedirectIfAuthenticated::class)->group(function () {
            Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('login.store');

            Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

            Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('password.email');

            Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

            Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.update');

            Route::get('passkeys/options', [PasskeyController::class, 'options'])
                ->middleware('throttle:10,1')
                ->name('passkeys.options');

            Route::post('passkeys/authenticate', [PasskeyController::class, 'authenticate'])
                ->middleware('throttle:6,1')
                ->name('passkeys.authenticate');
        });

        // Authenticated admin routes
        Route::middleware([Authenticate::class.':admin', EnsureAdminIsActive::class])->group(function () {
            Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            Route::get('dashboard', DashboardController::class)
                ->name('dashboard');

            Route::apiResource('authorization/users', UserController::class);
            Route::inertia('authorization/roles', 'admin/authorization/Roles')->name('authorization.roles');
            Route::inertia('authorization/roles/create', 'admin/authorization/RoleCreate')->name('authorization.roles.create');
            Route::get('authorization/roles/{id}/edit', function ($id) {
                return inertia('admin/authorization/RoleEdit', ['roleId' => (int) $id]);
            })->name('authorization.roles.edit');

            Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
            Route::put('settings/password', [SecurityController::class, 'update'])->name('security.update');
            Route::inertia('settings/appearance', 'admin/settings/Appearance')->name('appearance.edit');

            Route::get('passkeys/register/options', [SettingsPasskeyController::class, 'options'])->name('passkeys.register.options');
            Route::post('passkeys/register', [SettingsPasskeyController::class, 'store'])->name('passkeys.register');
            Route::delete('passkeys/{adminPasskey}', [SettingsPasskeyController::class, 'destroy'])->name('passkeys.destroy');
        });
    });
