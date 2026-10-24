<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Actions\Admin\VerifyAdminPasskey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPasskeyVerificationRequest;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;

class PasskeyController extends Controller
{
    /**
     * Generate WebAuthn authentication options for admin passkey login.
     *
     * Stores the challenge in an admin-specific session key to avoid
     * collisions with the user passkey session.
     */
    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate(null);

        $request->session()->put('admin_passkey.verification_options', WebAuthn::toJson($options));

        return response()->json([
            'options' => WebAuthn::toBrowserArray($options),
        ]);
    }

    /**
     * Verify the admin passkey credential and start an admin session.
     */
    public function authenticate(
        AdminPasskeyVerificationRequest $request,
        VerifyAdminPasskey $verify,
        AuditLogService $auditLogService,
    ): JsonResponse {
        try {
            $passkey = $verify($request->credential(), $request->verificationOptions());
        } catch (InvalidPasskeyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /** @var Admin $admin */
        $admin = $passkey->user;

        if (! $admin->is_active) {
            return response()->json([
                'message' => __('Your account has been deactivated. Please contact support.'),
            ], 403);
        }

        Auth::guard('admin')->login($admin, $request->remember());

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

        return response()->json([
            'redirect' => route('admin.dashboard'),
        ]);
    }
}
