<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->must_change_password || $admin->can('settings.security.view'), 403);

        return Inertia::render('admin/settings/Security', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passkeys' => $admin->passkeys()
                ->latest()
                ->get()
                ->map(fn ($passkey) => [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'authenticator' => $passkey->authenticator,
                    'created_at_diff' => $passkey->created_at->diffForHumans(),
                    'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                ])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->must_change_password || $admin->can('settings.security.edit'), 403);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $request->user('admin')->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
