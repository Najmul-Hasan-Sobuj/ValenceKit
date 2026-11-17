<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use App\Services\GmailSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGmailMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $timeout = 75;

    public int $uniqueFor = 300;

    public function __construct(public int $mailboxId, public ?int $contactId = null, public ?int $adminId = null)
    {
        $this->onQueue('imports');
    }

    public function uniqueId(): string
    {
        return $this->mailboxId.':'.($this->contactId ?? 'all').':'.($this->adminId ?? 'system');
    }

    /**
     * Execute the job.
     */
    public function handle(GmailSync $sync): void
    {
        $mailbox = GmailMailbox::find($this->mailboxId);
        if (! $mailbox || $mailbox->refresh_token === '') {
            return;
        }
        if ($this->adminId !== null) {
            $actor = Admin::find($this->adminId);
            if (! $actor?->is_active || ! $actor->can('contact.view') || ! $actor->can('contact.manage')) {
                return;
            }
        }
        if ($this->contactId !== null) {
            if ($contact = ContactMessage::find($this->contactId)) {
                try {
                    $sync->syncSingle($mailbox, $contact);
                } catch (\Throwable) {
                    throw new \RuntimeException('Conversation sync failed; retry is scheduled.');
                }
            }

            return;
        }
        ContactMessage::query()->chunkById(50, function ($contacts) use ($mailbox): void {
            foreach ($contacts as $contact) {
                if (! $contact->initiated_by_admin && ! $contact->replies()->where('notification', true)->exists()) {
                    ForwardContactMessage::dispatch($mailbox->id, $contact->id)->afterCommit();
                }
                if ($contact->gmail_thread_id || $contact->replies()->whereNotNull('rfc_message_id')->where('incoming', false)->exists()) {
                    self::dispatch($mailbox->id, $contact->id, $this->adminId)->afterCommit();
                }
            }
        });
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
