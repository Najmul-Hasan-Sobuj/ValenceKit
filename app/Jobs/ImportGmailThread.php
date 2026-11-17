<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\GmailMailbox;
use App\Services\AuditLogService;
use App\Services\GmailSync;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

class ImportGmailThread implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 75;

    public function __construct(public int $adminId, public int $mailboxId, public string $email, public string $threadId, public string $uid) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 120];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    public function handle(GmailSync $sync, AuditLogService $audit): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }
        $admin = Admin::query()->find($this->adminId);
        $mailbox = GmailMailbox::query()->find($this->mailboxId);
        if (! $admin || ! $admin->is_active || ! $admin->can('contact.view') || ! $admin->can('contact.manage') || ! $mailbox) {
            $this->fail('Gmail import is no longer authorized.');

            return;
        }
        try {
            $contact = $sync->importThread($mailbox, $this->email, $this->threadId, $this->uid);
        } catch (ValidationException $exception) {
            $this->fail($exception);

            return;
        }
        $audit->record('contact.conversation.imported', $contact, actor: $admin);
    }
}
