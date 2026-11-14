<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class GmailSender
{
    public function __construct(private GmailToken $tokens) {}

    public function send(GmailMailbox $mailbox, ContactMessage $contact, string $body): string
    {
        $email = (new Email)
            ->from($mailbox->email)
            ->to($contact->email)
            ->subject($contact->subject ? 'Re: '.preg_replace('/^re:\s*/i', '', $contact->subject) : 'Website enquiry #'.$contact->id)
            ->text($body);

        $previous = $contact->replies()->whereNotNull('rfc_message_id')->latest('created_at')->latest('id')->value('rfc_message_id');
        if ($previous) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $previous);
            $email->getHeaders()->addIdHeader('References', $previous);
        }

        return $this->deliver($mailbox, $email);
    }

    public function notify(GmailMailbox $mailbox, ContactMessage $contact): string
    {
        $details = "From: {$contact->name} <{$contact->email}>";
        if ($contact->phone) {
            $details .= "\nPhone: {$contact->phone}";
        }
        if ($contact->referral_source) {
            $details .= "\nReferral: {$contact->referral_source}";
        }
        $email = (new Email)
            ->from($mailbox->email)
            ->to($mailbox->email)
            ->replyTo($contact->email)
            ->subject('Website enquiry #'.$contact->id)
            ->text($details."\n\n".$contact->message);

        return $this->deliver($mailbox, $email);
    }

    public function notifyIfNeeded(GmailMailbox $mailbox, ContactMessage $contact): void
    {
        if ($contact->initiated_by_admin) {
            return;
        }

        Cache::lock('contact-notification-'.$contact->id, 180)->block(5, function () use ($mailbox, $contact) {
            if ($contact->replies()->where('notification', true)->exists()) {
                return;
            }
            $id = $this->notify($mailbox, $contact);
            $contact->replies()->create(['body' => $contact->message, 'rfc_message_id' => $id, 'notification' => true]);
        });
    }

    protected function deliver(GmailMailbox $mailbox, Email $email): string
    {
        $transport = new EsmtpTransport('smtp.gmail.com', 465, true, authenticators: [new XOAuth2Authenticator]);
        $transport->getStream()->setTimeout(15);
        $transport->setUsername($mailbox->email);
        $transport->setPassword($this->tokens->accessToken($mailbox));
        $sent = $transport->send($email);
        if ($sent === null) {
            throw new RuntimeException('Gmail did not accept the message.');
        }

        return trim($sent->getMessageId(), '<>');
    }
}
