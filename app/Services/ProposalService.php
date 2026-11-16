<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\ProposalClientInvitation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Notification as NotificationMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class ProposalService
{
    public function __construct(private AuditLogService $audit) {}

    public function sendPasswordLink(Proposal $proposal, User $user, bool $invitation = false): string
    {
        $broker = Password::broker('users');

        return $broker->sendResetLink(['email' => $user->email], function (User $recipient, string $token) use ($proposal, $invitation, $broker): string {
            $notification = $invitation
                ? new ProposalClientInvitation($proposal->number, route('proposals.public.show', $proposal), route('password.reset', ['token' => $token, 'email' => $recipient->email]))
                : new ResetPassword($token);
            $sent = $this->deliver($proposal, $recipient->email, $notification, $invitation ? 'proposal.invitation_delivery' : 'proposal.password_reset_delivery');
            if (! $sent) {
                $broker->deleteToken($recipient);
            }

            return $sent ? Password::RESET_LINK_SENT : 'passwords.delivery_failed';
        });
    }

    public function claimDocuments(User $user): void
    {
        Proposal::query()->whereNull('owner_claimed_at')->whereNull('client_user_id')
            ->whereRaw('LOWER(client_email) = ?', [mb_strtolower($user->email)])
            ->update(['client_user_id' => $user->id, 'owner_claimed_at' => now()]);
    }

    public function authorizeRecipient(User $user, Proposal $proposal): Proposal
    {
        return DB::transaction(function () use ($user, $proposal): Proposal {
            $locked = Proposal::whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'draft', 404);
            if ($locked->owner_claimed_at === null && $locked->client_user_id === null && mb_strtolower($locked->client_email) === mb_strtolower($user->email)) {
                $locked->forceFill(['client_user_id' => $user->id]);
                $locked->owner_claimed_at = now();
                $locked->save();
                $this->audit->record('proposal.owner_claimed', $locked, actor: $user);
            }
            abort_unless($locked->client_user_id === $user->id, 404);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    public function documentData(Proposal $proposal, bool $admin = false): array
    {
        $values = $proposal->accepted_snapshot ?? $proposal->documentValues();
        $root = $proposal->root_id ?? $proposal->id;

        return $values + [
            'status' => $proposal->displayStatus(),
            'fingerprint' => $proposal->approvalFingerprint(),
            'signer_name' => $proposal->signer_name,
            'responded_at' => $proposal->responded_at?->toIso8601String(),
            'recipient_verified_at' => $proposal->getAttribute('recipient_verified_at')?->toIso8601String(),
            'response_note' => $proposal->response_note,
            'invitation_status' => $admin ? $proposal->invitation_status : null,
            'invitation_sent_at' => $admin ? $proposal->invitation_sent_at?->toIso8601String() : null,
            'invoice' => $admin ? Invoice::where('proposal_id', $proposal->id)->first(['number', 'status', 'amount_minor'])?->toArray() : null,
            'history' => AuditLog::where('subject_type', $proposal->getMorphClass())->where('subject_id', $proposal->id)
                ->orderByDesc('id')->limit(100)->get(['id', 'action', 'description', 'created_at'])->map(fn (AuditLog $event): array => ['id' => $event->id, 'action' => $event->action, 'description' => $admin ? $event->description : null, 'created_at' => $event->created_at?->toIso8601String()])->all(),
            'versions' => Proposal::where(fn ($query) => $query->whereKey($root)->orWhere('root_id', $root))
                ->when(! $admin, fn ($query) => $query->where('client_user_id', $proposal->client_user_id)->where('status', '!=', 'draft'))
                ->orderByDesc('revision')->get(['public_id', 'number', 'revision', 'status'])->toArray(),
        ];
    }

    public function deliver(Proposal $proposal, string $email, NotificationMessage $notification, string $event): bool
    {
        try {
            Notification::route('mail', $email)->notify($notification);
            $this->audit->record($event, $proposal, metadata: ['delivery' => 'accepted_by_transport']);

            return true;
        } catch (\Throwable) {
            $this->audit->record($event.'.failed', $proposal, description: 'Email transport failed. Retry is available.');

            return false;
        }
    }

    public function codeRetryAfter(Proposal $proposal): int
    {
        return RateLimiter::availableIn('proposal-code-cooldown:'.$proposal->id);
    }
}
