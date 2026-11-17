<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Invoice;
use App\Models\MailDelivery;
use App\Models\Proposal;
use App\Services\AuditLogService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Notification as Notifications;
use Throwable;

class DeliverMail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('mail');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('mail-delivery:'.$this->deliveryId))->releaseAfter(10)->expireAfter(90)];
    }

    /**
     * Execute the job.
     */
    public function handle(AuditLogService $audit): void
    {
        $delivery = MailDelivery::find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, ['sent', 'cancelled'], true)) {
            return;
        }
        $subject = $delivery->subject;
        $recipient = $delivery->recipient;
        $valid = $subject !== null && (! $delivery->expires_at || $delivery->expires_at->isFuture());
        if ($delivery->recipient_id !== null) {
            $valid = $valid && $recipient !== null && $recipient->getAttribute('email') === $delivery->email;
        }
        if ($recipient instanceof Admin) {
            $valid = $valid && $recipient->is_active && (! $delivery->permission || $recipient->can($delivery->permission));
        }
        if ($subject instanceof Invoice && ! $recipient instanceof Admin) {
            $valid = $valid && $subject->customer_email === $delivery->email;
        }
        if ($subject instanceof Invoice && $delivery->action === 'invoice.reminder') {
            $valid = $valid && $subject->status === 'issued' && (int) $subject->payments()->sum('amount_minor') < $subject->amount_minor;
        }
        if ($subject instanceof Proposal) {
            $valid = $valid && hash_equals($delivery->fingerprint ?? '', $subject->approvalFingerprint());
            if (! $recipient instanceof Admin) {
                $valid = $valid && ($subject->owner_claimed_at
                    ? $subject->client_user_id === $delivery->recipient_id
                    : mb_strtolower($subject->client_email) === mb_strtolower($delivery->email));
            }
            if ($delivery->action === 'proposal.reminder_sent') {
                $valid = $valid && $subject->displayStatus() === 'sent';
            }
        }
        if (! $valid) {
            $delivery->update(['status' => 'cancelled', 'error_code' => 'recipient_or_document_changed']);

            return;
        }
        $notification = $delivery->message();
        $delivery->increment('attempts');
        $delivery->update(['status' => 'processing', 'error_code' => null]);
        try {
            if ($recipient && method_exists($recipient, 'notifyNow')) {
                $recipient->notifyNow($notification);
            } else {
                Notifications::route('mail', $delivery->email)->notifyNow($notification);
            }
        } catch (Throwable) {
            $delivery->update(['status' => 'retrying', 'error_code' => 'mail_transport_failed']);
            throw new \RuntimeException('Email delivery failed; retry is scheduled.');
        }
        $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        $audit->record($delivery->action, $subject, metadata: ['delivery' => 'accepted_by_transport', 'mail_delivery_id' => $delivery->id]);
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = MailDelivery::find($this->deliveryId);
        if ($delivery && ! in_array($delivery->status, ['sent', 'cancelled'], true)) {
            $delivery->update(['status' => 'failed', 'error_code' => 'mail_transport_failed']);
            app(AuditLogService::class)->record($delivery->action.'.failed', $delivery->subject, metadata: ['mail_delivery_id' => $delivery->id], description: 'Email delivery failed after retries.');
        }
    }
}
