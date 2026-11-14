<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasskeyController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Authorization\RoleController;
use App\Http\Controllers\Admin\Authorization\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GmailConnectionController;
use App\Http\Controllers\Admin\HrController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadTokenController;
use App\Http\Controllers\Admin\McpUsageController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\Settings\FinanceSettingsController;
use App\Http\Controllers\Admin\Settings\McpSettingsController;
use App\Http\Controllers\Admin\Settings\PasskeyController as SettingsPasskeyController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SecurityController;
use App\Http\Controllers\Admin\Settings\TrackingSettingsController;
use App\Http\Middleware\Admin\EnsureAdminIsActive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix(config('admin.alias'))
    ->name('admin.')
    ->group(function () {

        // Guest-only admin routes (login, password reset, passkeys)
        Route::middleware('guest:admin')->group(function () {
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
        Route::middleware(['auth:admin', EnsureAdminIsActive::class])->group(function () {
            Route::get('contact', [ContactMessageController::class, 'index'])->name('contact.index');
            Route::get('contact/more', [ContactMessageController::class, 'more'])->name('contact.more');
            Route::get('contact/{contactMessage}/messages', [ContactMessageController::class, 'messages'])->name('contact.messages');
            Route::get('contact/gmail/connect', [GmailConnectionController::class, 'connect'])->middleware('throttle:6,1,contact-connect')->name('contact.gmail.connect');
            Route::delete('contact/gmail/disconnect', [GmailConnectionController::class, 'disconnect'])->middleware('throttle:6,1,contact-disconnect')->name('contact.gmail.disconnect');
            Route::get('contact/gmail/callback', [GmailConnectionController::class, 'callback'])->name('contact.gmail.callback');
            Route::post('contact/start', [ContactMessageController::class, 'start'])->middleware('throttle:10,1,contact-send')->name('contact.start');
            Route::post('contact/sync', [ContactMessageController::class, 'sync'])->middleware('throttle:6,1,contact-sync')->name('contact.sync');
            Route::post('contact/gmail/search', [ContactMessageController::class, 'searchGmail'])->middleware('throttle:30,1,contact-search')->name('contact.gmail.search');
            Route::post('contact/gmail/import', [ContactMessageController::class, 'importGmail'])->middleware('throttle:6,1,contact-import')->name('contact.gmail.import');
            Route::get('contact/gmail/import/{batch}', [ContactMessageController::class, 'gmailImportStatus'])->name('contact.gmail.import-status');
            Route::post('contact/{contactMessage}/reply', [ContactMessageController::class, 'reply'])->middleware('throttle:10,1,contact-send')->name('contact.reply');
            Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
            Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->name('invoices.payments.store');
            Route::get('hr', [HrController::class, 'index'])->name('hr.index');
            Route::post('hr/employees', [HrController::class, 'employee'])->name('hr.employees.store');
            Route::put('hr/employees/{employee}', [HrController::class, 'employee'])->name('hr.employees.update');
            Route::post('hr/settings', [HrController::class, 'settings'])->name('hr.settings.store');
            Route::delete('hr/settings/{kind}/{id}', [HrController::class, 'deleteSetting'])->name('hr.settings.destroy');
            Route::post('hr/leave', [HrController::class, 'leave'])->name('hr.leave.store');
            Route::post('hr/leave/{leave}/review', [HrController::class, 'review'])->name('hr.leave.review');
            Route::post('hr/clock', [HrController::class, 'clock'])->name('hr.clock');
            Route::post('hr/attendance', [HrController::class, 'attendance'])->name('hr.attendance.store');
            Route::post('hr/corrections/{correction}', [HrController::class, 'correction'])->name('hr.corrections.review');
            Route::get('hr/export', [HrController::class, 'export'])->name('hr.export');
            Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
            Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
            Route::post('leads/tokens', [LeadTokenController::class, 'store'])->middleware('throttle:6,1')->name('leads.tokens.store');
            Route::delete('leads/tokens/{token}', [LeadTokenController::class, 'destroy'])->name('leads.tokens.destroy');
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
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
            Route::get('settings/mcp', [McpSettingsController::class, 'edit'])->name('mcp.edit');
            Route::patch('settings/mcp/tools/{mcpTool}', [McpSettingsController::class, 'update'])->name('mcp.tools.update');
            Route::post('settings/mcp/tools/sync', [McpSettingsController::class, 'sync'])->name('mcp.tools.sync');
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

            Route::get('settings/tracking', [TrackingSettingsController::class, 'edit'])->name('tracking.settings.edit');
            Route::patch('settings/tracking', [TrackingSettingsController::class, 'update'])->name('tracking.settings.update');

            Route::get('passkeys/register/options', [SettingsPasskeyController::class, 'options'])->name('passkeys.register.options');
            Route::post('passkeys/register', [SettingsPasskeyController::class, 'store'])->name('passkeys.register');
            Route::delete('passkeys/{passkey}', [SettingsPasskeyController::class, 'destroy'])->name('passkeys.destroy');
        });
    });
