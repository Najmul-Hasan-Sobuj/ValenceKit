<?php

namespace App\Actions\Admin;

use App\Models\AdminPasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredential;

/**
 * Verifies an admin passkey credential against the admin_passkeys table.
 *
 * Extends the base VerifyPasskey action and overrides only the methods that
 * differ for admins: passkey lookup (admin_passkeys table instead of passkeys)
 * and ownership check (admin_id FK instead of user_id).
 */
class VerifyAdminPasskey extends VerifyPasskey
{
    /**
     * Retrieve the admin passkey matching the given credential.
     *
     * @throws InvalidPasskeyException
     */
    public function getPasskey(PublicKeyCredential $credential, bool $lock = false): Passkey
    {
        $credentialId = Base64UrlSafe::encodeUnpadded($credential->rawId);

        $query = AdminPasskey::where('credential_id', $credentialId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first()
            ?? throw InvalidPasskeyException::make('Passkey not recognized. It may have been removed from this account.');
    }

    /**
     * Ensure the passkey belongs to the expected admin.
     *
     * Uses admin_id instead of user_id for the ownership check.
     *
     * @throws InvalidPasskeyException
     */
    public function ensurePasskeyBelongsToUser(Passkey $passkey, ?PasskeyUser $user): void
    {
        if (! $user instanceof PasskeyUser) {
            return;
        }

        $identifier = $user->getKey();

        /** @var AdminPasskey $passkey */
        if (! is_scalar($identifier) || (string) $passkey->admin_id !== (string) $identifier) {
            throw InvalidPasskeyException::make('Passkey not recognized. It may have been removed from this account.');
        }
    }
}
