<?php

namespace App\Http\Controllers;

use App\Jobs\ForwardContactMessage;
use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use App\Services\GmailSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function store(Request $request, GmailSender $sender): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:10000'],
            'referral_source' => ['nullable', 'string', 'max:255'],
        ]);

        $contact = ContactMessage::create($data);
        $mailbox = GmailMailbox::query()->first();
        if ($mailbox) {
            try {
                ForwardContactMessage::dispatch($mailbox->id, $contact->id)->afterCommit();
            } catch (\Throwable) {
                // The saved message can be forwarded when Gmail is next synced.
            }
        }

        return back();
    }
}
