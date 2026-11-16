<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface $issued_on
 * @property CarbonInterface $valid_until
 * @property CarbonInterface|null $responded_at
 * @property array{hash: string, expires_at: int, attempts: int, fingerprint: string, delivered?: bool}|null $recipient_verification
 * @property array<string, mixed>|null $accepted_snapshot
 * @property CarbonInterface|null $owner_claimed_at
 * @property CarbonInterface|null $superseded_at
 * @property CarbonInterface|null $last_reminded_at
 * @property CarbonInterface|null $invitation_sent_at
 */
#[Fillable(['created_by', 'number', 'type', 'status', 'title', 'client_name', 'client_email', 'currency', 'items', 'subtotal_minor', 'discount_minor', 'tax_rate_basis_points', 'tax_minor', 'total_minor', 'issued_on', 'valid_until', 'terms', 'notes', 'signer_name', 'response_note', 'responded_at'])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected $hidden = ['recipient_verification'];

    protected static function booted(): void
    {
        static::creating(function (Proposal $proposal): void {
            $proposal->public_id ??= (string) Str::uuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'client_user_id' => 'integer',
            'created_by' => 'integer',
            'revision' => 'integer',
            'accepted_snapshot' => 'array',
            'owner_claimed_at' => 'datetime',
            'superseded_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'recipient_verification' => 'array',
            'recipient_verified_at' => 'datetime',
            'items' => 'array',
            'issued_on' => 'date',
            'valid_until' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function approvalFingerprint(): string
    {
        return hash('sha256', json_encode($this->documentValues(), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function documentValues(): array
    {
        return $this->only(['public_id', 'number', 'revision', 'type', 'title', 'client_name', 'client_email', 'currency', 'items', 'subtotal_minor', 'tax_minor', 'total_minor', 'discount_minor', 'tax_rate_basis_points', 'terms', 'notes']) + ['issued_on' => $this->issued_on->format('Y-m-d'), 'valid_until' => $this->valid_until->format('Y-m-d')];
    }

    public function displayStatus(): string
    {
        return $this->superseded_at ? 'superseded' : ($this->status === 'sent' && $this->valid_until->lt(today()) ? 'expired' : $this->status);
    }

    /** @return array<string, mixed> */
    public function auditLogValues(): array
    {
        return $this->only(['number', 'type', 'status', 'title', 'client_name', 'client_email', 'currency', 'subtotal_minor', 'discount_minor', 'tax_rate_basis_points', 'tax_minor', 'total_minor', 'issued_on', 'valid_until', 'signer_name', 'responded_at']);
    }
}
