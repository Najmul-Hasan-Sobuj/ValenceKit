<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasskeyController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Authorization\RoleController;
use App\Http\Controllers\Admin\Authorization\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\McpUsageController;
use App\Http\Controllers\Admin\Settings\FinanceSettingsController;
use App\Http\Controllers\Admin\Settings\McpController;
use App\Http\Controllers\Admin\Settings\McpSettingsController;
use App\Http\Controllers\Admin\Settings\PasskeyController as SettingsPasskeyController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SecurityController;
use App\Http\Middleware\Admin\Authenticate;
use App\Http\Middleware\Admin\EnsureAdminIsActive;
use App\Http\Middleware\Admin\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

            Route::apiResource('authorization/users', UserController::class)->except(['show']);
            Route::resource('authorization/roles', RoleController::class)->names('authorization.roles')->except(['show']);

            Route::apiResource('categories', CategoryController::class)
                ->except(['show'])
                ->names('categories');

            Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
            Route::put('settings/password', [SecurityController::class, 'update'])->name('security.update');
            Route::get('settings/appearance', function (Request $request) {
                abort_unless($request->user('admin')->can('settings.appearance.view'), 403);

                return Inertia::render('admin/settings/Appearance');
            })->name('appearance.edit');

            Route::get('mcp/usage', [McpUsageController::class, 'index'])->name('mcp.usage.index');
            Route::get('settings/mcp', [McpSettingsController::class, 'edit'])->name('mcp.edit');
            Route::patch('settings/mcp/tools/{mcpTool}', [McpController::class, 'update'])->name('mcp.tools.update');
            Route::post('settings/mcp/tools/sync', [McpController::class, 'sync'])->name('mcp.tools.sync');
            Route::post('settings/mcp/tokens', [McpSettingsController::class, 'store'])->name('mcp.tokens.store');
            Route::delete('settings/mcp/tokens/{tokenId}', [McpSettingsController::class, 'destroy'])->name('mcp.tokens.destroy');

            Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
            Route::get('finance/summary', [FinanceController::class, 'summary'])->name('finance.summary');
            Route::post('finance/transactions', [FinanceController::class, 'store'])->name('finance.transactions.store');
            Route::put('finance/transactions/{transaction}', [FinanceController::class, 'update'])->name('finance.transactions.update');
            Route::delete('finance/transactions/{transaction}', [FinanceController::class, 'destroy'])->name('finance.transactions.destroy');
            Route::post('finance/transactions/bulk-destroy', [FinanceController::class, 'bulkDestroy'])->name('finance.transactions.bulk-destroy');
            Route::get('finance/export', [FinanceController::class, 'export'])->name('finance.export');

            Route::get('settings/finance', [FinanceSettingsController::class, 'edit'])->name('finance.settings.edit');
            Route::patch('settings/finance', [FinanceSettingsController::class, 'update'])->name('finance.settings.update');

            Route::get('passkeys/register/options', [SettingsPasskeyController::class, 'options'])->name('passkeys.register.options');
            Route::post('passkeys/register', [SettingsPasskeyController::class, 'store'])->name('passkeys.register');
            Route::delete('passkeys/{adminPasskey}', [SettingsPasskeyController::class, 'destroy'])->name('passkeys.destroy');
        });
    });
