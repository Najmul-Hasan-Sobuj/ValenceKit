<?php

namespace App\Observers;

use App\Models\ContactMessage;
use App\Notifications\AdminNotification;
use App\Services\AdminNotifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ContactMessageObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ContactMessage $contact): void
    {
        if (! $contact->initiated_by_admin) {
            app(AdminNotifier::class)->send(new AdminNotification('contact.view', 'New contact message', 'A new website enquiry has arrived.', 'admin.contact.index'));
        }
    }
}
