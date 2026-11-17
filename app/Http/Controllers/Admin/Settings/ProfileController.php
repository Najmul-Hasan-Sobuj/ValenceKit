<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use App\Services\WorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user('admin')->can('settings.profile.view'), 403);

        return Inertia::render('admin/settings/Profile');
    }

    public function update(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.profile.edit'), 403);

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $before = $admin->auditLogValues();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(Admin::class)->ignore($admin->id)],
        ]);

        DB::transaction(function () use ($admin, $validated, $before, $auditLogService): void {
            $admin->fill($validated);

            if ($admin->isDirty('email')) {
                $admin->email_verified_at = null;
            }

            $admin->save();

            $auditLogService->updated(
                'settings.profile.updated',
                $admin,
                beforeValues: $before,
                description: 'The administrator profile was updated.',
                actor: $admin,
            );

            app(WorkflowNotifier::class)->account($admin, 'account.self', 'Profile updated', 'Your administrator profile was updated.', 'admin.dashboard');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('admin.profile.edit');
    }
}
