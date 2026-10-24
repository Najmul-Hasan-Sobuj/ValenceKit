<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the admin login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Authenticate the admin and start a session.
     */
    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages([
                'email' => __('Your account has been deactivated. Please contact support.'),
            ]);
        }

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        $auditLogService->record(
            'admin.login',
            $admin,
            description: 'An administrator signed in.',
            actor: $admin,
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Destroy the admin session and log out.
     */
    public function destroy(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $admin = $request->user('admin');
        Auth::guard('admin')->logout();

        $auditLogService->record(
            'admin.logout',
            $admin,
            description: 'An administrator signed out.',
            actor: $admin,
        );

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
