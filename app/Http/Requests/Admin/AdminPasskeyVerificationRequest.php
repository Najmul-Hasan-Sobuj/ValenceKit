<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Passkey verification request for admin authentication.
 *
 * Reads from the admin-specific session key to avoid collisions
 * with the user passkey session stored under the default key.
 */
class AdminPasskeyVerificationRequest extends PasskeyVerificationRequest
{
    /**
     * Get the admin passkey verification options from the session.
     *
     * @throws ValidationException
     */
    public function verificationOptions(): PublicKeyCredentialRequestOptions
    {
        /** @var string|null $serialized */
        $serialized = $this->session()->pull('admin_passkey.verification_options');

        if (! $serialized) {
            throw ValidationException::withMessages([
                'credential' => __('Passkey verification session expired. Please try again.'),
            ]);
        }

        return WebAuthn::fromJson($serialized, PublicKeyCredentialRequestOptions::class);
    }
}
