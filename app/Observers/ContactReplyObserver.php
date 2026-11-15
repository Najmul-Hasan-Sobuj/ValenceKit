<?php

namespace App\Observers;

use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Notifications\AdminNotification;
use App\Services\AdminNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ContactReplyObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ContactReply $reply): void
    {
        if ($reply->incoming && ! $reply->notification && ContactMessage::query()->whereKey($reply->contact_message_id)->where('created_at', '<=', $reply->created_at)->exists()) {
            app(AdminNotifier::class)->send(new AdminNotification('contact.view', 'New Gmail reply', 'A client conversation has a new reply.', 'admin.contact.index'));
        }
    }
}
