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
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\ProposalController;
use App\Http\Controllers\Admin\Settings\FinanceSettingsController;
use App\Http\Controllers\Admin\Settings\McpSettingsController;
use App\Http\Controllers\Admin\Settings\PasskeyController as SettingsPasskeyController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SecurityController;
use App\Http\Controllers\Admin\Settings\TrackingSettingsController;
use App\Http\Middleware\Admin\EnsureAdminIsActive;
use Illuminate\Broadcasting\BroadcastController;
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
            Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
            Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::patch('read-all', 'readAll')->name('read-all');
                Route::patch('{notification}/read', 'read')->whereUuid('notification')->name('read');
                Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
            });

            Route::get('dashboard', DashboardController::class)
                ->name('dashboard');

            Route::prefix('authorization')->group(function () {
                Route::apiResource('users', UserController::class)->except(['show']);
                Route::resource('roles', RoleController::class)->names('authorization.roles')->except(['show']);
            });

            Route::prefix('leads')->name('leads.')->group(function () {
                Route::controller(LeadController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('export', 'export')->name('export');
                    Route::delete('{lead}', 'destroy')->name('destroy');
                });

                Route::prefix('tokens')->name('tokens.')->controller(LeadTokenController::class)->group(function () {
                    Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
                    Route::delete('{token}', 'destroy')->name('destroy');
                });
            });

            Route::prefix('contact')->name('contact.')->group(function () {
                Route::controller(ContactMessageController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('more', 'more')->name('more');
                    Route::post('start', 'start')->middleware('throttle:10,1,contact-send')->name('start');
                    Route::post('sync', 'sync')->middleware('throttle:6,1,contact-sync')->name('sync');
                    Route::get('{contactMessage}/messages', 'messages')->name('messages');
                    Route::post('{contactMessage}/reply', 'reply')->middleware('throttle:10,1,contact-send')->name('reply');
                });

                Route::prefix('gmail')->name('gmail.')->group(function () {
                    Route::controller(GmailConnectionController::class)->group(function () {
                        Route::get('connect', 'connect')->middleware('throttle:6,1,contact-connect')->name('connect');
                        Route::get('callback', 'callback')->name('callback');
                        Route::delete('disconnect', 'disconnect')->middleware('throttle:6,1,contact-disconnect')->name('disconnect');
                    });

                    Route::controller(ContactMessageController::class)->group(function () {
                        Route::post('search', 'searchGmail')->middleware('throttle:30,1,contact-search')->name('search');
                        Route::post('import', 'importGmail')->middleware('throttle:6,1,contact-import')->name('import');
                        Route::get('import/{batch}', 'gmailImportStatus')->name('import-status');
                    });
                });
            });

            Route::prefix('hr')->name('hr.')->controller(HrController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('export', 'export')->name('export');
                Route::post('employees', 'employee')->name('employees.store');
                Route::put('employees/{employee}', 'employee')->name('employees.update');
                Route::post('settings', 'settings')->name('settings.store');
                Route::delete('settings/{kind}/{id}', 'deleteSetting')->name('settings.destroy');
                Route::post('leave', 'leave')->name('leave.store');
                Route::post('leave/{leave}/review', 'review')->name('leave.review');
                Route::post('clock', 'clock')->name('clock');
                Route::post('attendance', 'attendance')->name('attendance.store');
                Route::post('corrections/{correction}', 'correction')->name('corrections.review');
            });

            Route::prefix('payroll')->name('payroll.')->controller(PayrollController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
            });

            Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('{invoice}/payments', 'payment')->name('payments.store');
                Route::post('{invoice}/issue', 'issue')->name('issue');
            });

            Route::post('proposals/{proposal}/invite', [ProposalController::class, 'invite'])->middleware('throttle:3,1,proposal-invite')->name('proposals.invite');
            Route::get('proposals/{proposal}/preview', [ProposalController::class, 'preview'])->name('proposals.preview');
            Route::post('proposals/{proposal}/revise', [ProposalController::class, 'revise'])->name('proposals.revise');
            Route::post('proposals/{proposal}/transfer', [ProposalController::class, 'transfer'])->name('proposals.transfer');
            Route::post('proposals/{proposal}/invoice', [ProposalController::class, 'invoice'])->name('proposals.invoice');
            Route::post('proposals/{proposal}/receipt', [ProposalController::class, 'receipt'])->middleware('throttle:3,1,proposal-receipt')->name('proposals.receipt');
            Route::post('proposals/{proposal}/remind', [ProposalController::class, 'remind'])->middleware('throttle:3,1,proposal-remind')->name('proposals.remind');
            Route::post('proposals/{proposal}/password-reset', [ProposalController::class, 'passwordReset'])->middleware('throttle:3,1,proposal-password-reset')->name('proposals.password-reset');
            Route::apiResource('proposals', ProposalController::class)
                ->except(['show'])
                ->names('proposals');

            Route::prefix('finance')->name('finance.')->controller(FinanceController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('summary', 'summary')->name('summary');
                Route::get('export', 'export')->name('export');
                Route::post('transactions', 'store')->name('transactions.store');
                Route::post('transactions/bulk-destroy', 'bulkDestroy')->name('transactions.bulk-destroy');
                Route::put('transactions/{transaction}', 'update')->name('transactions.update');
                Route::delete('transactions/{transaction}', 'destroy')->name('transactions.destroy');
            });

            Route::apiResource('categories', CategoryController::class)
                ->except(['show'])
                ->names('categories');

            Route::get('mcp/usage', [McpUsageController::class, 'index'])->name('mcp.usage.index');

            Route::prefix('audit-logs')->name('audit-logs.')->controller(AuditLogController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('export', 'export')->name('export');
            });

            Route::prefix('settings')->group(function () {
                Route::controller(ProfileController::class)->group(function () {
                    Route::get('profile', 'edit')->name('profile.edit');
                    Route::patch('profile', 'update')->name('profile.update');
                });

                Route::controller(SecurityController::class)->group(function () {
                    Route::get('security', 'edit')->name('security.edit');
                    Route::put('password', 'update')->name('security.update');
                });

                Route::get('appearance', function (Request $request) {
                    abort_unless($request->user('admin')->can('settings.appearance.view'), 403);

                    return Inertia::render('admin/settings/Appearance');
                })->name('appearance.edit');

                Route::prefix('mcp')->name('mcp.')->controller(McpSettingsController::class)->group(function () {
                    Route::get('/', 'edit')->name('edit');
                    Route::post('tools/sync', 'sync')->name('tools.sync');
                    Route::patch('tools/{mcpTool}', 'update')->name('tools.update');
                    Route::post('tokens', 'store')->name('tokens.store');
                    Route::delete('tokens/{tokenId}', 'destroy')->name('tokens.destroy');
                });

                Route::controller(FinanceSettingsController::class)->group(function () {
                    Route::get('finance', 'edit')->name('finance.settings.edit');
                    Route::patch('finance', 'update')->name('finance.settings.update');
                });

                Route::controller(TrackingSettingsController::class)->group(function () {
                    Route::get('tracking', 'edit')->name('tracking.settings.edit');
                    Route::patch('tracking', 'update')->name('tracking.settings.update');
                });
            });

            Route::prefix('passkeys')->name('passkeys.')->controller(SettingsPasskeyController::class)->group(function () {
                Route::get('register/options', 'options')->name('register.options');
                Route::post('register', 'store')->name('register');
                Route::delete('{passkey}', 'destroy')->name('destroy');
            });

            Route::delete('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        });
    });
