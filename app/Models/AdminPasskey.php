<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passkeys\Passkey;

/**
 * Admin passkey model backed by the admin_passkeys table.
 *
 * Extends the base Passkey model so it inherits credential casting,
 * the authenticator accessor, and WebAuthn update logic unchanged.
 * Only the table name and owner relationship differ from user passkeys.
 */
class AdminPasskey extends Passkey
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin_passkeys';

    /**
     * Get the admin that owns this passkey.
     *
     * Overrides the base user() relationship to point at the admins table
     * via the admin_id foreign key.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
