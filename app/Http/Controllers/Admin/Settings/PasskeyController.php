<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\AdminPasskey;
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
        $options = $generate($request->user('admin'));

        $request->session()->put('admin_passkey.registration_options', WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(PasskeyRegistrationRequest $request, StorePasskey $store): JsonResponse
    {
        $serialized = $request->session()->pull('admin_passkey.registration_options');

        abort_unless(is_string($serialized), 422, 'Passkey registration session expired. Please try again.');

        $passkey = $store(
            $request->user('admin'),
            $request->string('name')->toString(),
            $request->credential(),
            WebAuthn::fromJson($serialized, PublicKeyCredentialCreationOptions::class),
        );

        return response()->json(['status' => 'passkey-registered', 'id' => (string) $passkey->id]);
    }

    public function destroy(AdminPasskey $adminPasskey): RedirectResponse
    {
        abort_unless($adminPasskey->admin_id === Auth::guard('admin')->id(), 404);

        $adminPasskey->delete();

        return back();
    }
}
