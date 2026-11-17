<?php

namespace App\Jobs;

use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use App\Services\GmailSender;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ForwardContactMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $mailboxId, public int $contactId)
    {
        $this->onQueue('mail');
    }

    /**
     * Execute the job.
     */
    public function handle(GmailSender $sender): void
    {
        $mailbox = GmailMailbox::find($this->mailboxId);
        $contact = ContactMessage::find($this->contactId);
        if ($mailbox && $mailbox->refresh_token !== '' && $contact) {
            try {
                $sender->notifyIfNeeded($mailbox, $contact);
            } catch (\Throwable) {
                throw new \RuntimeException('Contact forwarding failed; retry is scheduled.');
            }
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
