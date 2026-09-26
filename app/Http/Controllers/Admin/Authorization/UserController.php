<?php

namespace App\Http\Controllers\Admin\Authorization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Authorization\StoreUserRequest;
use App\Http\Requests\Admin\Authorization\UpdateUserRequest;
use App\Models\Admin;
use App\Notifications\AdminInviteNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the admins.
     */
    public function index(Request $request)
    {
        abort_unless($request->user('admin')->can('users.view'), 403);

        $admins = Admin::with(['roles', 'passkeys'])
            ->when($request->input('search'), fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($request->input('role'), fn ($q, $roleName) => $q->whereHas('roles', fn ($q) => $q->where('name', $roleName)))
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
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    /**
     * Store a newly created admin in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $defaultPassword = 'Password123!';

        $admin = Admin::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($defaultPassword),
            'is_active' => $request->validated('is_active', true),
            'must_change_password' => true,
        ]);

        $admin->assignRole($request->validated('role'));

        $admin->notify(new AdminInviteNotification($defaultPassword));

        return back()->with('success', 'User invited successfully. Default password is: '.$defaultPassword);
    }

    /**
     * Update the specified admin in storage.
     */
    public function update(UpdateUserRequest $request, Admin $user)
    {
        $user->update([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'is_active' => $request->validated('is_active', true),
        ]);

        $user->syncRoles([$request->validated('role')]);

        return back()->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified admin from storage.
     */
    public function destroy(Request $request, Admin $user)
    {
        abort_unless($request->user('admin')->can('users.delete'), 403);

        if ($request->user('admin')->is($user)) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($user->hasRole('Super Admin') && Admin::role('Super Admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the last Super Admin.');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
