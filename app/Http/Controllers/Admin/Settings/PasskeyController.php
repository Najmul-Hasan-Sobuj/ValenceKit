<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Passkey;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialCreationOptions;

class PasskeyController extends Controller
{
    public function options(Request $request, GenerateRegistrationOptions $generate): JsonResponse
    {
        abort_unless($request->user('admin')->can('settings.security.edit'), 403);

        $options = $generate($request->user('admin'));

        $request->session()->put('admin_passkey.registration_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(PasskeyRegistrationRequest $request, StorePasskey $store, AuditLogService $auditLogService): JsonResponse
    {
        abort_unless($request->user('admin')->can('settings.security.edit'), 403);

        $serialized = $request->session()->pull('admin_passkey.registration_options');

        abort_unless(is_string($serialized), 422, 'Passkey registration session expired. Please try again.');

        $passkey = $store(
            $request->user('admin'),
            $request->string('name')->toString(),
            $request->credential(),
            WebAuthn::fromJson($serialized, PublicKeyCredentialCreationOptions::class),
        );

        $auditLogService->record(
            'settings.security.passkey_registered',
            $passkey,
            afterValues: ['name' => $passkey->name, 'authenticator' => $passkey->authenticator],
            description: 'A passkey was registered.',
            actor: $request->user('admin'),
        );

        return response()->json(['status' => 'passkey-registered', 'id' => (string) $passkey->id]);
    }

    public function destroy(Request $request, Passkey $passkey, AuditLogService $auditLogService): RedirectResponse
    {
        abort_unless($request->user('admin')->can('settings.security.edit'), 403);
        abort_unless((string) $passkey->user_id === (string) Auth::guard('admin')->id() && $passkey->user_type === Admin::class, 404);

        $before = $passkey->only(['name', 'authenticator']);
        $passkey->delete();

        $auditLogService->record(
            'settings.security.passkey_deleted',
            $passkey,
            beforeValues: $before,
            description: 'A passkey was removed.',
            actor: $request->user('admin'),
        );

        return back();
    }
}
