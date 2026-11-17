<?php

namespace App\Services;

use App\Jobs\DeliverMail;
use App\Models\MailDelivery;
use App\Models\Proposal;
use App\Notifications\BusinessMailNotification;
use App\Notifications\ProposalStatusNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class MailDeliveryService
{
    public function queue(Model $subject, Model|string $recipient, Notification $notification, string $action, ?string $permission = null, ?string $deduplicationKey = null): MailDelivery
    {
        if ($deduplicationKey !== null) {
            $deduplicationKey = hash('sha256', implode('|', [$subject->getMorphClass(), $subject->getKey(), $recipient instanceof Model ? $recipient->getMorphClass().':'.$recipient->getKey() : $recipient, $action, $deduplicationKey]));
        }

        return DB::transaction(function () use ($subject, $recipient, $notification, $action, $permission, $deduplicationKey): MailDelivery {
            $attributes = [
                'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                'recipient_type' => $recipient instanceof Model ? $recipient->getMorphClass() : null,
                'recipient_id' => $recipient instanceof Model ? $recipient->getKey() : null,
                'email' => $recipient instanceof Model ? $recipient->getAttribute('email') : $recipient,
                'action' => $action, 'permission' => $permission,
                'notification' => match (true) {
                    $notification instanceof ProposalStatusNotification => ['type' => 'proposal', 'number' => $notification->number, 'message' => $notification->message, 'url' => $notification->documentUrl],
                    $notification instanceof BusinessMailNotification => ['type' => 'business', 'title' => $notification->title, 'message' => $notification->message],
                    default => throw new \InvalidArgumentException('Unsupported delivery message.'),
                },
                'fingerprint' => $subject instanceof Proposal ? $subject->fresh()->approvalFingerprint() : null,
            ];
            $delivery = $deduplicationKey === null
                ? MailDelivery::create($attributes)
                : MailDelivery::firstOrCreate(['deduplication_key' => $deduplicationKey], $attributes);
            if ($delivery->wasRecentlyCreated) {
                DB::afterCommit(fn () => $this->dispatch($delivery));
            }

            return $delivery;
        });
    }

    public function dispatch(MailDelivery $delivery): void
    {
        try {
            DeliverMail::dispatch($delivery->id)->afterCommit();
            $delivery->refresh();
            if ($delivery->status === 'pending') {
                $delivery->update(['status' => 'queued', 'error_code' => null]);
            }
        } catch (\Throwable) {
            $delivery->update(['error_code' => 'queue_dispatch_failed']);
        }
    }
}
