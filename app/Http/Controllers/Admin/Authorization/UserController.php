<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Authorization\IndexUserRequest;
use App\Http\Requests\Admin\Authorization\StoreUserRequest;
use App\Http\Requests\Admin\Authorization\UpdateUserRequest;
use App\Models\Admin;
use App\Notifications\AdminInviteNotification;
use App\Services\AuditLogService;
use App\Services\WorkflowNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the admins.
     */
    public function index(IndexUserRequest $request)
    {
        $validated = $request->validated();

        $admins = Admin::with(['roles', 'passkeys'])
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($validated['role'] ?? null, fn ($q, $roleName) => $q->whereHas('roles', fn ($q) => $q->where('name', $roleName)))
            ->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('last_login_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('last_login_at', '<=', $to))
            ->paginate(10)->withQueryString();

        // Passkeys typically determine if MFA is enabled
        $total = Admin::count();
        $active = Admin::where('is_active', true)->count();
        $mfaEnabled = Admin::has('passkeys')->count(); // Assuming passkeys are the primary 2FA method here
        $mfaMissing = $total - $mfaEnabled;

        return Inertia::render('admin/authorization/Users', [
            'admins' => $admins,
            'roles' => Role::where('guard_name', 'admin')->get(['id', 'name']),
            'metrics' => [
                'total' => $total,
                'active' => $active,
                'mfaEnabled' => $mfaEnabled,
                'mfaMissing' => $mfaMissing,
            ],
            'filters' => [
                'search' => $validated['search'] ?? null,
                'role' => $validated['role'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }

    /**
     * Store a newly created admin in storage.
     */
    public function store(StoreUserRequest $request, AuditLogService $auditLogService)
    {
        return DB::transaction(function () use ($request, $auditLogService) {
            $temporaryPassword = Str::password(24);

            $admin = Admin::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($temporaryPassword),
                'is_active' => $request->validated('is_active', true),
                'must_change_password' => true,
            ]);

            $admin->assignRole($request->validated('role'));

            $admin->notify(new AdminInviteNotification(Password::broker('admins')->createToken($admin)));

            $auditLogService->created(
                'user.invited',
                $admin,
                description: 'User '.$admin->name.' was invited.',
                actor: $request->user('admin'),
            );

            return back()->with('success', 'User invited successfully. An invitation link was queued for delivery.');
        });
    }

    /**
     * Update the specified admin in storage.
     */
    public function update(UpdateUserRequest $request, Admin $user, AuditLogService $auditLogService)
    {
        return DB::transaction(function () use ($request, $user, $auditLogService) {
            Admin::role('Super Admin')->where('is_active', true)->lockForUpdate()->get();
            if ($user->hasRole('Super Admin') && ! $request->user('admin')->hasRole('Super Admin')) {
                return back()->with('error', 'Only Super Admins can modify a Super Admin account.');
            }

            if ($request->user('admin')->is($user) && ! $request->validated('is_active', true)) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }

            if ($user->is_active && $user->hasRole('Super Admin') && Admin::role('Super Admin')->where('is_active', true)->count() <= 1) {
                if ($request->validated('role') !== 'Super Admin' || ! $request->validated('is_active', true)) {
                    return back()->with('error', 'Cannot demote or deactivate the only remaining Super Admin.');
                }
            }

            $before = $user->auditLogValues();

            $user->update([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'is_active' => $request->validated('is_active', true),
            ]);

            $user->syncRoles([$request->validated('role')]);

            $auditLogService->updated(
                'user.updated',
                $user,
                beforeValues: $before,
                description: 'User '.$user->name.' was updated.',
                actor: $request->user('admin'),
            );
            app(WorkflowNotifier::class)->account($user, 'account.self', 'Account access updated', 'Your profile or assigned access was updated by an administrator.', 'admin.dashboard');

            return back()->with('success', 'User updated successfully.');
        });
    }

    public function resendInvitation(Request $request, Admin $user, AuditLogService $audit): RedirectResponse
    {
        $actor = $request->user('admin');
        abort_unless($actor->can('users.invite') && $actor->canDelegatePermissions($user->getAllPermissions()->pluck('name')->all()), 403);
        abort_if($user->hasRole('Super Admin') && ! $actor->hasRole('Super Admin'), 403);
        if (! $user->is_active || ! $user->must_change_password) {
            return back()->with('error', 'Only active accounts awaiting password setup can receive another invitation.');
        }
        $status = Password::broker('admins')->sendResetLink(['email' => $user->email], function (Admin $recipient, string $token): void {
            $recipient->notify(new AdminInviteNotification($token));
        });
        if ($status === Password::RESET_LINK_SENT) {
            $audit->record('user.invitation_resent', $user, actor: $actor);
        }

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', $status === Password::RESET_LINK_SENT ? 'Invitation queued for delivery.' : 'Please wait before requesting another invitation.');
    }

    /**
     * Remove the specified admin from storage.
     */
    public function destroy(Request $request, Admin $user, AuditLogService $auditLogService)
    {
        return DB::transaction(function () use ($request, $user, $auditLogService) {
            Admin::role('Super Admin')->where('is_active', true)->lockForUpdate()->get();
            abort_unless($request->user('admin')->can('users.delete'), 403);

            if ($request->user('admin')->is($user)) {
                return back()->with('error', 'You cannot delete yourself.');
            }

            if ($user->hasRole('Super Admin')) {
                if ($user->is_active && Admin::role('Super Admin')->where('is_active', true)->count() <= 1) {
                    return back()->with('error', 'Cannot delete the last Super Admin.');
                }

                if (! $request->user('admin')->hasRole('Super Admin')) {
                    return back()->with('error', 'Only Super Admins can delete a Super Admin account.');
                }
            }

            abort_unless($request->user('admin')->canDelegatePermissions($user->getAllPermissions()->pluck('name')->all()), 403);

            $before = $user->auditLogValues();
            $userName = $user->name;
            $user->delete();

            $auditLogService->deleted(
                'user.deleted',
                $user,
                beforeValues: $before,
                description: 'User '.$userName.' was deleted.',
                actor: $request->user('admin'),
            );

            return back()->with('success', 'User deleted successfully.');
        });
    }
}
